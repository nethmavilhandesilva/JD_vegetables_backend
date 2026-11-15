<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SupplierWeight was only set when a sale was created and was not kept up to
     * date when the sale's weight was edited later, so some rows hold an old value.
     * Nothing read it until now (supplier amounts used `weight`), and it could not
     * be edited by hand, so any difference from `weight` is stale data.
     *
     * The supplier bill now reads SupplierWeight (it can be edited from the
     * supplier report), so bring every row in line with `weight` once.
     */
    public function up()
    {
        if (! Schema::hasTable('sales') || ! Schema::hasColumn('sales', 'SupplierWeight')) {
            return;
        }

        DB::table('sales')
            ->where(function ($query) {
                $query->whereNull('SupplierWeight')
                    ->orWhereColumn('SupplierWeight', '<>', 'weight');
            })
            ->update(['SupplierWeight' => DB::raw('weight')]);
    }

    public function down()
    {
        // Nothing to undo: the old values were stale.
    }
};
