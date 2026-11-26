-- ---------------------------------------------------------------------------
-- Accounts > Customers settlements, second step:
--  * closes_bill  : the bill was settled with less than its total ("fully settle")
--  * from_credit  : paid from the customer's received amount
--  * bill_key NULL: a received amount entered without a bill (used later when settling)
--
-- Run AFTER 2026_11_25_customer_bill_settlements.sql (phpMyAdmin -> database -> SQL tab).
-- Safe to run more than once.
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;

SET @sql := IF((SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_bill_settlements' AND COLUMN_NAME = 'closes_bill'),
  'ALTER TABLE `customer_bill_settlements` ADD COLUMN `closes_bill` TINYINT(1) NOT NULL DEFAULT 0',
  'SELECT ''closes_bill already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer_bill_settlements' AND COLUMN_NAME = 'from_credit'),
  'ALTER TABLE `customer_bill_settlements` ADD COLUMN `from_credit` TINYINT(1) NOT NULL DEFAULT 0',
  'SELECT ''from_credit already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE `customer_bill_settlements` MODIFY `bill_key` VARCHAR(120) NULL DEFAULT NULL;

-- Tell Laravel this change is done, so the deploy's migrate step skips it.
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_26_000000_add_flags_to_customer_bill_settlements',
       COALESCE((SELECT MAX(m.`batch`) FROM `migrations` m), 0) + 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_26_000000_add_flags_to_customer_bill_settlements'
);
