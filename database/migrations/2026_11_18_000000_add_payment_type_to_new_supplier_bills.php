<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * New Supplier Bills: Credit / Cash buttons on the entry screen.
 * payment_type = 'credit' (default) or 'cash'. Existing bills become 'credit'.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('new_supplier_bills') && !Schema::hasColumn('new_supplier_bills', 'payment_type')) {
            Schema::table('new_supplier_bills', function (Blueprint $t) {
                $t->string('payment_type', 10)->default('credit')->after('status')->comment('credit or cash');
                $t->index(['payment_type', 'bill_date'], 'idx_nsb_payment_date');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('new_supplier_bills') && Schema::hasColumn('new_supplier_bills', 'payment_type')) {
            Schema::table('new_supplier_bills', function (Blueprint $t) {
                $t->dropIndex('idx_nsb_payment_date');
                $t->dropColumn('payment_type');
            });
        }
    }
};
