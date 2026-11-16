<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * New Supplier Bills, pack-by-pack entry: the price per kg of each pack of a line (JSON list).
     * Same change as database/sql/2026_11_16_new_supplier_bill_pack_prices.sql;
     * the column is only added when missing, so running both is safe.
     */
    public function up()
    {
        if (Schema::hasTable('new_supplier_bill_items') && ! Schema::hasColumn('new_supplier_bill_items', 'pack_prices')) {
            Schema::table('new_supplier_bill_items', function (Blueprint $table) {
                $table->text('pack_prices')->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('new_supplier_bill_items') && Schema::hasColumn('new_supplier_bill_items', 'pack_prices')) {
            Schema::table('new_supplier_bill_items', function (Blueprint $table) {
                $table->dropColumn('pack_prices');
            });
        }
    }
};
