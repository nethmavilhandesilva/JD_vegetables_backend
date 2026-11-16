-- ---------------------------------------------------------------------------
-- New Supplier Bills: commission deducted from the supplier (same rules as Sales Entry).
-- Adds (DECIMAL, default 0):
--   new_supplier_bills.commission_amount        total commission of the bill
--   new_supplier_bill_items.commission_per_kg   commission per kg of the line
--   new_supplier_bill_items.commission_amount   weight x commission per kg
-- Existing bills keep commission 0.
--
-- Run AFTER 2026_11_15_new_supplier_bills.sql (phpMyAdmin -> database -> SQL tab).
-- Safe to run more than once.
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;

SET @sql := IF((SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'new_supplier_bills' AND COLUMN_NAME = 'commission_amount'),
  'ALTER TABLE `new_supplier_bills` ADD COLUMN `commission_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER `gross_amount`',
  'SELECT ''new_supplier_bills.commission_amount already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'new_supplier_bill_items' AND COLUMN_NAME = 'commission_per_kg'),
  'ALTER TABLE `new_supplier_bill_items` ADD COLUMN `commission_per_kg` DECIMAL(12,2) NOT NULL DEFAULT 0.00',
  'SELECT ''new_supplier_bill_items.commission_per_kg already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'new_supplier_bill_items' AND COLUMN_NAME = 'commission_amount'),
  'ALTER TABLE `new_supplier_bill_items` ADD COLUMN `commission_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00',
  'SELECT ''new_supplier_bill_items.commission_amount already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Tell Laravel this change is done, so the deploy's migrate step skips it.
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_16_000400_add_commission_to_new_supplier_bills',
       COALESCE((SELECT MAX(m.`batch`) FROM `migrations` m), 0) + 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_16_000400_add_commission_to_new_supplier_bills'
);
