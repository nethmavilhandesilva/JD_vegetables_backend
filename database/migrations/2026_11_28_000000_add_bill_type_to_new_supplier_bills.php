<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * New Supplier Bills page: Supplier bill / Customer bill modes.
 * bill_type = 'supplier' (default, every existing bill) or 'customer'. For a customer bill the
 * supplier_code / supplier_name columns hold the customer code / name.
 * Added to the history table too, so the day end keeps it.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['new_supplier_bills', 'history_new_supplier_bills'] as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'bill_type')) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    $t->string('bill_type', 10)->default('supplier')->after('bill_no')->comment('supplier or customer');
                    $t->index(['bill_type', 'bill_date'], $table === 'new_supplier_bills' ? 'idx_nsb_type_date' : 'idx_hnsb_type_date');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['new_supplier_bills' => 'idx_nsb_type_date', 'history_new_supplier_bills' => 'idx_hnsb_type_date'] as $table => $index) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'bill_type')) {
                Schema::table($table, function (Blueprint $t) use ($index) {
                    $t->dropIndex($index);
                    $t->dropColumn('bill_type');
                });
            }
        }
    }
};
