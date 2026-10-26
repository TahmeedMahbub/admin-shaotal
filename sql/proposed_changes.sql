-- PROPOSED, NOT EXECUTED. Review, then run manually against the shared database (the storefront uses it too).
-- Nothing in the admin app requires these to run: the five modules below show a "pending schema approval" page
-- until the tables exist, and the rest of the admin works without them.
-- Conventions follow sql/ecom.sql: InnoDB, utf8mb4, BIGINT UNSIGNED ids, created_by/updated_by -> admin_users (SET NULL).
-- No migrations. Run each section on its own; each is independent except where noted.

SET NAMES utf8mb4;

/* ==========================================================================================
 1. OFFERS / DISCOUNTS            (design: offers, offer-add, offer-details, offer-edit)
 Purpose   : automatically-applied promotional discounts (no code), as opposed to `coupons`, which need a code.
 Fields    : name, description, discount_type (percentage|fixed_amount), discount_value, starts_at/ends_at, status.
 Scope     : offer_products / offer_categories pivot tables (same shape as coupon_products / coupon_categories);
             no rows in either = applies to the whole catalog.
 Overlap   : coupons already has code-based discounts and the same two pivots. Offers deliberately do NOT use
             the coupons table because they have no code and no usage limits; applying them is a storefront change.
 Storefront: on read, resolve the best active offer per product (status='active' and now() between the dates)
             and lower the effective selling price. Until the storefront does this the admin just stores the data.
 Indexes   : (status, starts_at, ends_at) for the "active now" lookup; pivot PKs cover product/category lookups.
========================================================================================== */
CREATE TABLE offers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(190) NOT NULL,
 description VARCHAR(1000),
 discount_type ENUM('percentage','fixed_amount') NOT NULL,
 discount_value DECIMAL(12,2) NOT NULL,
 starts_at DATETIME NULL,
 ends_at DATETIME NULL,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CHECK (discount_value >= 0 AND (discount_type <> 'percentage' OR discount_value <= 100)),
 INDEX (status, starts_at, ends_at),
 FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE offer_products (
 offer_id BIGINT UNSIGNED NOT NULL, product_id BIGINT UNSIGNED NOT NULL,
 created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (offer_id, product_id), INDEX (product_id),
 FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE CASCADE,
 FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
 FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE offer_categories (
 offer_id BIGINT UNSIGNED NOT NULL, category_id BIGINT UNSIGNED NOT NULL,
 created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (offer_id, category_id), INDEX (category_id),
 FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE CASCADE,
 FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
 FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


/* ==========================================================================================
 2. PAGES                          (design: pages, page-add, page-details, page-edit)
 Purpose   : standalone CMS pages (About, Privacy, Terms, Shipping, Returns, FAQ - the storefront footer links to
             these but has no pages today).
 Fields    : title, slug (URL), HTML content, featured image URL (Cloudinary), status (published|draft), SEO.
 Relations : none. Linkable from navigation_items.url (e.g. /pages/about-us).
 Overlap   : none (site_settings holds scalar values, not long-form pages).
 Storefront: new route /pages/{slug} -> select the row where slug=? and status='published'. Content is admin-authored
             HTML, so the storefront must sanitise or the admin must only allow trusted editors.
 Indexes   : UNIQUE(slug) serves the lookup; (status) for listings.
========================================================================================== */
CREATE TABLE pages (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(220) NOT NULL,
 slug VARCHAR(240) NOT NULL UNIQUE,
 content LONGTEXT,
 featured_image_url VARCHAR(2048),
 status ENUM('draft','published') NOT NULL DEFAULT 'draft',
 seo_title VARCHAR(255), seo_description VARCHAR(500),
 created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX (status),
 FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


/* ==========================================================================================
 3. NAVIGATION                     (design: navigation, navigation-add, navigation-edit)
 Purpose   : editable header menu, dropdown sub-items and footer link groups.
 Fields    : title, url, location (header|footer_quick_links|footer_customer_service), parent_id (dropdown nesting),
             sort_order, status.
 Relations : parent_id -> navigation_items(id) (self reference, CASCADE so removing a parent removes its sub-items).
 Overlap   : the storefront currently derives its header menu from `categories`/`subcategories` and hardcodes the footer.
 Storefront: read active items ordered by (location, parent_id, sort_order), build the menu tree; fall back to the
             current category-derived menu when the table is empty.
 Indexes   : (location, status, sort_order), (parent_id).
========================================================================================== */
CREATE TABLE navigation_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 parent_id BIGINT UNSIGNED NULL,
 location ENUM('header','footer_quick_links','footer_customer_service') NOT NULL DEFAULT 'header',
 title VARCHAR(160) NOT NULL,
 url VARCHAR(2048) NOT NULL,
 sort_order INT NOT NULL DEFAULT 0,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX (location, status, sort_order), INDEX (parent_id),
 FOREIGN KEY (parent_id) REFERENCES navigation_items(id) ON DELETE CASCADE,
 FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


/* ==========================================================================================
 4. SITE CONTENTS                  (design: site-contents, site-content-details, site-content-edit)
 Purpose   : admin-editable text blocks at predefined locations (footer info, announcement text, etc.).
             Location slugs are read-only in the admin: rows are created by this script / developers, admins edit
             title, HTML content and status only.
 Fields    : slug (location key), title, content (HTML), status (enabled|disabled).
 Relations : none.
 Overlap   : site_settings stores JSON scalars keyed by name. Short single values could live there, but the design
             edits titled rich-text blocks with an enabled flag, which does not fit a key->JSON row well.
 Storefront: SELECT content FROM site_contents WHERE slug=? AND status='enabled' (cache per slug).
 Indexes   : UNIQUE(slug).
========================================================================================== */
CREATE TABLE site_contents (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 slug VARCHAR(120) NOT NULL UNIQUE,
 title VARCHAR(220) NOT NULL,
 content LONGTEXT,
 status ENUM('enabled','disabled') NOT NULL DEFAULT 'enabled',
 created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
 FOREIGN KEY (updated_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO site_contents (slug, title, content, status) VALUES
 ('footer_about', 'Footer About Text', NULL, 'enabled'),
 ('shipping_info', 'Shipping Information', NULL, 'enabled'),
 ('returns_policy', 'Returns Policy', NULL, 'enabled')
ON DUPLICATE KEY UPDATE slug = VALUES(slug);


/* ==========================================================================================
 5. HOMEPAGE SETTINGS              (design: homepage)
 Purpose   : hero headline/subtitle, hero slider toggle, announcement bar toggle + message, featured-products
             section title.
 Design    : NO new table. These are single scalar settings, so they fit the existing `site_settings` table
             (setting_key UNIQUE, setting_value JSON). The admin Homepage page reads/writes these keys only.
 Keys      : below; setting_value is JSON (strings quoted, booleans true/false).
 Overlap   : hero slides themselves already live in `hero_slides` (managed under Banners).
 Storefront: read the keys through the existing site_settings loader; the storefront already consumes keys such as
             home_*_image and social_* the same way.
========================================================================================== */
INSERT INTO site_settings (setting_key, setting_value, description) VALUES
 ('home_hero_slider_enabled', 'true', 'Homepage: show the hero banner slider'),
 ('home_hero_title', JSON_QUOTE(''), 'Homepage: hero headline'),
 ('home_hero_subtitle', JSON_QUOTE(''), 'Homepage: hero subtitle'),
 ('home_announcement_enabled', 'false', 'Homepage: show the top announcement bar'),
 ('home_announcement_text', JSON_QUOTE(''), 'Homepage: announcement bar message'),
 ('home_featured_title', JSON_QUOTE(''), 'Homepage: featured products section title')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);


/* ==========================================================================================
 SIZE GUIDE MEASUREMENTS AS TEXT
 Purpose   : size guide cells must accept text such as '38-40' or 'M/L', not only numbers.
 Storefront: Catalog.php (~line 236) casts measurement_value with (float); change that to a plain string.
========================================================================================== */
ALTER TABLE size_guide_values MODIFY measurement_value VARCHAR(50) NOT NULL;


/* ==========================================================================================
 URL-TO-DRAFT PRODUCT IMPORTER (Phase 1: submit URLs, de-duplicate, fetch and extract)
 Purpose   : stage externally sourced products for review before they become draft products.
 Flow      : an admin pastes URLs -> one product_import_items row per unique normalized URL (queued) ->
             `php artisan product-imports:process` (or the "Fetch now" button) fetches and extracts it
             -> raw_data/source_images hold the extraction; draft_data / generated_images are filled by
             later phases (AI structuring, image processing). product_id is set only when a draft is saved.
 Duplicates: url_hash = SHA-256 of the normalized URL (tracking params, www, fragment, trailing slash removed).
             The UNIQUE key makes a URL importable once, even across batches or under concurrent submits.
 Statuses  : queued/processing = extraction; needs_review = extracted, awaiting edits; ready = approved by an admin;
             draft_saved = product created with status 'draft'; published = product made active; duplicate = a product
             with the same name/SKU already exists (blocked until the name/SKU is changed); failed = extraction gave up.
 Retries   : attempts / next_attempt_at / last_error back automatic retries (max 3, exponential backoff);
             locked_at lets a crashed run be picked up again. No Laravel queue tables are needed.
 Safety    : products are never touched here; product_id is SET NULL if a product is later deleted.
========================================================================================== */
CREATE TABLE product_import_batches (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(190) NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_import_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 batch_id BIGINT UNSIGNED NOT NULL,
 source_url VARCHAR(2048) NOT NULL,
 normalized_url VARCHAR(2048) NOT NULL,
 url_hash CHAR(64) NOT NULL,
 status ENUM('queued','processing','needs_review','ready','draft_saved','published','duplicate','failed') NOT NULL DEFAULT 'queued',
 attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
 next_attempt_at DATETIME NULL,
 locked_at DATETIME NULL,
 last_error VARCHAR(500) NULL,
 raw_data JSON NULL,
 draft_data JSON NULL,
 source_images JSON NULL,
 generated_images JSON NULL,
 product_id BIGINT UNSIGNED NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_import_url_hash (url_hash),
 INDEX(batch_id,status),
 INDEX(status,next_attempt_at),
 FOREIGN KEY(batch_id) REFERENCES product_import_batches(id) ON DELETE CASCADE,
 FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE SET NULL,
 FOREIGN KEY(created_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


/* ==========================================================================================
 URL-TO-DRAFT IMPORTER: UPGRADE for a database that already ran the first (Phase 1) version of the section above
 Purpose   : the review/approve/draft/publish workflow needs the new status list. Run ONLY where product_import_items
             already exists with the old ENUM('queued','processing','extracted','imaged','ready','saved','failed').
             A database created from the current CREATE TABLE above does not need this.
 Data      : rows extracted by the first version hold inaccurate Fabrilife data, so they are re-queued and will be
             re-extracted by the new extractor (the URL and its duplicate hash are kept).
========================================================================================== */
UPDATE product_import_items
   SET status = 'queued', attempts = 0, next_attempt_at = NULL, locked_at = NULL, last_error = NULL,
       raw_data = NULL, draft_data = NULL, source_images = NULL, generated_images = NULL
 WHERE status IN ('extracted','imaged','ready','saved') AND product_id IS NULL;

ALTER TABLE product_import_items
  MODIFY status ENUM('queued','processing','needs_review','ready','draft_saved','published','duplicate','failed') NOT NULL DEFAULT 'queued';
