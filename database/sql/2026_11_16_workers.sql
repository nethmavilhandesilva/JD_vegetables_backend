-- ---------------------------------------------------------------------------
-- Accounts -> Workers: creates 1 NEW table `workers`. Existing tables are not touched.
-- Run in phpMyAdmin -> database -> SQL tab (or Import tab). Safe to run more than once.
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `workers` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(255)    NOT NULL,
  `phone`       VARCHAR(20)     NULL DEFAULT NULL,
  `nic`         VARCHAR(20)     NULL DEFAULT NULL,
  `address`     VARCHAR(500)    NULL DEFAULT NULL,
  `job_role`    VARCHAR(100)    NULL DEFAULT NULL,
  `daily_wage`  DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `joined_date` DATE            NULL DEFAULT NULL,
  `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,
  `notes`       TEXT            NULL,
  `created_at`  TIMESTAMP       NULL DEFAULT NULL,
  `updated_at`  TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tell Laravel the table exists, so the deploy's migrate step skips it.
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_16_000200_create_workers_table',
       COALESCE((SELECT MAX(m.`batch`) FROM `migrations` m), 0) + 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_16_000200_create_workers_table'
);
