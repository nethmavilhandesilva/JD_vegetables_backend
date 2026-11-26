<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Accounts > Customers: money received against a customer's (credit) bills.
     * Same table as database/sql/2026_11_25_customer_bill_settlements.sql; only created when missing.
     */
    public function up()
    {
        if (Schema::hasTable('customer_bill_settlements')) {
            return;
        }
        Schema::create('customer_bill_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('customer_code', 50)->index();
            $table->string('bill_key', 120)->index(); // bill date + "|" + bill no
            $table->string('bill_no', 50)->nullable();
            $table->date('bill_date')->nullable();
            $table->decimal('amount', 14, 2);
            $table->date('received_date');
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('customer_bill_settlements');
    }
};
