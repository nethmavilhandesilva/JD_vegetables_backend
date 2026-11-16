<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables for the "New Supplier Bills" dashboard. The same tables can be created
     * by importing database/sql/2026_11_15_new_supplier_bills.sql; each table is only
     * created when it does not exist yet, so running both is safe.
     */
    public function up()
    {
        if (! Schema::hasTable('new_supplier_bills')) {
            Schema::create('new_supplier_bills', function (Blueprint $table) {
                $table->id();
                $table->string('bill_no', 30)->nullable()->unique();
                $table->date('bill_date');
                $table->string('supplier_code', 50);
                $table->string('supplier_name')->nullable();
                $table->string('reference_no', 100)->nullable();
                $table->decimal('total_packs', 12, 2)->default(0);
                $table->decimal('total_weight', 12, 3)->default(0);
                $table->decimal('gross_amount', 14, 2)->default(0);
                $table->decimal('deductions', 14, 2)->default(0);
                $table->string('deduction_note')->nullable();
                $table->decimal('net_amount', 14, 2)->default(0);
                $table->decimal('paid_amount', 14, 2)->default(0);
                $table->decimal('balance_amount', 14, 2)->default(0);
                $table->string('status', 20)->default('unpaid');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index('bill_date', 'idx_nsb_bill_date');
                $table->index(['supplier_code', 'bill_date'], 'idx_nsb_supplier_date');
            });
        }

        if (! Schema::hasTable('new_supplier_bill_items')) {
            Schema::create('new_supplier_bill_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('new_supplier_bill_id')
                    ->constrained('new_supplier_bills')
                    ->cascadeOnDelete();
                $table->string('item_code', 50);
                $table->string('item_name')->nullable();
                $table->decimal('packs', 12, 2)->default(0);
                $table->decimal('weight', 12, 3)->default(0);
                $table->decimal('price_per_kg', 12, 2)->default(0);
                $table->decimal('amount', 14, 2)->default(0);
                $table->timestamps();

                $table->index('item_code', 'idx_nsbi_item_code');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('new_supplier_bill_items');
        Schema::dropIfExists('new_supplier_bills');
    }
};
