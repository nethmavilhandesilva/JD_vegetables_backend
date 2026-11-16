<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewSupplierBillItem extends Model
{
    protected $table = 'new_supplier_bill_items';

    protected $fillable = [
        'new_supplier_bill_id', 'item_code', 'item_name', 'packs', 'weight', 'price_per_kg', 'amount',
    ];

    protected $casts = [
        'packs'        => 'float',
        'weight'       => 'float',
        'price_per_kg' => 'float',
        'amount'       => 'float',
    ];

    public function bill()
    {
        return $this->belongsTo(NewSupplierBill::class, 'new_supplier_bill_id');
    }
}
