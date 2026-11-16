-- ---------------------------------------------------------------------------
-- New Supplier Bills dashboard: creates 2 NEW tables. Existing tables are not touched.
--   new_supplier_bills       one row per bill (supplier, date, totals, payment)
--   new_supplier_bill_items  the item lines of each bill
--
-- How to import: cPanel -> phpMyAdmin -> select the database -> SQL tab
-- (or Import tab) -> run this file. Safe to run more than once.
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `new_supplier_bills` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bill_no`        VARCHAR(30)     NULL DEFAULT NULL,
  `bill_date`      DATE            NOT NULL,
  `supplier_code`  VARCHAR(50)     NOT NULL,
  `supplier_name`  VARCHAR(255)    NULL DEFAULT NULL,
  `reference_no`   VARCHAR(100)    NULL DEFAULT NULL,
  `total_packs`    DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `total_weight`   DECIMAL(12,3)   NOT NULL DEFAULT 0.000,
  `gross_amount`   DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  `deductions`     DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  `deduction_note` VARCHAR(255)    NULL DEFAULT NULL,
  `net_amount`     DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  `paid_amount`    DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  `balance_amount` DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  `status`         VARCHAR(20)     NOT NULL DEFAULT 'unpaid',
  `notes`          TEXT            NULL,
  `created_by`     BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_at`     TIMESTAMP       NULL DEFAULT NULL,
  `updated_at`     TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `new_supplier_bills_bill_no_unique` (`bill_no`),
  KEY `idx_nsb_bill_date` (`bill_date`),
  KEY `idx_nsb_supplier_date` (`supplier_code`, `bill_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `new_supplier_bill_items` (
  `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `new_supplier_bill_id` BIGINT UNSIGNED NOT NULL,
  `item_code`            VARCHAR(50)     NOT NULL,
  `item_name`            VARCHAR(255)    NULL DEFAULT NULL,
  `packs`                DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `weight`               DECIMAL(12,3)   NOT NULL DEFAULT 0.000,
  `price_per_kg`         DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `amount`               DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  `created_at`           TIMESTAMP       NULL DEFAULT NULL,
  `updated_at`           TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `new_supplier_bill_items_new_supplier_bill_id_foreign` (`new_supplier_bill_id`),
  KEY `idx_nsbi_item_code` (`item_code`),
  CONSTRAINT `new_supplier_bill_items_new_supplier_bill_id_foreign`
    FOREIGN KEY (`new_supplier_bill_id`) REFERENCES `new_supplier_bills` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tell Laravel these tables already exist, so the deploy's `php artisan migrate`
-- skips the matching migration instead of trying to create them again.
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_15_000200_create_new_supplier_bills_tables',
       COALESCE((SELECT MAX(m.`batch`) FROM `migrations` m), 0) + 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_15_000200_create_new_supplier_bills_tables'
);
