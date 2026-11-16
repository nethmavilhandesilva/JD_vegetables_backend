<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hold (F5) / Print (F4) on the New Supplier Bills dashboard.
     * is_printed = 0 means the bill is held and still waiting to be printed.
     * Same change as database/sql/2026_11_16_new_supplier_bills_print_status.sql;
     * columns are only added when missing, so running both is safe.
     */
    public function up()
    {
        if (! Schema::hasTable('new_supplier_bills')) {
            return;
        }

        if (! Schema::hasColumn('new_supplier_bills', 'is_printed')) {
            Schema::table('new_supplier_bills', function (Blueprint $table) {
                $table->boolean('is_printed')->default(false)->after('status');
                $table->index(['is_printed', 'bill_date'], 'idx_nsb_printed_date');
            });
            // Bills saved before hold/print existed were final, so treat them as printed.
            DB::table('new_supplier_bills')->update(['is_printed' => true]);
        }

        if (! Schema::hasColumn('new_supplier_bills', 'printed_at')) {
            Schema::table('new_supplier_bills', function (Blueprint $table) {
                $table->timestamp('printed_at')->nullable()->after('is_printed');
            });
        }
    }

    public function down()
    {
        if (! Schema::hasTable('new_supplier_bills')) {
            return;
        }
        if (Schema::hasColumn('new_supplier_bills', 'is_printed')) {
            Schema::table('new_supplier_bills', function (Blueprint $table) {
                $table->dropIndex('idx_nsb_printed_date');
                $table->dropColumn('is_printed');
            });
        }
        if (Schema::hasColumn('new_supplier_bills', 'printed_at')) {
            Schema::table('new_supplier_bills', function (Blueprint $table) {
                $table->dropColumn('printed_at');
            });
        }
    }
};
