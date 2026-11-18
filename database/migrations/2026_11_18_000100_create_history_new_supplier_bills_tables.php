<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * History tables for the New Supplier Bills dashboard. The day-end process moves printed
     * bills (and their item lines) from new_supplier_bills / new_supplier_bill_items into these.
     * original_id keeps the bill's id from new_supplier_bills. The same tables can be created
     * with database/sql/2026_11_18_history_new_supplier_bills.sql; both are safe to run.
     */
    public function up(): void
    {
        if (! Schema::hasTable('history_new_supplier_bills')) {
            Schema::create('history_new_supplier_bills', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('original_id')->unique();
                $table->string('bill_no', 30)->nullable();
                $table->date('bill_date');
                $table->string('supplier_code', 50);
                $table->string('supplier_name')->nullable();
                $table->string('reference_no', 100)->nullable();
                $table->decimal('total_packs', 12, 2)->default(0);
                $table->decimal('total_weight', 12, 3)->default(0);
                $table->decimal('gross_amount', 14, 2)->default(0);
                $table->decimal('commission_amount', 14, 2)->default(0);
                $table->decimal('deductions', 14, 2)->default(0);
                $table->string('deduction_note')->nullable();
                $table->decimal('net_amount', 14, 2)->default(0);
                $table->decimal('paid_amount', 14, 2)->default(0);
                $table->decimal('balance_amount', 14, 2)->default(0);
                $table->string('status', 20)->default('unpaid');
                $table->string('payment_type', 10)->default('credit');
                $table->boolean('is_printed')->default(true);
                $table->timestamp('printed_at')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->timestamp('moved_at')->nullable();

                $table->index('bill_no', 'idx_hnsb_bill_no');
                $table->index('bill_date', 'idx_hnsb_bill_date');
                $table->index(['supplier_code', 'bill_date'], 'idx_hnsb_supplier_date');
            });
        }

        if (! Schema::hasTable('history_new_supplier_bill_items')) {
            Schema::create('history_new_supplier_bill_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('original_id')->nullable();
                $table->foreignId('history_new_supplier_bill_id')
                    ->constrained('history_new_supplier_bills')
                    ->cascadeOnDelete();
                $table->unsignedBigInteger('new_supplier_bill_id')->nullable();
                $table->string('item_code', 50);
                $table->string('item_name')->nullable();
                $table->decimal('packs', 12, 2)->default(0);
                $table->decimal('weight', 12, 3)->default(0);
                $table->decimal('price_per_kg', 12, 2)->default(0);
                $table->decimal('commission_per_kg', 12, 2)->default(0);
                $table->decimal('commission_amount', 14, 2)->default(0);
                $table->decimal('amount', 14, 2)->default(0);
                $table->text('pack_weights')->nullable();
                $table->text('pack_prices')->nullable();
                $table->timestamps();

                $table->index('item_code', 'idx_hnsbi_item_code');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('history_new_supplier_bill_items');
        Schema::dropIfExists('history_new_supplier_bills');
    }
};
