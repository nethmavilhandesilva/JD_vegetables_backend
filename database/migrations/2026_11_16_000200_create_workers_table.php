<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Workers entered from Accounts -> Workers. Same table as
     * database/sql/2026_11_16_workers.sql; only created when missing, so running both is safe.
     */
    public function up()
    {
        if (Schema::hasTable('workers')) {
            return;
        }
        Schema::create('workers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('nic', 20)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('job_role', 100)->nullable();
            $table->decimal('daily_wage', 12, 2)->default(0);
            $table->date('joined_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('workers');
    }
};
