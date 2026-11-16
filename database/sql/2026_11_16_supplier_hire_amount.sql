-- ---------------------------------------------------------------------------
-- Supplier Bills: Hire amount.
-- Adds a `hire_amount` column (DECIMAL 12,2, default 0) to:
--   suppliers            hire to deduct on the supplier's next bill
--   supplier_bill_links  hire a printed bill was printed with (reprints / SMS link)
-- No existing data is changed.
--
-- Run in phpMyAdmin -> database -> SQL tab. Safe to run more than once.
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;

SET @sql := IF((SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND COLUMN_NAME = 'hire_amount'),
  'ALTER TABLE `suppliers` ADD COLUMN `hire_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00',
  'SELECT ''suppliers.hire_amount already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'supplier_bill_links' AND COLUMN_NAME = 'hire_amount'),
  'ALTER TABLE `supplier_bill_links` ADD COLUMN `hire_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00',
  'SELECT ''supplier_bill_links.hire_amount already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Tell Laravel this change is done, so the deploy's migrate step skips it.
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_16_000100_add_hire_amount_to_suppliers_and_bill_links',
       COALESCE((SELECT MAX(m.`batch`) FROM `migrations` m), 0) + 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_16_000100_add_hire_amount_to_suppliers_and_bill_links'
);
