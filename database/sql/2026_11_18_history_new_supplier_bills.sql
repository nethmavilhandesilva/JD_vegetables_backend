-- ---------------------------------------------------------------------------
-- History tables for New Supplier Bills. The Day End Process moves printed bills from
-- new_supplier_bills / new_supplier_bill_items into these two tables.
-- Only needed if you create the tables by hand: the deploy's `php artisan migrate`
-- creates them too. Safe to run more than once.
-- cPanel -> phpMyAdmin -> select the database -> SQL tab -> run this file.
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `history_new_supplier_bills` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `original_id`       BIGINT UNSIGNED NOT NULL,
  `bill_no`           VARCHAR(30)     NULL DEFAULT NULL,
  `bill_date`         DATE            NOT NULL,
  `supplier_code`     VARCHAR(50)     NOT NULL,
  `supplier_name`     VARCHAR(255)    NULL DEFAULT NULL,
  `reference_no`      VARCHAR(100)    NULL DEFAULT NULL,
  `total_packs`       DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `total_weight`      DECIMAL(12,3)   NOT NULL DEFAULT 0.000,
  `gross_amount`      DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  `commission_amount` DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  `deductions`        DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  `deduction_note`    VARCHAR(255)    NULL DEFAULT NULL,
  `net_amount`        DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  `paid_amount`       DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  `balance_amount`    DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  `status`            VARCHAR(20)     NOT NULL DEFAULT 'unpaid',
  `payment_type`      VARCHAR(10)     NOT NULL DEFAULT 'credit',
  `is_printed`        TINYINT(1)      NOT NULL DEFAULT 1,
  `printed_at`        TIMESTAMP       NULL DEFAULT NULL,
  `notes`             TEXT            NULL,
  `created_by`        BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_at`        TIMESTAMP       NULL DEFAULT NULL,
  `updated_at`        TIMESTAMP       NULL DEFAULT NULL,
  `moved_at`          TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `history_new_supplier_bills_original_id_unique` (`original_id`),
  KEY `idx_hnsb_bill_no` (`bill_no`),
  KEY `idx_hnsb_bill_date` (`bill_date`),
  KEY `idx_hnsb_supplier_date` (`supplier_code`, `bill_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `history_new_supplier_bill_items` (
  `id`                           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `original_id`                  BIGINT UNSIGNED NULL DEFAULT NULL,
  `history_new_supplier_bill_id` BIGINT UNSIGNED NOT NULL,
  `new_supplier_bill_id`         BIGINT UNSIGNED NULL DEFAULT NULL,
  `item_code`                    VARCHAR(50)     NOT NULL,
  `item_name`                    VARCHAR(255)    NULL DEFAULT NULL,
  `packs`                        DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `weight`                       DECIMAL(12,3)   NOT NULL DEFAULT 0.000,
  `price_per_kg`                 DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `commission_per_kg`            DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `commission_amount`            DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  `amount`                       DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  `pack_weights`                 TEXT            NULL,
  `pack_prices`                  TEXT            NULL,
  `created_at`                   TIMESTAMP       NULL DEFAULT NULL,
  `updated_at`                   TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hnsbi_history_bill_foreign` (`history_new_supplier_bill_id`),
  KEY `idx_hnsbi_item_code` (`item_code`),
  CONSTRAINT `history_new_supplier_bill_items_history_new_supplier_bill_id_foreign`
    FOREIGN KEY (`history_new_supplier_bill_id`) REFERENCES `history_new_supplier_bills` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tell Laravel the tables exist so the deploy's migrate skips creating them again.
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_18_000100_create_history_new_supplier_bills_tables',
       COALESCE((SELECT MAX(m.`batch`) FROM `migrations` m), 0) + 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_18_000100_create_history_new_supplier_bills_tables'
);
