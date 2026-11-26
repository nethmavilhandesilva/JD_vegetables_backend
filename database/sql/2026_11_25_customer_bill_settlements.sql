-- ---------------------------------------------------------------------------
-- Accounts > Customers: bill settlements (money received against credit bills).
-- Creates `customer_bill_settlements`. Existing tables are not changed.
--
-- Run in phpMyAdmin -> database -> SQL tab. Safe to run more than once.
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `customer_bill_settlements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_code` VARCHAR(50) NOT NULL,
  `bill_key` VARCHAR(120) NOT NULL,
  `bill_no` VARCHAR(50) NULL DEFAULT NULL,
  `bill_date` DATE NULL DEFAULT NULL,
  `amount` DECIMAL(14,2) NOT NULL,
  `received_date` DATE NOT NULL,
  `note` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_bill_settlements_customer_code_index` (`customer_code`),
  KEY `customer_bill_settlements_bill_key_index` (`bill_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tell Laravel this change is done, so the deploy's migrate step skips it.
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_25_000000_create_customer_bill_settlements_table',
       COALESCE((SELECT MAX(m.`batch`) FROM `migrations` m), 0) + 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_25_000000_create_customer_bill_settlements_table'
);
