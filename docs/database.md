# Database

Shared MySQL 8 database `hishabiz_ecom` (remote; host/credentials in `.env`, never in docs). Schema = `sql/ecom.sql` (39 tables, identical to the storefront's copy). Full per-table column notes: `c:\laragon\www\shaotal\docs\database.md`; this file records admin-specific findings.

## Connection
One Laravel `mysql` connection from `.env` (`DB_*`). The storefront uses the same DB, so no second connection is needed. Row counts observed (read-only, 2026-10-16): admin_users 0, categories 5, subcategories 7, brands 5, products 37, product_colors 86, product_sizes 100, product_variants 261, product_specifications 37, size_guides 1, customers 1, customer_addresses 1, carts 1, wishlist_items 3, orders 2, order_items 3, order_timeline 2, payments 2, hero_slides 3, site_settings 3; all others 0 (coupons, reviews, subscribers, contact messages, bulk/sourcing requests, abandoned_cart_logs, product_images*).
*`product_image_colors` reports 170 rows while `product_images` reports 0 (information_schema estimates) — verify with `COUNT(*)` before building the image UI.

## Design module → table mapping

| Module (design pages) | Tables |
|---|---|
| Login (`auth-login-*`) | `admin_users` |
| Dashboard (`index`, `dashboards-ecommerce`) | aggregates: `orders`, `order_items`, `customers`, `products`, `product_variants` |
| Products (`products`, `product-add/edit`) | `products`, `product_colors`, `product_sizes`, `product_variants`, `product_images`, `product_image_colors`, `product_specifications` |
| Categories / Subcategories (list, add, edit) | `categories`, `subcategories` |
| Brands (list, add, edit) | `brands` |
| Size guides (list, details, add, edit) | `size_guides`, `size_guide_attributes`, `size_guide_values` |
| Orders (`orders`, `order-details`) | `orders`, `order_items`, `order_timeline`, `payments` |
| Customers (list, details, edit) | `customers`, `customer_addresses`, `customer_security_logs` (read) |
| Abandoned carts (list, `cart-details`) | `carts`, `cart_items`, `abandoned_cart_logs` |
| Sourcing requests (list, details) | `sourcing_requests`, `sourcing_request_images` |
| Bulk orders (list, details) | `bulk_order_requests`, `bulk_order_request_items`, `bulk_order_item_variants` |
| Coupons (list, details, add, edit) | `coupons`, `coupon_products`, `coupon_categories` |
| Banners (list, details, add, edit) | `hero_slides` |
| Reviews (list, details, add, edit) | `product_reviews` |
| Wishlists (list, details, add, edit) | `wishlist_items` |
| Subscribers (list, details, add, edit) | `newsletter_subscribers` |
| Contacts (list, details, add, edit) | `contact_messages` |

## Design modules with NO table (SQL proposed in `sql/proposed_changes.sql`, not executed)
- **Offers** (4 pages): discount type/value, applicable products/categories, date range, status. Closest existing: `coupons` (code-based), not auto-applied offers. Storefront has no offers logic.
- **Pages** (4 pages, standalone CMS pages), **Navigation** (add/edit/list), **Site contents** (predefined locations, 3 pages), **Homepage** config: no tables. `site_settings` (key → JSON) could hold Homepage/Site contents; Pages and Navigation need rows. The storefront does not read any of these today (it derives nav from categories, hero from `hero_slides`, and reads `site_settings` keys `home_*_image`, `social_*`).
- **Roles/permissions** (`app-access-*` templates): no role tables; treat as out of scope (single admin level).

## Known data-model gaps / conflicts
- Banner design fields (placement, link type, etc.) may exceed `hero_slides` columns (title, subtitle, image_url, mobile_image_url, button_text/url, sort_order, starts_at, ends_at, status) — compare when porting.
- Design prototypes use localStorage ids like `OFF-1001`; real ids are `BIGINT` autoincrement; orders use `order_number`, requests use `request_number`.
- Storefront has pending proposed schema changes (`shaotal/sql/proposed_changes.sql`: postcode/target_date on bulk, postcode/preferred_variant on sourcing, nullable `email`). Admin must tolerate both before/after states (select columns defensively until applied).
- Stock is not decremented at checkout (storefront PROGRESS #5).
- `currency_code` in `site_settings` is NULL; storefront assumes A$.

## Implementation notes
- Admin login: `admin_users.email` unique; seeded dev accounts use `tahmeed@shaotal.local` / `antu@shaotal.local`.
- `order_timeline` rows for payment changes use `old_status`/`new_status` prefixed `payment:`.
- Banners are `hero_slides`; the design's placement/destination-type fields have no columns (only `button_url`).
- Abandoned = cart with items, not `converted`, and `status='abandoned'` or idle over 1 hour.
