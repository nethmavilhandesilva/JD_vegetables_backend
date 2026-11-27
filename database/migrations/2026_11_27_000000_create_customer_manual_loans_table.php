<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Accounts > Customers: loans typed in by hand (not from a sales bill). Each loan is shown and
     * settled like a bill; its settlements use bill_key "loan|{id}".
     * Same table as database/sql/2026_11_27_customer_manual_loans.sql; only created when missing.
     */
    public function up()
    {
        if (Schema::hasTable('customer_manual_loans')) {
            return;
        }
        Schema::create('customer_manual_loans', function (Blueprint $table) {
            $table->id();
            $table->string('customer_code', 50)->index();
            $table->date('loan_date');
            $table->string('ref_no', 50)->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('customer_manual_loans');
    }
};
