-- ---------------------------------------------------------------------------
-- New Supplier Bills: Credit / Cash.
-- Adds 1 column to new_supplier_bills:
--   payment_type  'credit' (default) or 'cash'. Existing bills become 'credit'.
--
-- Run in phpMyAdmin -> database -> SQL tab. Safe to run more than once.
-- (The backend deploy also adds it automatically through `php artisan migrate`.)
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;

SET @sql := IF((SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'new_supplier_bills' AND COLUMN_NAME = 'payment_type'),
  'ALTER TABLE `new_supplier_bills` ADD COLUMN `payment_type` VARCHAR(10) NOT NULL DEFAULT ''credit'' COMMENT ''credit or cash'' AFTER `status`, ADD KEY `idx_nsb_payment_date` (`payment_type`, `bill_date`)',
  'SELECT ''payment_type already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Tell Laravel this change is done, so the deploy's migrate step skips it.
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_18_000000_add_payment_type_to_new_supplier_bills',
       COALESCE((SELECT MAX(m.`batch`) FROM `migrations` m), 0) + 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_18_000000_add_payment_type_to_new_supplier_bills'
);

-- Check
SELECT payment_type, COUNT(*) AS bills, SUM(net_amount) AS net FROM `new_supplier_bills` GROUP BY payment_type;
