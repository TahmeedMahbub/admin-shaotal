# Database rules

- Source of truth: `sql/ecom.sql` (MySQL 8, InnoDB), shared with the storefront at `c:\laragon\www\shaotal`. Single connection (`mysql`, from `.env`); no second database exists.
- No migrations, no DDL run by the app. Needed schema changes → write to `sql/proposed_changes.sql` and ask the user to execute.
- The remote DB is live: no writes/deletes of seeded data without approval. Develop/test on a local scratch DB with env overrides.
- Verify table/column names against `sql/ecom.sql` before writing queries; don't invent columns.
- `admin_users` and `customers` use `password_hash` (bcrypt via `Hash::make`/`Hash::check`). Admin auth = `admin_users` only, status must be `active`.
- Set `created_by`/`updated_by` = current admin id on admin-made writes (FK → `admin_users`, SET NULL). Orders log status changes in `order_timeline.changed_by_admin_id`.
- Money `DECIMAL(12,2)`. Effective price = `COALESCE(variant.price_override, products.price)`; selling = `COALESCE(variant.selling_price_override, products.selling_price, effective_price)`; `selling_price <= price`.
- Products: `has_variants=0` ⇒ `stock_qty` + `sku` on product, `variant_scenario` NULL; `has_variants=1` ⇒ `stock_qty` NULL, `variant_scenario` set, stock/SKU on `product_variants`. Check constraints enforce this. Exactly one `is_main` image per product (app-enforced). Images without `product_image_colors` rows are general.
- Never hard-delete products/variants/customers referenced by orders — deactivate/archive (`order_items.product_id` is SET NULL but snapshots must stay meaningful; `cart_items` and `payments` FKs are RESTRICT; `subcategories.category_id` RESTRICT).
- Multi-table writes (product + variants + images, order status + timeline, coupon + pivots) run in `DB::transaction`.
- Do not add stock deduction/reservation/restoration or stock validation; the admin leaves stock untouched except through product/variant edits.
- Deleting a product removes its variants first (variant FKs to colours/sizes are RESTRICT); ordered products are archived, never deleted.
- JSON columns: `products.badge`, `site_settings.setting_value`, `payments.response_data`.
- Lists: paginate, select needed columns, aggregate counts via grouped queries, not per-row queries.
