-- ---------------------------------------------------------------------------
-- Accounts > Customers: loans typed in by hand (not from a sales bill).
-- Creates `customer_manual_loans`. Existing tables are not changed.
--
-- Run in phpMyAdmin -> database -> SQL tab. Safe to run more than once.
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `customer_manual_loans` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_code` VARCHAR(50) NOT NULL,
  `loan_date` DATE NOT NULL,
  `ref_no` VARCHAR(50) NULL DEFAULT NULL,
  `amount` DECIMAL(14,2) NOT NULL,
  `note` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_manual_loans_customer_code_index` (`customer_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tell Laravel this change is done, so the deploy's migrate step skips it.
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_27_000000_create_customer_manual_loans_table',
       COALESCE((SELECT MAX(m.`batch`) FROM `migrations` m), 0) + 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_27_000000_create_customer_manual_loans_table'
);
