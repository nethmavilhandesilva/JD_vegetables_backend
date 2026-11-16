<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Worker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Accounts (bottom navigation bar):
 *  - customers / suppliers: read from the sales table (the open day's records)
 *  - workers: simple data entry, stored in the workers table
 */
class AccountsController extends Controller
{
    /** Customer line total, same as the customer bill: weight x price + bags x bag price + kuliya. */
    private const CUSTOMER_LINE_TOTAL = 'COALESCE(s.weight,0) * COALESCE(s.price_per_kg,0) + COALESCE(s.packs,0) * COALESCE(s.CustomerPackCost,0) + COALESCE(s.Kuliya,0)';

    /** Every customer code in the sales table with the customer's full name and totals. */
    public function customers()
    {
        $rows = DB::table('sales as s')
            ->leftJoin('customers as c', DB::raw('UPPER(c.short_name)'), '=', DB::raw('UPPER(s.customer_code)'))
            ->whereNotNull('s.customer_code')
            ->where('s.customer_code', '<>', '')
            ->selectRaw('UPPER(s.customer_code) AS customer_code')
            ->selectRaw('MAX(c.name) AS full_name')
            ->selectRaw('MAX(s.customer_name) AS sales_name')
            ->selectRaw('COUNT(*) AS records')
            ->selectRaw("COUNT(DISTINCT CASE WHEN s.bill_no IS NOT NULL AND s.bill_no <> '' THEN s.bill_no END) AS bills")
            ->selectRaw("SUM(CASE WHEN s.bill_printed = 'Y' THEN 0 ELSE 1 END) AS unprinted_records")
            ->selectRaw('SUM(' . self::CUSTOMER_LINE_TOTAL . ') AS total')
            ->groupBy(DB::raw('UPPER(s.customer_code)'))
            ->orderBy('customer_code')
            ->get()
            ->map(fn ($r) => [
                'customer_code' => $r->customer_code,
                // Full name from the customers table; the name typed on the sale otherwise
                'full_name' => $r->full_name ?: ($r->sales_name && strtoupper($r->sales_name) !== $r->customer_code ? $r->sales_name : null),
                'records' => (int) $r->records,
                'bills' => (int) $r->bills,
                'unprinted_records' => (int) $r->unprinted_records,
                'total' => round((float) $r->total, 2),
            ]);

        return response()->json(['customers' => $rows]);
    }

    /** All sales lines of one customer, for the bill details view (grouped into bills on screen). */
    public function customerBills(string $code)
    {
        $records = Sale::query()
            ->whereRaw('UPPER(customer_code) = ?', [strtoupper($code)])
            ->orderBy('bill_no')
            ->orderBy('id')
            ->get([
                'id', 'customer_code', 'customer_name', 'supplier_code', 'item_code', 'item_name', 'weight',
                'price_per_kg', 'packs', 'CustomerPackCost', 'Kuliya', 'total', 'bill_no', 'bill_printed',
                'credit_transaction', 'given_amount', 'Date', 'created_at',
            ]);

        $customer = DB::table('customers')->whereRaw('UPPER(short_name) = ?', [strtoupper($code)])
            ->first(['short_name', 'name', 'telephone_no', 'address']);

        return response()->json(['customer' => $customer, 'records' => $records]);
    }

    /** Every supplier code in the sales table with the supplier's name and totals. */
    public function suppliers()
    {
        $rows = DB::table('sales as s')
            ->leftJoin('suppliers as p', DB::raw('UPPER(p.code)'), '=', DB::raw('UPPER(s.supplier_code)'))
            ->whereNotNull('s.supplier_code')
            ->where('s.supplier_code', '<>', '')
            ->selectRaw('UPPER(s.supplier_code) AS supplier_code')
            ->selectRaw('MAX(p.name) AS full_name')
            ->selectRaw('COUNT(*) AS records')
            ->selectRaw("COUNT(DISTINCT CASE WHEN s.supplier_bill_no IS NOT NULL AND s.supplier_bill_no <> '' THEN s.supplier_bill_no END) AS bills")
            ->selectRaw('SUM(COALESCE(s.SupplierTotal,0)) AS total')
            ->groupBy(DB::raw('UPPER(s.supplier_code)'))
            ->orderBy('supplier_code')
            ->get()
            ->map(fn ($r) => [
                'supplier_code' => $r->supplier_code,
                'full_name' => $r->full_name && strtoupper($r->full_name) !== $r->supplier_code ? $r->full_name : null,
                'records' => (int) $r->records,
                'bills' => (int) $r->bills,
                'total' => round((float) $r->total, 2),
            ]);

        return response()->json(['suppliers' => $rows]);
    }

    /** One supplier's bills: each supplier bill number with its total (not-yet-printed lines as one group). */
    public function supplierBills(string $code)
    {
        $records = Sale::query()
            ->whereRaw('UPPER(supplier_code) = ?', [strtoupper($code)])
            ->orderBy('id')
            ->get([
                'id', 'supplier_code', 'customer_code', 'item_code', 'item_name', 'weight', 'SupplierWeight',
                'SupplierPricePerKg', 'SupplierTotal', 'packs', 'supplier_bill_no', 'supplier_bill_printed', 'Date', 'created_at',
            ]);

        $bills = $records->groupBy(fn ($r) => $r->supplier_bill_no ?: '')
            ->map(function ($lines, $billNo) {
                return [
                    'bill_no' => $billNo !== '' ? $billNo : null,
                    'printed' => $billNo !== '' && $lines->contains(fn ($l) => $l->supplier_bill_printed === 'Y'),
                    'records' => $lines->count(),
                    'packs' => (int) $lines->sum('packs'),
                    'weight' => round((float) $lines->sum(fn ($l) => (float) ($l->SupplierWeight ?? $l->weight)), 3),
                    'total' => round((float) $lines->sum('SupplierTotal'), 2),
                    'lines' => $lines->values(),
                ];
            })
            ->sortBy(fn ($b) => $b['bill_no'] === null ? PHP_INT_MAX : (int) preg_replace('/\D/', '', $b['bill_no']))
            ->values();

        $supplier = DB::table('suppliers')->whereRaw('UPPER(code) = ?', [strtoupper($code)])
            ->first(['code', 'name', 'telephone_no']);

        return response()->json(['supplier' => $supplier, 'bills' => $bills]);
    }

    // ---------------- Workers ----------------

    public function workers()
    {
        return response()->json(['workers' => Worker::orderByDesc('is_active')->orderBy('name')->get()]);
    }

    public function storeWorker(Request $request)
    {
        $worker = Worker::create($this->validatedWorker($request));
        return response()->json($worker, 201);
    }

    public function updateWorker(Request $request, Worker $worker)
    {
        $worker->update($this->validatedWorker($request));
        return response()->json($worker->fresh());
    }

    public function destroyWorker(Worker $worker)
    {
        $worker->delete();
        return response()->json(['message' => 'Worker deleted']);
    }

    private function validatedWorker(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'nic' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'job_role' => 'nullable|string|max:100',
            'daily_wage' => 'nullable|numeric|min:0',
            'joined_date' => 'nullable|date_format:Y-m-d',
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string|max:2000',
        ]);
        $data['daily_wage'] = round((float) ($data['daily_wage'] ?? 0), 2);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        return $data;
    }
}
