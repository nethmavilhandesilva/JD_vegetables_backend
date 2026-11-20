<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Worker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * Accounts (bottom navigation bar):
 *  - customers / suppliers: read from the sales table (today) and sales_histories (earlier days)
 *  - workers: simple data entry, stored in the workers table
 */
class AccountsController extends Controller
{
    /** Today's records (sales) and earlier days (sales_histories): both are read. */
    private const TABLES = ['sales' => 'today', 'sales_histories' => 'history'];

    /** Column expression, or a fallback when an older table does not have that column. */
    private function col(string $table, string $column, string $fallback = '0'): string
    {
        static $columns = [];
        if (!isset($columns[$table])) {
            $columns[$table] = Schema::hasTable($table)
                ? array_map('strtolower', Schema::getColumnListing($table))
                : [];
        }
        return in_array(strtolower($column), $columns[$table], true) ? "s.`{$column}`" : $fallback;
    }

    private function tableExists(string $table): bool
    {
        return Schema::hasTable($table);
    }

    /** Customer line total, same as the customer bill: weight x price + bags x bag price + kuliya. */
    private function customerLineTotal(string $table): string
    {
        return 'COALESCE(s.weight,0) * COALESCE(s.price_per_kg,0) + COALESCE(s.packs,0) * COALESCE('
            . $this->col($table, 'CustomerPackCost') . ',0) + COALESCE(' . $this->col($table, 'Kuliya') . ',0)';
    }

    /** Every customer code in sales and sales history, with the full name and totals of both. */
    public function customers()
    {
        $merged = [];
        foreach (self::TABLES as $table => $source) {
            if (!$this->tableExists($table)) {
                continue;
            }
            $rows = DB::table("{$table} as s")
                ->whereNotNull('s.customer_code')
                ->where('s.customer_code', '<>', '')
                ->selectRaw('UPPER(TRIM(s.customer_code)) AS customer_code')
                ->selectRaw('MAX(' . $this->col($table, 'customer_name', 'NULL') . ') AS sales_name')
                ->selectRaw('COUNT(*) AS records')
                // Bill numbers can repeat on different days, so count date + bill no
                ->selectRaw("COUNT(DISTINCT CASE WHEN s.bill_no IS NOT NULL AND s.bill_no <> '' THEN CONCAT(COALESCE(" . $this->col($table, 'Date', "''") . ",''), '|', s.bill_no) END) AS bills")
                ->selectRaw("SUM(CASE WHEN s.bill_printed = 'Y' THEN 0 ELSE 1 END) AS unprinted_records")
                ->selectRaw('SUM(' . $this->customerLineTotal($table) . ') AS total')
                ->selectRaw('MAX(' . $this->col($table, 'Date', 'NULL') . ') AS last_date')
                ->groupBy(DB::raw('UPPER(TRIM(s.customer_code))'))
                ->get();

            foreach ($rows as $r) {
                $m = $merged[$r->customer_code] ?? [
                    'customer_code' => $r->customer_code, 'sales_name' => null, 'records' => 0, 'bills' => 0,
                    'unprinted_records' => 0, 'total' => 0.0, 'last_date' => null, 'in_today' => false,
                ];
                $m['sales_name'] = $m['sales_name'] ?: $r->sales_name;
                $m['records'] += (int) $r->records;
                $m['bills'] += (int) $r->bills;
                // Only today's records can still be waiting to print
                if ($source === 'today') {
                    $m['unprinted_records'] += (int) $r->unprinted_records;
                    $m['in_today'] = true;
                }
                $m['total'] += (float) $r->total;
                if ($r->last_date && (!$m['last_date'] || $r->last_date > $m['last_date'])) {
                    $m['last_date'] = $r->last_date;
                }
                $merged[$r->customer_code] = $m;
            }
        }

        $names = DB::table('customers')->get(['short_name', 'name'])
            ->mapWithKeys(fn ($c) => [strtoupper(trim((string) $c->short_name)) => $c->name]);

        $rows = collect($merged)->sortKeys()->values()->map(fn ($m) => [
            'customer_code' => $m['customer_code'],
            // Full name from the customers table; the name typed on the sale otherwise
            'full_name' => $names[$m['customer_code']] ?? (
                $m['sales_name'] && strtoupper($m['sales_name']) !== $m['customer_code'] ? $m['sales_name'] : null
            ),
            'records' => $m['records'],
            'bills' => $m['bills'],
            'unprinted_records' => $m['unprinted_records'],
            'total' => round($m['total'], 2),
            'last_date' => $m['last_date'],
            'in_today' => $m['in_today'],
        ]);

        return response()->json(['customers' => $rows]);
    }

    /** All sales lines of one customer from sales and sales history (grouped into bills on screen). */
    public function customerBills(string $code)
    {
        $records = collect();
        foreach (self::TABLES as $table => $source) {
            if (!$this->tableExists($table)) {
                continue;
            }
            $rows = DB::table("{$table} as s")
                ->whereRaw('UPPER(TRIM(s.customer_code)) = ?', [strtoupper(trim($code))])
                ->selectRaw("s.id, s.customer_code, s.supplier_code, s.item_code, s.item_name, s.weight, s.price_per_kg, s.packs, s.total, s.bill_no, s.bill_printed")
                ->selectRaw($this->col($table, 'customer_name', 'NULL') . ' AS customer_name')
                ->selectRaw($this->col($table, 'CustomerPackCost') . ' AS CustomerPackCost')
                ->selectRaw($this->col($table, 'Kuliya') . ' AS Kuliya')
                ->selectRaw($this->col($table, 'credit_transaction', 'NULL') . ' AS credit_transaction')
                ->selectRaw($this->col($table, 'given_amount', 'NULL') . ' AS given_amount')
                ->selectRaw($this->col($table, 'Date', 'NULL') . ' AS Date')
                ->selectRaw($this->col($table, 'created_at', 'NULL') . ' AS created_at')
                ->selectRaw('? AS source', [$source])
                ->orderBy('s.id')
                ->get();
            $records = $records->concat($rows);
        }

        $customer = DB::table('customers')->whereRaw('UPPER(short_name) = ?', [strtoupper($code)])
            ->first(['short_name', 'name', 'telephone_no', 'address']);

        return response()->json(['customer' => $customer, 'records' => $records->values()]);
    }

    /** Every supplier code in sales and sales history, with the supplier's name and totals of both. */
    public function suppliers()
    {
        $merged = [];
        foreach (self::TABLES as $table => $source) {
            if (!$this->tableExists($table)) {
                continue;
            }
            $billNo = $this->col($table, 'supplier_bill_no', 'NULL');
            $rows = DB::table("{$table} as s")
                ->whereNotNull('s.supplier_code')
                ->where('s.supplier_code', '<>', '')
                ->selectRaw('UPPER(TRIM(s.supplier_code)) AS supplier_code')
                ->selectRaw('COUNT(*) AS records')
                ->selectRaw("COUNT(DISTINCT CASE WHEN {$billNo} IS NOT NULL AND {$billNo} <> '' THEN {$billNo} END) AS bills")
                ->selectRaw('SUM(COALESCE(' . $this->col($table, 'SupplierTotal') . ',0)) AS total')
                ->selectRaw('MAX(' . $this->col($table, 'Date', 'NULL') . ') AS last_date')
                ->groupBy(DB::raw('UPPER(TRIM(s.supplier_code))'))
                ->get();

            foreach ($rows as $r) {
                $m = $merged[$r->supplier_code] ?? [
                    'supplier_code' => $r->supplier_code, 'records' => 0, 'bills' => 0, 'total' => 0.0, 'last_date' => null, 'in_today' => false,
                ];
                $m['records'] += (int) $r->records;
                $m['bills'] += (int) $r->bills;
                $m['total'] += (float) $r->total;
                if ($source === 'today') {
                    $m['in_today'] = true;
                }
                if ($r->last_date && (!$m['last_date'] || $r->last_date > $m['last_date'])) {
                    $m['last_date'] = $r->last_date;
                }
                $merged[$r->supplier_code] = $m;
            }
        }

        $names = DB::table('suppliers')->get(['code', 'name'])
            ->mapWithKeys(fn ($p) => [strtoupper(trim((string) $p->code)) => $p->name]);

        $rows = collect($merged)->sortKeys()->values()->map(function ($m) use ($names) {
            $name = $names[$m['supplier_code']] ?? null;
            return [
                'supplier_code' => $m['supplier_code'],
                'full_name' => $name && strtoupper($name) !== $m['supplier_code'] ? $name : null,
                'records' => $m['records'],
                'bills' => $m['bills'],
                'total' => round($m['total'], 2),
                'last_date' => $m['last_date'],
                'in_today' => $m['in_today'],
            ];
        });

        return response()->json(['suppliers' => $rows]);
    }

    /** One supplier's bills from sales and sales history: each supplier bill number with its total. */
    public function supplierBills(string $code)
    {
        $records = collect();
        foreach (self::TABLES as $table => $source) {
            if (!$this->tableExists($table)) {
                continue;
            }
            $rows = DB::table("{$table} as s")
                ->whereRaw('UPPER(TRIM(s.supplier_code)) = ?', [strtoupper(trim($code))])
                ->selectRaw('s.id, s.supplier_code, s.customer_code, s.item_code, s.item_name, s.weight, s.packs')
                ->selectRaw($this->col($table, 'SupplierWeight', 'NULL') . ' AS SupplierWeight')
                ->selectRaw($this->col($table, 'SupplierPricePerKg') . ' AS SupplierPricePerKg')
                ->selectRaw($this->col($table, 'SupplierTotal') . ' AS SupplierTotal')
                ->selectRaw($this->col($table, 'supplier_bill_no', 'NULL') . ' AS supplier_bill_no')
                ->selectRaw($this->col($table, 'supplier_bill_printed', 'NULL') . ' AS supplier_bill_printed')
                ->selectRaw($this->col($table, 'Date', 'NULL') . ' AS Date')
                ->selectRaw($this->col($table, 'created_at', 'NULL') . ' AS created_at')
                ->selectRaw('? AS source', [$source])
                ->orderBy('s.id')
                ->get();
            $records = $records->concat($rows);
        }

        $bills = $records->groupBy(fn ($r) => $r->supplier_bill_no ?: '')
            ->map(function ($lines, $billNo) {
                return [
                    'bill_no' => $billNo !== '' ? $billNo : null,
                    'printed' => $billNo !== '' && $lines->contains(fn ($l) => $l->supplier_bill_printed === 'Y'),
                    'records' => $lines->count(),
                    'packs' => (int) $lines->sum('packs'),
                    'weight' => round((float) $lines->sum(fn ($l) => (float) ($l->SupplierWeight ?? $l->weight)), 3),
                    'total' => round((float) $lines->sum(fn ($l) => (float) $l->SupplierTotal), 2),
                    'date' => $lines->max('Date'),
                    'lines' => $lines->values(),
                ];
            })
            ->sortBy(fn ($b) => $b['bill_no'] === null ? PHP_INT_MAX : (int) preg_replace('/\D/', '', $b['bill_no']))
            ->values();

        $supplier = DB::table('suppliers')->whereRaw('UPPER(code) = ?', [strtoupper($code)])
            ->first(['code', 'name', 'telephone_no']);

        return response()->json(['supplier' => $supplier, 'bills' => $bills]);
    }

    /**
     * Edit supplier bill values (supplier weight / supplier price per kg) of records in today's
     * sales or in sales history, then recalculate SupplierTotal, profit and commission.
     * Body: { "updates": [ { "id": 1, "source": "today"|"history", "SupplierWeight": 12.5, "SupplierPricePerKg": 95 } ] }
     */
    public function updateSupplierLines(Request $request)
    {
        $data = $request->validate([
            'updates'                      => 'required|array|min:1|max:500',
            'updates.*.id'                 => 'required|integer',
            'updates.*.source'             => ['required', Rule::in(['today', 'history', 'sales', 'sales_history', 'sales_histories'])],
            'updates.*.SupplierWeight'     => 'nullable|numeric|min:0',
            'updates.*.SupplierPricePerKg' => 'nullable|numeric|min:0',
        ]);

        try {
            $count = DB::transaction(function () use ($data) {
                $count = 0;
                foreach ($data['updates'] as $i => $u) {
                    if (!isset($u['SupplierWeight']) && !isset($u['SupplierPricePerKg'])) {
                        continue;
                    }
                    $table = in_array($u['source'], ['today', 'sales'], true) ? 'sales' : 'sales_histories';
                    $row = DB::table($table)->where('id', $u['id'])->lockForUpdate()->first();
                    if (!$row) {
                        throw new \RuntimeException("Record #{$u['id']} was not found.");
                    }
                    $columns = array_map('strtolower', Schema::getColumnListing($table));
                    $has = fn ($c) => in_array(strtolower($c), $columns, true);

                    $weight = isset($u['SupplierWeight']) ? (float) $u['SupplierWeight'] : (float) ($row->SupplierWeight ?? $row->weight ?? 0);
                    $price = isset($u['SupplierPricePerKg']) ? (float) $u['SupplierPricePerKg'] : (float) ($row->SupplierPricePerKg ?? 0);
                    $supplierTotal = round($weight * $price, 2);

                    $fields = [];
                    if ($has('SupplierWeight')) $fields['SupplierWeight'] = $weight;
                    if ($has('SupplierPricePerKg')) $fields['SupplierPricePerKg'] = $price;
                    if ($has('SupplierTotal')) $fields['SupplierTotal'] = $supplierTotal;
                    if ($has('profit')) $fields['profit'] = round((float) ($row->total ?? 0) - $supplierTotal, 2);
                    if ($has('commission_amount') && isset($row->price_per_kg)) {
                        $fields['commission_amount'] = round((float) $row->price_per_kg - $price, 2);
                    }
                    if ($has('updated_at')) $fields['updated_at'] = now();

                    DB::table($table)->where('id', $u['id'])->update($fields);
                    $count++;
                }
                return $count;
            });

            return response()->json(['message' => "{$count} record(s) updated.", 'updated_count' => $count]);
        } catch (\Throwable $e) {
            \Log::error('updateSupplierLines failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => $e->getMessage()], 500);
        }
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
