-- ---------------------------------------------------------------------------
-- New Supplier Bills: Hold (F5) / Print (F4) support.
-- Adds 2 columns to new_supplier_bills:
--   is_printed  0 = held (waiting to print), 1 = printed
--   printed_at  when the bill was printed
--
-- Run AFTER 2026_11_15_new_supplier_bills.sql (phpMyAdmin -> database -> SQL tab).
-- Safe to run more than once: each change is skipped if it is already there.
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;

-- is_printed (existing bills were final, so they are marked as printed)
SET @col_missing := (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'new_supplier_bills' AND COLUMN_NAME = 'is_printed');
SET @sql := IF(@col_missing,
  'ALTER TABLE `new_supplier_bills` ADD COLUMN `is_printed` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`, ADD KEY `idx_nsb_printed_date` (`is_printed`, `bill_date`)',
  'SELECT ''is_printed already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@col_missing, 'UPDATE `new_supplier_bills` SET `is_printed` = 1', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- printed_at
SET @sql := IF((SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'new_supplier_bills' AND COLUMN_NAME = 'printed_at'),
  'ALTER TABLE `new_supplier_bills` ADD COLUMN `printed_at` TIMESTAMP NULL DEFAULT NULL AFTER `is_printed`',
  'SELECT ''printed_at already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Tell Laravel this change is done, so the deploy's migrate step skips it.
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_16_000000_add_print_status_to_new_supplier_bills',
       COALESCE((SELECT MAX(m.`batch`) FROM `migrations` m), 0) + 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_16_000000_add_print_status_to_new_supplier_bills'
);
