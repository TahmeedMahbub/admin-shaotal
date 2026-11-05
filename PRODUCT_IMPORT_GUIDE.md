# Product import guide - `Shaotal_Product_Entry_Template.xlsx`

A data-entry template for about 100 products. **No importer exists yet**; this file only fixes the shape of the data so one can be written later. It mirrors what `products/create` really saves (`SavesProducts::validated()/persist()`) and the live schema (`sql/ecom.sql` plus two live-DB differences, see the end). Currency is A$.

## How the sheets connect

```
Products (1 row / product)  <- product_sku (Parent SKU) ->  Colors    (1 row / product + colour)
        |                                                   Variants  (1 row / colour x size, colour only, or size only)
        |                                                   Images    (1 row / image; colour(s) optional)
        +-- size_guide_key -> existing guide (Ref_Size_Guides) or a NEW one on Size_Guides
                                                  Size_Guides -> Size_Guide_Attributes -> Size_Guide_Values   (guide_key)
```

`product_sku` is the only product key. Product details are never repeated on the other sheets. Variants and Images refer to colours **by name** as written on Colors.

Layout of every entry sheet: row 1 banner, row 2 requirement (REQUIRED / CONDITIONAL / OPTIONAL), row 3 hint, **row 4 = machine column names (do not rename)**, data from row 5. The last column, `import_check`, is a formula that must read `OK` (red = problem, amber = example row). Yellow italic rows are examples (SKU starts with `EXAMPLE-`) and must be deleted before import; their prices, stock and image URLs are illustrative placeholders.

Dropdowns: category, subcategory (filtered by category), brand, enums, size guide and every `product_sku` are strict. Colour and size names are free text per product in Shaotal, so those dropdowns only suggest.

## Products -> `products` (+ `product_specifications`)

| Column | Req | Maps to / rule |
|---|---|---|
| product_sku | R | `products.sku`, unique, max 120. Parent SKU used on every sheet |
| name | R | `name`, max 220 |
| slug | O | `slug`, `a-z0-9` and single hyphens, max 240; blank = generated from name |
| category | R | `category_id` (name, existing categories only) |
| subcategory | O | `subcategory_id`; must belong to the category. Two same-named ones in one category read `Name (#id)` |
| brand | O | `brand_id` (name) |
| short_description / description | O | max 500 / HTML (cleaned on save) |
| price | R | `price`, A$ >= 0 |
| selling_price | O | `selling_price`, <= price |
| has_variants | R | Yes/No -> `has_variants` |
| variant_scenario | C | Required if Yes (`color_size`, `color_only`, `size_only`); blank if No |
| stock_qty | C | Required if No; blank if Yes (DB CHECK: variant products have NULL product stock) |
| availability | R | `in_stock`, `out_of_stock`, `preorder`, `coming_soon` |
| badge | O | `new`, `launch`, `low_stock`, `preorder` -> `badge` JSON `{type,label}` |
| preorder_release_date | O | `preorder_release_date` |
| low_stock_threshold | O | `low_stock_threshold` |
| size_guide_key | O | `size_guide_id` via `size_guides.key` |
| source_url | O | `products.source_url` (max 255) |
| visibility | R | `visible` / `hidden` |
| status | R | `draft`, `active`, `inactive`, `archived`. Storefront shows only `active` + `visible` |
| seo_title / seo_description | O | max 255 / 500 |
| material, fabric, dimensions, fit, pattern, care_instructions | O | `product_specifications` (one row per product) |

## Colors -> `product_colors`

`product_sku` (R), `color_name` (R, max 80, unique per product, case-insensitive), `hex_code` (O, `#RRGGBB`; blank if unknown), `sort_order` (O). Only for `color_size` / `color_only`.

## Variants -> `product_variants` (+ `product_sizes`)

One row per sellable combination, unique on (product, colour, size).

| Column | Req | Notes |
|---|---|---|
| product_sku | R | product must have `has_variants = Yes` |
| color_name | C | required unless `size_only`; blank for `size_only`; must exist on Colors |
| size_label | C | required unless `color_only`; blank for `color_only`. Distinct labels per product become `product_sizes` |
| variant_sku | O | unique store-wide; blank = admin generates `PARENT-COLOUR-SIZE` |
| price_override, selling_price_override | O | blank = product price |
| stock_qty | R | this variant's stock (`product_variants.stock_qty`) |
| is_active | O | Yes/No, blank = Yes |

## Images -> `product_images` (+ `product_image_colors`)

`product_sku` (R), `image_url` (R, `https://res.cloudinary.com/<store cloud>/...`, the only URLs the admin accepts), `alt_text` (O), `is_main` (Yes on exactly one per product), `sort_order` (O), `color_name` (O; blank = general image for all colours), `more_color_names` (O; extra colours separated by `;`). The schema links images to **colours, not sizes or individual variants**, so one image row serves every size of a colour. Max 30 per product.

## Size guides (only for new charts) -> `size_guides`, `size_guide_attributes`, `size_guide_values`

- **Size_Guides**: `guide_key` (slug, unique, must not already exist), `title`, `customer_details`, `admin_details`, `status`.
- **Size_Guide_Attributes**: `guide_key`, `attribute_name`, `attribute_key` (slug, unique per guide), `unit` (usually cm), `sort_order`. Max 20 per guide.
- **Size_Guide_Values**: one row per size x measurement: `guide_key`, `size_label`, `attribute_key`, `measurement_value` (**text**, max 50, e.g. `38` or `38-40`). Every size needs every attribute. Max 60 sizes per guide.

Existing guides are in **Ref_Size_Guides** (read-only); just select their key on Products. The guide's sizes fill the product form's size chips, so use the same labels as `size_label` on Variants.

## Lists

Snapshot (2026-11-04) of categories, subcategories, brands, active size-guide keys, colour/size names already in use, enum values and the Cloudinary URL prefix. It feeds the dropdowns. Regenerate it when categories/brands/guides change; new ones must be created in the admin first.

## Rules the checks enforce

Unique `product_sku` and `variant_sku`; subcategory belongs to category; `selling_price <= price`; stock/scenario consistent with `has_variants`; variant colour exists on Colors; no duplicate colour/size combination; at most one main image; image URL is https and on this store's Cloudinary; every size has every size-guide attribute. Limits per product: 50 colours, 50 sizes, 500 variants, 30 images.

## Not in the template (by design)

Stock deduction/reservation, reviews, offers/coupons, `created_by/updated_by` (set by the importer from the admin user), `product_variants` ids and colour/size ids (generated on insert).

## Where the live database differs from `sql/ecom.sql`

Read from the live schema on 2026-11-04: `products.source_url VARCHAR(255)` exists (used by the form), and `size_guide_values.measurement_value` is `VARCHAR(50)`, not `DECIMAL`. The template follows the live schema.
