# CLAUDE.md

Guidance for Claude Code in this repository.

## Project

Admin panel for the **Shaotal / Bongo Curated** storefront (`c:\laragon\www\shaotal`, A$ currency). Laravel 13 / PHP ^8.3. Both apps connect to the **same MySQL database** (`hishabiz_ecom`, remote). The storefront is the producer of orders/customers/carts/requests; this admin app manages catalog, orders, customers, marketing and content.

- Prepared design: `design/*.html` (Materialize/Sneat-style admin, "Aus Ecom Admin"; assets in `design/assets`, copy served from `public/` when ported). `design/admin/orders.html` is an older duplicate of `design/orders.html`; `design/html/` is the unused template demo set.
- Schema: `sql/ecom.sql` (byte-identical to the storefront's copy). Details: [docs/database.md](docs/database.md), [.claude/rules/database.md](.claude/rules/database.md). Status/blockers: [docs/PROGRESS.md](docs/PROGRESS.md).
- Design JS stores (`design/assets/js/*-store.js`, `catalog.js`, `sales-data.js`) are **localStorage prototypes with electronics dummy data** — they are the UI spec, not data sources. Replace with server-rendered Blade + DB.

## Working rules

- **Port the design 1:1** (same markup/classes/assets). Don't restyle.
- **SQL-first, no migrations.** Never add/run migrations or DDL. Schema changes go in a `sql/proposed_changes.sql` for the user to run manually.
- **Simple MVC.** Route → controller → model/query → Blade. No repository/service layers unless clearly needed.
- **Reusable components.** Layout (sidebar/navbar/footer), tables, status badges, forms, pagination as Blade components/partials.
- **Efficient queries.** Select needed columns, join/eager-load, paginate, use existing indexes.
- **Shared data is shared.** Products, categories, customers, orders live in the storefront DB; never duplicate them.
- **Remote DB is live.** Test writes on a local scratch DB (`shaotal_admin_local`, from `sql/ecom.sql`), not the remote one. No writes to seeded remote data without approval.
- **Minimal tokens.** `sql/ecom.sql` and `design/assets/js/*` are large — grep/read ranges.

## Auth

`admin_users` (columns `password_hash`, `status`, `last_login_at`; no username column) is the only admin identity, via the `admin` guard (`App\Models\AdminUser`). Login accepts an email, or a bare username expanded to `<username>@shaotal.local` (`config/admin.php`). No Laravel `users`/`sessions` tables exist -> file sessions. Never hardcode credentials.

**Local dev admins** (tahmeed / antu, password `123456` - replace before production). Seeder is idempotent, never resets existing passwords, and refuses to run unless `APP_ENV=local` and `DB_HOST` is 127.0.0.1/localhost:
`php artisan db:seed --class=AdminUserSeeder --force`

## Local database

Scratch DB `shaotal_admin_local` (Laragon MySQL, root, no password), built with:
`mysql -uroot shaotal_admin_local < sql/ecom.sql` then `sql/ecommerce_sample_data.sql`, then `sql/local_test_data.sql` (customers/orders/requests for testing). `.env` points at it; the remote DB credentials live only in the storefront's `.env`.

## Images (Cloudinary)

`App\Services\CloudinaryImageService::upload(UploadedFile, ?folder): string` validates (jpg/png/webp/gif, 5 MB), uploads to `<root>/<folder>` and returns only `secure_url`. It targets the **installed `cloudinary/cloudinary_php` v1.x** API (`Cloudinary::config()` + `Cloudinary\Uploader::upload()`); the v2 `Cloudinary\Configuration\Configuration` classes do not exist here. The SDK call is injectable (constructor closure) so tests can mock it. Credentials: `CLOUDINARY_CLOUD_NAME/API_KEY/API_SECRET` in `.env`, read only via `config/cloudinary.php`.

Simple forms call `$this->uploadImage($request, field, folder, $current)`. The **product form** uploads each image by AJAX the moment it is picked (`POST products/upload-image`, returns `{url}`), shows a local preview and progress, and submits only Cloudinary URLs (`images[k][url]`, validated server-side to start with `https://res.cloudinary.com/<cloud>/`). Browsers cannot restore `<input type=file>` after a reload, so uploading at selection time is what lets previews/selections survive validation errors. Abandoned uploads stay in Cloudinary (no cleanup job). Existing image URLs are displayed as-is, never re-uploaded.

## Product form

`resources/views/products/form.blade.php` + `public/assets/js/product-form.js` (follows `design/product-edit.html`; the size-measurement card is replaced by a size-guide dropdown that fills the size chips). The slug follows the name until it is edited by hand. **Save Draft** posts `save_draft=1`: it forces `status='draft'` and relaxes price/stock/variant requirements; the storefront lists only `status='active' AND visibility='visible'`, so drafts stay hidden (no schema change). Colour-to-image assignments are stored in `product_image_colors`.

## Product importer (URL-to-draft)

Admin > Catalog > Import Products. Tables `product_import_batches` / `product_import_items` are in `sql/proposed_changes.sql` (apply manually; a database that ran the earlier version also needs the UPGRADE block there for the new status list). One row per unique normalized URL (`UrlNormalizer`; `url_hash` UNIQUE), so a page is imported once.

- **Extraction (no AI):** `php artisan product-imports:process` or the Fetch now / Retry button runs `ImportProcessor` -> `PageFetcher` (public IPs only, redirects re-checked, size caps) -> `ProductExtractor` (JSON-LD, then OpenGraph, then HTML). `FabrilifeAdapter` handles fabrilife.com, whose JSON-LD is SEO boilerplate; it reads title, BDT price, sizes (size chart if the picker is empty), full description, labelled/plain "Label: value" specs, size charts and every gallery image. Add a new shop by adding an adapter, not by loosening the generic extractor. Never invent values: missing fields stay blank and are listed as "enter manually".
- **Draft + review:** `DraftBuilder` turns the extraction into the product form's own field names; the review page (`product-imports.review`) is `products/form.blade.php` in import mode (draft loaded through `session()->now('_old_input')`, or the saved product once one exists). BDT is never converted; A$ price/sale price/stock are manual.
- **Buttons:** Save Progress (edits stay on the import), Approve (every publish check, marks Ready), Save Draft (creates/updates the product as hidden draft), Publish (explicit, confirm dialog, Active+Visible), Update (published items). All saving goes through `App\Http\Controllers\Concerns\SavesProducts`, shared with `ProductController`. `DuplicateGuard` blocks a name/SKU that already exists; `linkedProduct()` refuses any product created before the import item, so an existing product (e.g. #41) can never be overwritten.
- **Images:** source photos are fetched only if the extraction listed them. Use original, Quick clean (`BackgroundCleaner`, plain backdrops only: it never redraws the product and refuses busy backgrounds; do not make it follow gradients, that leaked into skin/fabric) and AI (`GeminiImageClient`, needs `GEMINI_API_KEY`, optional `GEMINI_IMAGE_MODEL`) produce a private preview; nothing reaches Cloudinary until Approve (`CloudinaryImageService::uploadFile`). Previews live in `storage/app/private/import-previews` and are pruned after a day.
- No Laravel queue tables are used.

## Commands

- `php artisan test` needs the local MySQL DB `shaotal_admin_test` (built from `sql/ecom.sql` + `sql/ecommerce_sample_data.sql` + one `admin_users` row; `phpunit.xml` points at it; tests roll back). Never point tests at the shared DB.
- `vendor/bin/pint`.
- **Before any login/POST against a local web server, prove which server and DB answer.** An older `php -S` on port 8001 once answered instead of the env-overridden one and wrote to the live DB (the importer tables exist there). Use a fresh port (e.g. 8011), keep the server's log, add a diagnostic route/router that prints `config('database.connections.mysql.host')`, `database` and the password length (0) from the serving process, and stop unless it says 127.0.0.1 / `shaotal_admin_local`. PowerShell cannot set an empty `DB_PASSWORD` (the variable is dropped and `.env`'s value is used); start the server from bash with `DB_PASSWORD=`. Prefer feature tests.
- **`.env` may point at the remote shared DB.** To run the admin locally without editing `.env`, use the built-in server so env overrides apply (`php artisan serve` drops `DB_*` overrides): `cd public && DB_HOST=127.0.0.1 DB_DATABASE=shaotal_admin_local DB_USERNAME=root DB_PASSWORD= php -S 127.0.0.1:8001 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`, and confirm with `php artisan tinker` (same env) that `config('database.connections.mysql.host')` is 127.0.0.1 before logging in or posting anything.
- Do **not** run `php artisan migrate` or `composer setup` (it migrates).

## Environment notes

- `.env` (git-ignored) may point at the shared remote DB `hishabiz_ecom` (it did on 2026-10-16). Check `DB_HOST` before running anything that writes; never run seeders or write-heavy tests there. Secrets stay in `.env` only.
- `.env` has `QUEUE_CONNECTION=database`, `CACHE_STORE=file`, `SESSION_DRIVER=file`; the SQL has no `jobs`/`cache` tables, so don't use queues/database cache.
- Laravel Boost is not installed; the previous CLAUDE.md/AGENTS.md were only its bootstrap text.
