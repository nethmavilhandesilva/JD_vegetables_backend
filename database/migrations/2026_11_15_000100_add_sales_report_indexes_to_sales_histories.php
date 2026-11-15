<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The sales report filters `sales_histories` by date range, customer and item.
     * That table only grows, so add indexes for those lookups. The table was created
     * outside migrations, so each index is only added when it is missing.
     */
    private const INDEXES = [
        'idx_sales_histories_date'          => ['Date'],
        'idx_sales_histories_customer_date' => ['customer_code', 'Date'],
        'idx_sales_histories_item_date'     => ['item_code', 'Date'],
    ];

    public function up()
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('sales_histories')) {
            return;
        }

        foreach (self::INDEXES as $name => $columns) {
            if ($this->indexExists($name) || ! $this->columnsExist($columns)) {
                continue;
            }
            $cols = implode(', ', array_map(fn ($c) => "`$c`", $columns));
            DB::statement("ALTER TABLE `sales_histories` ADD INDEX `$name` ($cols)");
        }
    }

    public function down()
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('sales_histories')) {
            return;
        }

        foreach (array_keys(self::INDEXES) as $name) {
            if ($this->indexExists($name)) {
                DB::statement("ALTER TABLE `sales_histories` DROP INDEX `$name`");
            }
        }
    }

    private function indexExists(string $name): bool
    {
        return ! empty(DB::select('SHOW INDEX FROM `sales_histories` WHERE Key_name = ?', [$name]));
    }

    private function columnsExist(array $columns): bool
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn('sales_histories', $column)) {
                return false;
            }
        }
        return true;
    }
};
