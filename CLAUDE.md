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

`App\Services\CloudinaryImageService::upload(UploadedFile, ?folder): string` validates (jpg/png/webp/gif, 5 MB), uploads to `<root>/<folder>` and returns only `secure_url`. Credentials: `CLOUDINARY_CLOUD_NAME/API_KEY/API_SECRET` in `.env`, read only via `config/cloudinary.php`. Controllers call `$this->uploadImage($request, field, folder, $current)`. Existing image URLs are displayed as-is, never re-uploaded.

## Commands

- `php artisan serve`, `php artisan test`, `vendor/bin/pint`.
- Do **not** run `php artisan migrate` or `composer setup` (it migrates).

## Environment notes

- `.env` (git-ignored) points at the LOCAL scratch DB. The shared remote DB is `hishabiz_ecom`; switch only deliberately and never run seeders or write-heavy tests there. Secrets stay in `.env` only.
- `.env` has `QUEUE_CONNECTION=database`, `CACHE_STORE=file`, `SESSION_DRIVER=file`; the SQL has no `jobs`/`cache` tables, so don't use queues/database cache.
- Laravel Boost is not installed; the previous CLAUDE.md/AGENTS.md were only its bootstrap text.
