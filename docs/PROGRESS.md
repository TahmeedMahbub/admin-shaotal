# Progress

_Last updated: 2026-10-16_

## Architecture
Simple MVC on Laravel 13: `routes/web.php` → controller → Eloquent model (`app/Models`, one per table, `TracksAdmin` fills `created_by`/`updated_by`) → Blade. The Materialize/beige design from `design/` is ported to `layouts/admin.blade.php` + `partials/` (data-driven sidebar, navbar, flash) and reusable `components/admin/*` (`field`, `image-field`, `form-shell`, `filters`, `row-actions`, `thumb`, `badge`, `page-header`). Assets are in `public/assets` (subset of `design/assets`). Images go through `App\Services\CloudinaryImageService::upload()` (returns only the `secure_url`); the controller helper `Controller::uploadImage()` wraps it and turns failures into validation errors. No migrations, no schema changes.

## Done (all tested against local scratch DB `shaotal_admin_local`)
- **Auth**: `admin` guard on `admin_users` (`password_hash`), login (email or bare username → `<username>@shaotal.local`), logout, session regeneration, CSRF, throttling (5 tries/min per login+IP), inactive accounts rejected, every other route behind `auth:admin`.
- **Dashboard**: real aggregates (sales, orders, customers, products, month growth, 7d/30d/3m/1y revenue chart, order status mix, recent orders, top products, recent requests, low stock).
- **Catalog**: Categories, Subcategories, Brands, Size guides (editable attribute × size matrix, details page), Products (simple + color/size/color-size variants, images with main flag and per-color assignment, specs, SEO, size guide, badge; deleting an ordered product archives it).
- **Sales**: Orders (filters, details, status + payment updates with `order_timeline` entries, internal note), Customers (list, details, edit incl. default address), Abandoned carts (list, details, reminder log).
- **Requests**: Sourcing requests, Bulk orders (list, details, status/assignee/notes, delete).
- **Marketing/Content/Engagement**: Coupons (+ product/category scope), Banners (= `hero_slides`), Reviews (moderate/reply), Wishlists (add/view/remove), Subscribers, Contacts — full CRUD where designed.
- **Schema-approved modules** (SQL from `sql/proposed_changes.sql`, applied by you on the shared DB and to the local DB for testing): Offers (+product/category scope), Pages, Navigation (header/footer, dropdown parents), Site Content (edit-only; slugs are developer-managed), Homepage (`site_settings` keys). The storefront does not consume these yet.
- `php artisan test`: 7 passing (guest redirects, service validation/failure paths). Feature flows verified with curl against the local DB (see Verification).

## Not done / blockers
1. **Live Cloudinary upload is untested** — no credentials available. Set `CLOUDINARY_CLOUD_NAME/API_KEY/API_SECRET` in `.env`, then upload from Category/Brand/Banner/Product forms. Validation (type, 5 MB), missing-config and failure paths are tested.
2. **Storefront wiring**: nothing in the storefront reads offers, pages, navigation, site contents or homepage keys yet; see the 'Storefront' notes in `sql/proposed_changes.sql`.
3. Dev passwords (`123456`) must be replaced before production; remote DB has **no** admin users — create them deliberately (do not run the seeder remotely; it refuses non-local hosts).
4. Storefront regression: the admin never touches stock or order creation, and storefront code was not modified; storefront was not re-run against the local DB in this phase.
5. Design fields with no column are omitted: brand website URL, size-guide category/unit filter, order "priority", sourcing priority/category, bulk "quoted value", banner placement/destination type (only `button_url`), review image, wishlist variant, subscriber name/notes, coupon/offer redemption history beyond order counts. Product variant images can't be assigned to colors during creation (only after the first save).
6. Wishlist edit and customer delete are not provided (no editable fields / deleting customers would orphan order history).
7. Abandoned-cart "reminder" only records a log row; no email is sent.
8. Stock is deliberately untouched (no deduction/validation), per instructions.

## Verification summary
Login/logout/invalid creds/inactive account/throttle/guest redirects ✔. CRUD + validation + FK-restrict messages on categories, subcategories, brands, size guides, products (simple, variant create/update/delete), coupons (+pivots), banners, reviews, wishlists, subscribers, contacts ✔. Orders: status/payment/note + timeline, invalid status rejected ✔. Customers edit, requests update, cart reminder ✔. Upload: invalid type, >5 MB, not-configured → error shown, nothing saved ✔.
