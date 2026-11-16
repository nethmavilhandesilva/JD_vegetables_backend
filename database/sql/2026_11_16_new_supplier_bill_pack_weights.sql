-- ---------------------------------------------------------------------------
-- New Supplier Bills: pack-by-pack entry.
-- Adds `pack_weights` (TEXT, NULL) to new_supplier_bill_items: the weight of each
-- pack of a line, e.g. [12.5,13,11.75]. Existing rows are not changed.
--
-- Run AFTER 2026_11_15_new_supplier_bills.sql (phpMyAdmin -> database -> SQL tab).
-- Safe to run more than once.
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;

SET @sql := IF((SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'new_supplier_bill_items' AND COLUMN_NAME = 'pack_weights'),
  'ALTER TABLE `new_supplier_bill_items` ADD COLUMN `pack_weights` TEXT NULL DEFAULT NULL',
  'SELECT ''pack_weights already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Tell Laravel this change is done, so the deploy's migrate step skips it.
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_16_000300_add_pack_weights_to_new_supplier_bill_items',
       COALESCE((SELECT MAX(m.`batch`) FROM `migrations` m), 0) + 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_16_000300_add_pack_weights_to_new_supplier_bill_items'
);
