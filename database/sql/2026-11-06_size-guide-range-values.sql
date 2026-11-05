/* ==========================================================================================
 SIZE GUIDE RANGE VALUES                                            File date: 2026-11-06
 Target   : MySQL 8, shared Admin/Storefront database. Run MANUALLY, after a backup. Do NOT run twice concurrently.
 Purpose  : measurement ranges such as "71.1-76.2" cannot be stored in DECIMAL(10,2). Widen the column to VARCHAR(50)
            (same change as the Size Guide entry in sql/proposed_changes.sql). Skipped automatically when the column
            is already VARCHAR (nothing to do on such a database).
 Data     : existing numbers are preserved as text ("38.00" stays "38.00"; Admin/Storefront read them as 38).
            No value is converted and no unit is changed: units stay in size_guide_attributes.unit / size_guides.measurement_unit.
 Not here : existing centimetre guides are NOT converted to inch. A guide's stored unit is read from its attribute units
            (see 2026-11-05_size-guide-dual-unit.sql), and conversion only happens in the display, once.
========================================================================================== */
SET @type := (SELECT DATA_TYPE FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'size_guide_values' AND COLUMN_NAME = 'measurement_value');
SET @ddl := IF(@type = 'decimal',
  'ALTER TABLE size_guide_values MODIFY measurement_value VARCHAR(50) NOT NULL',
  'SELECT ''measurement_value is not DECIMAL - skipped'' AS info');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

/* Verification (read-only):
   SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='size_guide_values' AND COLUMN_NAME='measurement_value';
   SELECT COUNT(*), MIN(measurement_value), MAX(measurement_value) FROM size_guide_values;   -- same row count as before
   Rollback: not safe once ranges exist (they cannot fit DECIMAL). Restore from backup. */
