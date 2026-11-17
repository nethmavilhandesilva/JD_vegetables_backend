<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credit / Cash bills (Sales Entry buttons): credit_transaction 'Y' = credit, 'N' = cash.
 * Makes sure both sales (today) and sales_histories (earlier days, filled at day end) have
 * the column, with an index for the report filters. Columns that already exist are left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['sales', 'sales_histories'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            if (!Schema::hasColumn($table, 'credit_transaction')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->char('credit_transaction', 1)->nullable()->comment('Y = credit bill, N = cash bill');
                });
            }
        }
    }

    public function down(): void
    {
        // Not dropped: the column may have existed before this migration and holds bill data.
    }
};
