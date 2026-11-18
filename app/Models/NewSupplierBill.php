<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A supplier bill entered from the "New Supplier Bills" dashboard.
 * Stored separately from the sales-based supplier bills.
 */
class NewSupplierBill extends Model
{
    protected $table = 'new_supplier_bills';

    protected $fillable = [
        'bill_no', 'bill_date', 'supplier_code', 'supplier_name', 'reference_no',
        'total_packs', 'total_weight', 'gross_amount', 'commission_amount', 'deductions', 'deduction_note',
        'net_amount', 'paid_amount', 'balance_amount', 'status', 'payment_type', 'is_printed', 'printed_at',
        'notes', 'created_by',
    ];

    protected $casts = [
        'bill_date'      => 'date:Y-m-d',
        'total_packs'    => 'float',
        'total_weight'   => 'float',
        'gross_amount'   => 'float',
        'commission_amount' => 'float',
        'deductions'     => 'float',
        'net_amount'     => 'float',
        'paid_amount'    => 'float',
        'balance_amount' => 'float',
        'is_printed'     => 'boolean',
        'printed_at'     => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(NewSupplierBillItem::class, 'new_supplier_bill_id')->orderBy('id');
    }
}
