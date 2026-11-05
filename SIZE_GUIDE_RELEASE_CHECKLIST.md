# Size Guide — Dual Unit (Inch + CM) Release Checklist

**Model:** one stored number per cell (`size_guide_values.measurement_value`, unchanged) + one new guide-level unit
(`size_guides.measurement_unit` = `'in'` | `'cm'`, default `'in'`). The Storefront converts for display only
(1 in = 2.54 cm); it never writes measurements.

## Database
- **SQL file:** `database/sql/2026-11-06_size-guide-dual-unit.sql` (no Laravel migration). Re-runnable; no DROP/DELETE; no measurement value is modified.
- **Execution order:** 1) backup → 2) run the SQL → 3) deploy Admin → 4) deploy Storefront. Admin saves fail until the column exists; the Storefront tolerates either order.
- **Backup:** `mysqldump --single-transaction hishabiz_ecom size_guides size_guide_attributes size_guide_values products > size_guides_backup.sql` (the DB is shared and live).
- **What it does:** adds `measurement_unit`; backfills it from each guide's attribute units (all-cm → `cm`, all-inch → `in`) so existing numbers keep their meaning; normalises attribute unit labels to the guide unit (labels only).
- **Verification:** queries V1–V5 are at the bottom of the SQL file. V3 lists guides with mixed/unknown attribute units (or none): set their unit by hand in Admin after checking the numbers. V4/V5 must match the pre-run counts/sums.
- **Rollback:** `ALTER TABLE size_guides DROP COLUMN measurement_unit;` — safe only if no guide has yet been saved in Inch (otherwise those numbers would be read as cm). Restore from backup otherwise.
- `sql/ecom.sql` (shared source of truth, byte-identical in both repos) was **not** edited; add the column there too if you want fresh installs to include it.

## Admin (`admin-shaotal`)
- **Changed:** `SizeGuideController` (validates `measurement_unit`, numeric values ≤2 dp, attribute `unit` now always = guide unit), `SizeGuide` model (unit constants), `size-guides/form|index|show.blade.php`, new `tests/Feature/SizeGuideUnitTest.php`.
- **UI:** "Measurement Unit" selector (default Inch) above the chart; unit shown in every header and cell. Switching the unit converts the visible numbers (rounded to 2 dp) unless "convert" is unticked, which keeps the numbers and only relabels. Nothing is saved until **Save**.
- **Behaviour change:** measurements must now be plain numbers (the column is `DECIMAL(10,2)`; values like `38-40` could never be stored).
- **Deploy:** no cache/config/route changes. `php artisan view:clear` if views are cached.
- **Tests:** needs the SQL applied to the local test DB, then `php artisan test tests/Feature/SizeGuideUnitTest.php` (5 pass). The full suite also has 7 pre-existing failures (`products.source_url` missing on the test DB) unrelated to this change.
- **Manual:** create a guide (Inch default) → save → edit, switch to CM (values convert) → save → product pages still linked.

## Storefront (`shaotal`)
- **Shares the database** (same host/DB name `hishabiz_ecom`, verified from both `.env` files; secrets not read out) and reads size guides directly via `App\Models\Catalog::sizeGuide()`; no API between the apps.
- **Changed:** `Catalog::sizeGuide()` (adds `unit`; falls back to the legacy attribute unit, then `cm`, if the column is missing), `public/assets/js/product-detail.js` (Inch/CM toggle, default Inch, conversion for display only, converted values 1 dp, native values shown as stored), `product-detail.css` (toggle), `tests/Feature/SizeGuideUnitTest.php`.
- **Contract:** `GET /size-guides/{key}` → `{title, details, unit:'in'|'cm', attributes:[{id,name}], rows:{size:{attributeId:number}}}`. `unit` is the unit of the stored numbers; attribute `unit` was removed from the payload.
- **Caching:** the size guide endpoint is not part of the `storefront.catalog.v2` cache; changes show immediately. Assets are mtime-versioned.
- **Tests:** the storefront test uses in-memory SQLite; `pdo_sqlite` is not installed on this machine, so it could not be run here (the payload was verified against the local MySQL scratch DB instead).

---

## Update 2026-11-06 — range values and stored-vs-display unit

**Root causes**
1. *Ranges:* my first version validated every cell as a number (and the show page trimmed zeros), so `71.1-76.2` was rejected/mangled. `size_guide_values.measurement_value` must be `VARCHAR` (already proposed in `sql/proposed_changes.sql`); on a `DECIMAL` database a range can't be stored at all.
2. *CM read as Inch:* the editor took the guide's unit from `size_guides.measurement_unit`, whose column default is `'in'`. A legacy guide whose numbers are cm (attribute unit `cm`) but whose column is still `'in'` (SQL not run, or rows written by the old Admin after the SQL) was therefore shown/saved as Inch, and the form's switch-conversion then multiplied by 2.54 on top.

**How existing values are handled now** (`SizeGuide::storedUnit()`, `attributeUnit()`, `App\Support\Measurement`)
- *Stored unit* (what the saved numbers mean) is read from reliable stored data: every attribute with the same recognised unit → that unit (even if the column says otherwise); otherwise the guide column; otherwise `cm`. Never blindly `in` for existing data. Mixed legacy guides: each attribute keeps its own unit.
- *Display unit* is only a view. The editor opens in the stored unit (nothing converted, nothing rounded); the show page has an Inch | CM view (`?unit=`). Each value is converted exactly once, from its original, each end of a range, 1 in = 2.54 cm, ≤2 decimals. In the editor every cell remembers its original, so Inch→CM→Inch restores the exact original (500 toggles checked).
- *Saving* writes all cells in the selected unit and sets every attribute unit and the column to it, so meaning and unit stay in sync. Ranges are stored as `71.1-76.2` (spaces removed). Inch display format: `28"-30"`.
- No bulk conversion anywhere; no SQL touches values.

**Database:** `database/sql/2026-11-06_size-guide-range-values.sql` (not executed on production; applied only to the local scratch/test DBs). Widens `measurement_value` to `VARCHAR(50)` only if still `DECIMAL`; run after the 2026-11-05 file. Backup first; rollback = restore backup once ranges exist.

**Storefront follow-up REQUIRED (not changed here):** `App\Models\Catalog::sizeGuide()` casts values with `(float)`, so a range like `71.1-76.2` would display as `71.1`, and `public/assets/js/product-detail.js` converts numbers only. Do not publish range guides to customers until the storefront parses ranges (strings) and converts each end.

**Tests:** `tests/Feature/SizeGuideUnitTest.php` — 13 pass (cm single/range → inch, inch single/range, round trip, CM guide with wrong `'in'` column, mixed-unit legacy guide, save→reopen unchanged, range saved verbatim, invalid values rejected).
