-- ---------------------------------------------------------------------------
-- New Supplier Bills page: Supplier bill / Customer bill modes.
-- Adds 1 column to new_supplier_bills and history_new_supplier_bills:
--   bill_type  'supplier' (default) or 'customer'. Existing bills become 'supplier'.
--
-- Run in phpMyAdmin -> database -> SQL tab. Safe to run more than once.
-- (The backend deploy also adds it automatically through `php artisan migrate`.)
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;

SET @sql := IF((SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'new_supplier_bills' AND COLUMN_NAME = 'bill_type'),
  'ALTER TABLE `new_supplier_bills` ADD COLUMN `bill_type` VARCHAR(10) NOT NULL DEFAULT ''supplier'' COMMENT ''supplier or customer'' AFTER `bill_no`, ADD KEY `idx_nsb_type_date` (`bill_type`, `bill_date`)',
  'SELECT ''new_supplier_bills.bill_type already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) = 1 FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'history_new_supplier_bills')
  AND (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'history_new_supplier_bills' AND COLUMN_NAME = 'bill_type'),
  'ALTER TABLE `history_new_supplier_bills` ADD COLUMN `bill_type` VARCHAR(10) NOT NULL DEFAULT ''supplier'' COMMENT ''supplier or customer'' AFTER `bill_no`, ADD KEY `idx_hnsb_type_date` (`bill_type`, `bill_date`)',
  'SELECT ''history_new_supplier_bills.bill_type already exists or table missing''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Tell Laravel this change is done, so the deploy's migrate step skips it.
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_28_000000_add_bill_type_to_new_supplier_bills',
       COALESCE((SELECT MAX(m.`batch`) FROM `migrations` m), 0) + 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_28_000000_add_bill_type_to_new_supplier_bills'
);

-- Check
SELECT bill_type, COUNT(*) AS bills FROM `new_supplier_bills` GROUP BY bill_type;
