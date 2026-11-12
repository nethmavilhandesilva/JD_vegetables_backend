<?php

use App\Support\DefaultCommissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace every general commission band (no item or supplier code) with the
     * agreed schedule: Rs. 1 at 0-30, Rs. 2 at 31-49, Rs. 3 at 50-99, Rs. 5 at
     * 100-199, Rs. 10 at 200-299, then Rs. 5 more for every further Rs. 100 up to
     * 1900 and above = Rs. 95.
     *
     * The sales lookup takes these bands from App\Support\DefaultCommissions; the
     * table copy keeps the commissions screen in step with what is charged.
     * Item/supplier specific rules are left untouched.
     */
    public function up()
    {
        $this->replaceGeneralBands(DefaultCommissions::BANDS);
    }

    public function down()
    {
        // The general bands in place before this migration
        $this->replaceGeneralBands([
            [1.00,   9.00,   0.50],
            [10.00,  19.00,  1.00],
            [20.00,  29.00,  1.50],
            [30.00,  44.00,  2.00],
            [45.00,  59.00,  2.50],
            [60.00,  79.00,  3.00],
            [80.00,  99.00,  4.00],
            [100.00, 124.00, 5.00],
            [125.00, 149.00, 6.00],
            [150.00, 199.00, 7.50],
            [200.00, 249.00, 10.00],
            [250.00, 299.00, 12.50],
            [300.00, 399.00, 5.00],
            [400.00, 499.00, 10.00],
            [500.00, 599.00, 15.00],
            [600.00, 699.00, 20.00],
            [700.00, 799.00, 25.00],
            [800.00, 899.00, 30.00],
            [900.00, 999.00, 35.00],
            [1000.00, null,  50.00],
        ]);
    }

    private function replaceGeneralBands(array $bands)
    {
        if (! Schema::hasTable('commissions')) {
            return;
        }

        DB::transaction(function () use ($bands) {
            DB::table('commissions')
                ->where(fn ($q) => $q->whereNull('item_code')->orWhere('item_code', ''))
                ->where(fn ($q) => $q->whereNull('supplier_code')->orWhere('supplier_code', ''))
                ->delete();

            $now = now();
            DB::table('commissions')->insert(array_map(fn ($band) => [
                'item_code' => null,
                'item_name' => null,
                'supplier_code' => null,
                'supplier_name' => null,
                'starting_price' => $band[0],
                'end_price' => $band[1],
                'commission_amount' => $band[2],
                'type' => 'Z',
                'created_at' => $now,
                'updated_at' => $now,
            ], $bands));
        });
    }
};
