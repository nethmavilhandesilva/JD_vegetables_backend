<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewSupplierBillItem extends Model
{
    protected $table = 'new_supplier_bill_items';

    protected $fillable = [
        'new_supplier_bill_id', 'item_code', 'item_name', 'packs', 'weight', 'price_per_kg', 'amount', 'pack_weights',
        'commission_per_kg', 'commission_amount', 'pack_prices',
    ];

    protected $casts = [
        'packs'        => 'float',
        'weight'       => 'float',
        'price_per_kg' => 'float',
        'amount'       => 'float',
        'pack_weights' => 'array', // weight of each pack, when entered pack by pack
        'commission_per_kg' => 'float',
        'commission_amount' => 'float',
        'pack_prices'  => 'array', // price per kg of each pack (same order as pack_weights)
    ];

    public function bill()
    {
        return $this->belongsTo(NewSupplierBill::class, 'new_supplier_bill_id');
    }
}
