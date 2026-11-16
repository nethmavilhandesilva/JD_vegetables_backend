<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hire (transport) amount on supplier bills.
     *   suppliers.hire_amount           hire to deduct on the supplier's next bill
     *   supplier_bill_links.hire_amount hire a printed bill was printed with
     * Same change as database/sql/2026_11_16_supplier_hire_amount.sql; columns are only
     * added when missing, so running both is safe.
     */
    public function up()
    {
        foreach (['suppliers', 'supplier_bill_links'] as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'hire_amount')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->decimal('hire_amount', 12, 2)->default(0);
                });
            }
        }
    }

    public function down()
    {
        foreach (['suppliers', 'supplier_bill_links'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'hire_amount')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('hire_amount');
                });
            }
        }
    }
};
