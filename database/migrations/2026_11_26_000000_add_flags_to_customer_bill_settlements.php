<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Customer settlements: closes_bill (bill settled with less than its total), from_credit
     * (paid from the customer's received amount) and bill_key may be empty (a received amount
     * not given to a bill yet). Same change as database/sql/2026_11_26_customer_bill_settlements_flags.sql.
     */
    public function up()
    {
        if (!Schema::hasTable('customer_bill_settlements')) {
            return;
        }
        Schema::table('customer_bill_settlements', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_bill_settlements', 'closes_bill')) {
                $table->boolean('closes_bill')->default(false);
            }
            if (!Schema::hasColumn('customer_bill_settlements', 'from_credit')) {
                $table->boolean('from_credit')->default(false);
            }
        });
        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            DB::statement('ALTER TABLE `customer_bill_settlements` MODIFY `bill_key` VARCHAR(120) NULL DEFAULT NULL');
        } else {
            Schema::table('customer_bill_settlements', function (Blueprint $table) {
                $table->string('bill_key', 120)->nullable()->change();
            });
        }
    }

    public function down()
    {
        // Columns are kept: they hold settlement data.
    }
};
