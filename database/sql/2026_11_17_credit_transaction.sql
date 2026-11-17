-- ---------------------------------------------------------------------------
-- Credit / Cash bills (Sales Entry buttons): credit_transaction 'Y' = credit, 'N' = cash.
-- Adds the column to `sales` and `sales_histories` only if it is missing.
-- Existing data is not changed. Run in phpMyAdmin -> database -> SQL tab.
-- Safe to run more than once.
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;

-- sales (today's records)
SET @add_sales := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `sales` ADD COLUMN `credit_transaction` CHAR(1) NULL DEFAULT NULL COMMENT ''Y = credit bill, N = cash bill''',
        'SELECT ''sales.credit_transaction already exists''')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'credit_transaction'
);
PREPARE stmt FROM @add_sales; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- sales_histories (earlier days, filled when the day is processed)
SET @add_hist := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE `sales_histories` ADD COLUMN `credit_transaction` CHAR(1) NULL DEFAULT NULL COMMENT ''Y = credit bill, N = cash bill''',
        'SELECT ''sales_histories.credit_transaction already exists''')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales_histories' AND COLUMN_NAME = 'credit_transaction'
);
PREPARE stmt FROM @add_hist; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Check: credit / cash / not set, per table
SELECT 'sales' AS tbl, credit_transaction, COUNT(*) AS records FROM `sales` GROUP BY credit_transaction
UNION ALL
SELECT 'sales_histories', credit_transaction, COUNT(*) FROM `sales_histories` GROUP BY credit_transaction;
