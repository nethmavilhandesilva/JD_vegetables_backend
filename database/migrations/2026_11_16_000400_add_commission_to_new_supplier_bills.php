<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Commission deducted on New Supplier Bills (same rules as Sales Entry).
     *   new_supplier_bills.commission_amount        total commission of the bill
     *   new_supplier_bill_items.commission_per_kg   commission per kg of the line
     *   new_supplier_bill_items.commission_amount   weight x commission per kg
     * Same change as database/sql/2026_11_16_new_supplier_bill_commission.sql; columns are
     * only added when missing, so running both is safe.
     */
    public function up()
    {
        $add = [
            'new_supplier_bills' => ['commission_amount' => 14],
            'new_supplier_bill_items' => ['commission_per_kg' => 12, 'commission_amount' => 14],
        ];
        foreach ($add as $tableName => $columns) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            foreach ($columns as $column => $precision) {
                if (! Schema::hasColumn($tableName, $column)) {
                    Schema::table($tableName, function (Blueprint $table) use ($column, $precision) {
                        $table->decimal($column, $precision, 2)->default(0);
                    });
                }
            }
        }
    }

    public function down()
    {
        foreach (['new_supplier_bills' => ['commission_amount'], 'new_supplier_bill_items' => ['commission_per_kg', 'commission_amount']] as $tableName => $columns) {
            foreach ($columns as $column) {
                if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, $column)) {
                    Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn($column));
                }
            }
        }
    }
};
