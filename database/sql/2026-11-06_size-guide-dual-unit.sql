/* ==========================================================================================
 SIZE GUIDE DUAL UNIT (INCH + CM)                                   File date: 2026-11-06
 Target   : MySQL 8, database shared by the Admin and the Storefront (hishabiz_ecom).
 Run      : MANUALLY, once, after a backup (see SIZE_GUIDE_RELEASE_CHECKLIST.md), BEFORE deploying code.
 Safe     : no DROP / DELETE / TRUNCATE; no measurement_value is changed; re-runnable (idempotent).
 Model    : size_guide_values.measurement_value stays the single stored number. The NEW column
            size_guides.measurement_unit says which unit that number is expressed in ('in' or 'cm').
            The Storefront converts for display only (1 in = 2.54 cm) and never writes back.
========================================================================================== */

/* ---- 1. Add size_guides.measurement_unit (skipped if it already exists) ------------------ */
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'size_guides' AND COLUMN_NAME = 'measurement_unit');
SET @ddl := IF(@has_col = 0,
  "ALTER TABLE size_guides ADD COLUMN measurement_unit ENUM('in','cm') NOT NULL DEFAULT 'in' AFTER customer_details",
  'SELECT ''measurement_unit already exists - skipped'' AS info');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
/* New guides default to Inch. Existing rows get 'in' from the DEFAULT, which is WRONG for guides whose numbers
   are centimetres (size_guide_attributes.unit defaulted to 'cm') - step 2 corrects them. */

/* ---- 2. Backfill: keep every existing guide's numbers meaning exactly what they meant before -------------
   Existing unit lives per attribute in size_guide_attributes.unit (free text). Guides whose attributes are
   ALL centimetre spellings -> 'cm'; ALL inch spellings -> 'in'. Mixed / unknown / attribute-less guides are
   left at the default and listed by verification query V3 for manual review (no guessing, no conversion).
   Re-running is harmless until admins start saving Inch guides: afterwards Admin keeps attribute units equal to the
   guide unit, so the same answer is derived again. */
UPDATE size_guides g
   SET g.measurement_unit = 'cm'
 WHERE EXISTS (SELECT 1 FROM size_guide_attributes a WHERE a.size_guide_id = g.id)
   AND NOT EXISTS (SELECT 1 FROM size_guide_attributes a WHERE a.size_guide_id = g.id
                      AND LOWER(TRIM(a.unit)) NOT IN ('cm','centimeter','centimeters','centimetre','centimetres'));

UPDATE size_guides g
   SET g.measurement_unit = 'in'
 WHERE EXISTS (SELECT 1 FROM size_guide_attributes a WHERE a.size_guide_id = g.id)
   AND NOT EXISTS (SELECT 1 FROM size_guide_attributes a WHERE a.size_guide_id = g.id
                      AND LOWER(TRIM(a.unit)) NOT IN ('in','inch','inches','"'));

/* ---- 3. Normalise attribute unit labels to the guide unit (labels only, numbers untouched) ---------------
   Admin now keeps size_guide_attributes.unit equal to the guide unit; older Storefront code still reads it. */
UPDATE size_guide_attributes a
  JOIN size_guides g ON g.id = a.size_guide_id
   SET a.unit = g.measurement_unit
 WHERE LOWER(TRIM(a.unit)) IN ('cm','centimeter','centimeters','centimetre','centimetres','in','inch','inches','"')
   AND a.unit <> g.measurement_unit;

/* ---- Verification (read-only) ----------------------------------------------------------------------------
-- V1 columns exist:        SHOW COLUMNS FROM size_guides LIKE 'measurement_unit';
-- V2 unit distribution:    SELECT measurement_unit, COUNT(*) FROM size_guides GROUP BY measurement_unit;
-- V3 needs manual review (mixed/unknown attribute units, or no attributes):
SELECT g.id, g.`key`, g.measurement_unit, GROUP_CONCAT(DISTINCT a.unit) AS attribute_units
  FROM size_guides g LEFT JOIN size_guide_attributes a ON a.size_guide_id = g.id
 GROUP BY g.id, g.`key`, g.measurement_unit
HAVING attribute_units IS NULL OR COUNT(DISTINCT a.unit) > 1 OR attribute_units NOT IN ('in','cm');
-- V4 product links intact: SELECT COUNT(*) FROM products WHERE size_guide_id IS NOT NULL;  (compare with the pre-run count)
-- V5 values unchanged:     SELECT COUNT(*), SUM(measurement_value) FROM size_guide_values;  (compare with the pre-run result)
   Rollback (only if no guide was saved in Inch yet; otherwise Inch-based guides would be read as cm):
-- ALTER TABLE size_guides DROP COLUMN measurement_unit;   -- attribute unit labels were already equal in meaning
========================================================================================== */
