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

    /** Settlement rows (customer_bill_settlements), with the newer columns read safely. */
    private function settlementRows(?string $code = null)
    {
        if (!$this->tableExists('customer_bill_settlements')) {
            return collect();
        }
        $q = DB::table('customer_bill_settlements');
        if ($code !== null) {
            $q->whereRaw('UPPER(TRIM(customer_code)) = ?', [strtoupper(trim($code))]);
        }
        return $q->orderBy('received_date')->orderBy('id')->get()->map(function ($r) {
            $r->customer_code = strtoupper(trim((string) $r->customer_code));
            $r->amount = (float) $r->amount;
            $r->closes_bill = (bool) ($r->closes_bill ?? false);
            $r->from_credit = (bool) ($r->from_credit ?? false);
            return $r;
        });
    }

    /** Loans typed in by hand (customer_manual_loans); each one is a bill with key "loan|{id}". */
    private function manualLoans(?string $code = null)
    {
        if (!$this->tableExists('customer_manual_loans')) {
            return collect();
        }
        $q = DB::table('customer_manual_loans');
        if ($code !== null) {
            $q->whereRaw('UPPER(TRIM(customer_code)) = ?', [strtoupper(trim($code))]);
        }
        return $q->orderBy('loan_date')->orderBy('id')->get()->map(function ($r) {
            $r->customer_code = strtoupper(trim((string) $r->customer_code));
            $r->amount = (float) $r->amount;
            $r->loan_date = substr((string) $r->loan_date, 0, 10);
            $r->bill_key = 'loan|' . $r->id;
            return $r;
        });
    }

    /**
     * Printed customer bills from the New Supplier Bills page (bill_type 'customer'), today's and
     * those moved to history at day end. Each is a bill with key "cbill|{bill_no}"; the amount paid
     * when it was made (all of it for a cash bill) counts as received.
     */
    private function pageCustomerBills(?string $code = null)
    {
        $rows = collect();
        foreach (['new_supplier_bills', 'history_new_supplier_bills'] as $table) {
            if (!$this->tableExists($table) || !Schema::hasColumn($table, 'bill_type')) {
                continue;
            }
            $q = DB::table($table)->where('bill_type', 'customer')->where('is_printed', 1)->whereNotNull('bill_no');
            if ($code !== null) {
                $q->whereRaw('UPPER(TRIM(supplier_code)) = ?', [strtoupper(trim($code))]);
            }
            $hasPayment = Schema::hasColumn($table, 'payment_type');
            foreach ($q->get() as $r) {
                $net = round((float) $r->net_amount, 2);
                $cash = $hasPayment && ($r->payment_type ?? 'credit') === 'cash';
                $rows->put($r->bill_no, (object) [
                    'bill_key'      => 'cbill|' . $r->bill_no,
                    'bill_no'       => $r->bill_no,
                    'customer_code' => strtoupper(trim((string) $r->supplier_code)),
                    'customer_name' => $r->supplier_name,
                    'bill_date'     => substr((string) $r->bill_date, 0, 10),
                    'total'         => $net,
                    'prepaid'       => $cash ? $net : min($net, round((float) $r->paid_amount, 2)),
                    'payment_type'  => $cash ? 'cash' : 'credit',
                ]);
            }
        }
        return $rows->values();
    }

    /**
     * Received money not given to a bill yet:received amounts (no bill) minus what was used
     * from them to settle bills. CODE => amount.
     */
    private function availableCredit($settlements): array
    {
        $credit = [];
        foreach ($settlements as $r) {
            if ($r->bill_key === null || $r->bill_key === '') {
                $credit[$r->customer_code] = ($credit[$r->customer_code] ?? 0) + $r->amount;
            } elseif ($r->from_credit) {
                $credit[$r->customer_code] = ($credit[$r->customer_code] ?? 0) - $r->amount;
            }
        }
        return array_map(fn ($v) => round(max(0, $v), 2), $credit);
    }

    /**
     * Customers by bill status. A bill (bill date + bill no) is settled when the money received
     * for it covers its total, or when it was closed with a smaller amount ("fully settle").
     * credit:   the customer's bills that are still open (unprinted bills are always open)
     * received: the customer's settled bills
     * A customer with both kinds of bills is in both lists.
     */
    public function customers()
    {
        $bills = []; // CODE => [billKey => [...]]
        $info = [];  // CODE => sales_name, in_today, unprinted_records
        foreach (self::TABLES as $table => $source) {
            if (!$this->tableExists($table)) {
                continue;
            }
            $date = $this->col($table, 'Date', 'NULL');
            $rows = DB::table("{$table} as s")
                ->whereNotNull('s.customer_code')
                ->where('s.customer_code', '<>', '')
                ->selectRaw('UPPER(TRIM(s.customer_code)) AS customer_code')
                ->selectRaw("{$date} AS bill_date, s.bill_no")
                ->selectRaw('MAX(' . $this->col($table, 'customer_name', 'NULL') . ') AS sales_name')
                ->selectRaw("SUM(CASE WHEN s.bill_printed = 'Y' THEN 0 ELSE 1 END) AS unprinted_records")
                ->selectRaw('SUM(' . $this->customerLineTotal($table) . ') AS total')
                ->groupBy(DB::raw('UPPER(TRIM(s.customer_code))'), DB::raw($date), 's.bill_no')
                ->get();

            foreach ($rows as $r) {
                $code = $r->customer_code;
                $i = $info[$code] ?? ['sales_name' => null, 'in_today' => false, 'unprinted_records' => 0];
                $i['sales_name'] = $i['sales_name'] ?: $r->sales_name;
                if ($source === 'today') {
                    $i['in_today'] = true;
                    $i['unprinted_records'] += (int) $r->unprinted_records;
                }
                $info[$code] = $i;

                $day = $r->bill_date ? substr((string) $r->bill_date, 0, 10) : '';
                $key = ($r->bill_no !== null && $r->bill_no !== '') ? $day . '|' . $r->bill_no : 'unprinted';
                $bill = $bills[$code][$key] ?? ['key' => $key, 'date' => $day ?: null, 'total' => 0.0];
                $bill['total'] += (float) $r->total;
                $bills[$code][$key] = $bill;
            }
        }

        // Loans typed in by hand count as bills of the customer
        foreach ($this->manualLoans() as $l) {
            $bills[$l->customer_code][$l->bill_key] = ['key' => $l->bill_key, 'date' => $l->loan_date, 'total' => $l->amount];
            $info[$l->customer_code] = $info[$l->customer_code] ?? ['sales_name' => null, 'in_today' => false, 'unprinted_records' => 0];
        }

        $settlements = $this->settlementRows();
        $paid = [];   // CODE|billKey => received
        $closed = []; // CODE|billKey => true
        $lastReceived = [];

        // Customer bills made on the New Supplier Bills page; money paid with the bill counts as received
        foreach ($this->pageCustomerBills() as $b) {
            $bills[$b->customer_code][$b->bill_key] = ['key' => $b->bill_key, 'date' => $b->bill_date, 'total' => $b->total];
            $info[$b->customer_code] = $info[$b->customer_code] ?? ['sales_name' => $b->customer_name, 'in_today' => false, 'unprinted_records' => 0];
            if ($b->prepaid > 0) {
                $paid[$b->customer_code . '|' . $b->bill_key] = $b->prepaid;
            }
        }
        foreach ($settlements as $r) {
            if ($r->bill_key === null || $r->bill_key === '') continue;
            $k = $r->customer_code . '|' . $r->bill_key;
            $paid[$k] = ($paid[$k] ?? 0) + $r->amount;
            if ($r->closes_bill) $closed[$k] = true;
            $d = substr((string) $r->received_date, 0, 10);
            if (!isset($lastReceived[$r->customer_code]) || $d > $lastReceived[$r->customer_code]) $lastReceived[$r->customer_code] = $d;
        }
        $credit = $this->availableCredit($settlements);

        $names = DB::table('customers')->get(['short_name', 'name'])
            ->mapWithKeys(fn ($c) => [strtoupper(trim((string) $c->short_name)) => $c->name]);

        ksort($bills);
        $rows = [];
        foreach ($bills as $code => $list) {
            $open = ['bills' => 0, 'total' => 0.0, 'balance' => 0.0, 'last_date' => null];
            $done = ['bills' => 0, 'total' => 0.0, 'received' => 0.0, 'last_date' => null];
            foreach ($list as $key => $b) {
                $k = $code . '|' . $key;
                $received = $paid[$k] ?? 0.0;
                $settled = $key !== 'unprinted' && $b['total'] > 0
                    && (!empty($closed[$k]) || $received >= round($b['total'], 2) - 0.009);
                $grp = $settled ? 'done' : 'open';
                if ($settled) {
                    $done['bills']++;
                    $done['total'] += $b['total'];
                    $done['received'] += $received;
                    if ($b['date'] && (!$done['last_date'] || $b['date'] > $done['last_date'])) $done['last_date'] = $b['date'];
                } else {
                    if ($key !== 'unprinted') $open['bills']++;
                    $open['total'] += $b['total'];
                    $open['balance'] += $b['total'] - $received;
                    if ($b['date'] && (!$open['last_date'] || $b['date'] > $open['last_date'])) $open['last_date'] = $b['date'];
                }
            }
            $available = $credit[$code] ?? 0.0;
            $rows[] = [
                'customer_code' => (string) $code, // numeric codes (e.g. 113) become int array keys in PHP
                'full_name' => $names[$code] ?? ($info[$code]['sales_name'] ?? null),
                'in_today' => $info[$code]['in_today'] ?? false,
                'unprinted_records' => $info[$code]['unprinted_records'] ?? 0,
                'available_credit' => $available,
                // Open bills: balance still owed after the received amount not given to a bill yet
                'credit' => ($open['bills'] > 0 || $open['total'] > 0.009) ? [
                    'bills' => $open['bills'],
                    'total' => round($open['total'], 2),
                    'balance' => round($open['balance'] - $available, 2),
                    'last_date' => $open['last_date'],
                ] : null,
                'received' => $done['bills'] > 0 ? [
                    'bills' => $done['bills'],
                    'total' => round($done['total'], 2),
                    'received' => round($done['received'], 2),
                    'last_date' => $done['last_date'],
                    'last_received' => $lastReceived[$code] ?? null,
                ] : null,
            ];
        }

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

        $settlements = $this->settlementRows($code);

        return response()->json([
            'customer' => $customer,
            'records' => $records->values(),
            'settlements' => $settlements->values(),
            'loans' => $this->manualLoans($code)->values(),
            'page_bills' => $this->pageCustomerBills($code)->values(),
            'available_credit' => $this->availableCredit($settlements)[strtoupper(trim($code))] ?? 0.0,
        ]);
    }

    /**
     * Record money received from a customer.
     *  - bill_key empty: a received amount not given to a bill yet (used later when settling)
     *  - bill_key set:   settle that bill with `amount` (cash now) + `credit_used` (from the
     *                    customer's received amount); closes_bill = settled even if less than the total
     */
    public function storeSettlement(Request $request, string $code)
    {
        if (!$this->tableExists('customer_bill_settlements')) {
            return response()->json(['message' => 'Import database/sql/2026_11_25_customer_bill_settlements.sql first.'], 500);
        }
        $data = $request->validate([
            'bill_key'      => 'nullable|string|max:120',
            'bill_no'       => 'nullable|string|max:50',
            'bill_date'     => 'nullable|date',
            'amount'        => 'nullable|numeric|min:0',
            'credit_used'   => 'nullable|numeric|min:0',
            'received_date' => 'required|date',
            'note'          => 'nullable|string|max:255',
            'closes_bill'   => 'nullable|boolean',
        ]);
        $code = strtoupper(trim($code));
        $amount = round((float) ($data['amount'] ?? 0), 2);
        $creditUsed = round((float) ($data['credit_used'] ?? 0), 2);
        $billKey = trim((string) ($data['bill_key'] ?? '')) ?: null;
        $cols = array_map('strtolower', Schema::getColumnListing('customer_bill_settlements'));
        $hasFlags = in_array('closes_bill', $cols, true) && in_array('from_credit', $cols, true);

        if (!$billKey && $amount <= 0) {
            return response()->json(['message' => 'Enter the amount received.'], 422);
        }
        if ($billKey && $amount + $creditUsed <= 0) {
            return response()->json(['message' => 'Enter the amount received.'], 422);
        }
        if (($creditUsed > 0 || !empty($data['closes_bill']) || !$billKey) && !$hasFlags) {
            return response()->json(['message' => 'Import database/sql/2026_11_26_customer_bill_settlements_flags.sql first.'], 500);
        }
        if ($creditUsed > 0) {
            $available = $this->availableCredit($this->settlementRows($code))[$code] ?? 0.0;
            if ($creditUsed > $available + 0.009) {
                return response()->json(['message' => 'The received amount available is only Rs. ' . number_format($available, 2) . '.'], 422);
            }
        }

        $base = [
            'customer_code' => $code,
            'bill_key'      => $billKey,
            'bill_no'       => $billKey ? ($data['bill_no'] ?? null) : null,
            'bill_date'     => $billKey && !empty($data['bill_date']) ? substr($data['bill_date'], 0, 10) : null,
            'received_date' => substr($data['received_date'], 0, 10),
            'note'          => $data['note'] ?? null,
            'created_at'    => now(),
            'updated_at'    => now(),
        ];
        // Only the last row of a settlement closes the bill
        $closes = $billKey && !empty($data['closes_bill']);
        $ids = DB::transaction(function () use ($base, $amount, $creditUsed, $closes, $hasFlags) {
            $ids = [];
            if ($creditUsed > 0) {
                $ids[] = DB::table('customer_bill_settlements')->insertGetId(array_merge($base, [
                    'amount' => $creditUsed, 'from_credit' => 1, 'closes_bill' => ($closes && $amount <= 0) ? 1 : 0,
                    'note' => trim(($base['note'] ? $base['note'] . ' · ' : '') . 'from received amount'),
                ]));
            }
            if ($amount > 0) {
                $ids[] = DB::table('customer_bill_settlements')->insertGetId($base + ['amount' => $amount]
                    + ($hasFlags ? ['from_credit' => 0, 'closes_bill' => $closes ? 1 : 0] : []));
            }
            return $ids;
        });

        return response()->json(['ids' => $ids], 201);
    }

    /** Remove a settlement entered by mistake. */
    public function destroySettlement(int $id)
    {
        if ($this->tableExists('customer_bill_settlements')) {
            DB::table('customer_bill_settlements')->where('id', $id)->delete();
        }
        return response()->json(['deleted' => true]);
    }

    /** Add a customer loan by hand (shown and settled like a bill). */
    public function storeLoan(Request $request)
    {
        if (!$this->tableExists('customer_manual_loans')) {
            return response()->json(['message' => 'Import database/sql/2026_11_27_customer_manual_loans.sql first.'], 500);
        }
        $data = $request->validate([
            'customer_code' => 'required|string|max:50',
            'loan_date'     => 'required|date',
            'ref_no'        => 'nullable|string|max:50',
            'amount'        => 'required|numeric|gt:0',
            'note'          => 'nullable|string|max:255',
        ]);
        $code = strtoupper(trim($data['customer_code']));
        if ($code === '') {
            return response()->json(['message' => 'Enter the customer code.'], 422);
        }
        $id = DB::table('customer_manual_loans')->insertGetId([
            'customer_code' => $code,
            'loan_date'     => substr($data['loan_date'], 0, 10),
            'ref_no'        => trim((string) ($data['ref_no'] ?? '')) ?: null,
            'amount'        => round((float) $data['amount'], 2),
            'note'          => trim((string) ($data['note'] ?? '')) ?: null,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
        return response()->json(['id' => $id, 'customer_code' => $code], 201);
    }

    /** Remove a loan typed in by mistake, with the payments saved against it. */
    public function destroyLoan(int $id)
    {
        if ($this->tableExists('customer_manual_loans')) {
            DB::transaction(function () use ($id) {
                if ($this->tableExists('customer_bill_settlements')) {
                    DB::table('customer_bill_settlements')->where('bill_key', 'loan|' . $id)->delete();
                }
                DB::table('customer_manual_loans')->where('id', $id)->delete();
            });
        }
        return response()->json(['deleted' => true]);
    }

    /**
     * Advance / hire deducted on each printed supplier bill (saved in supplier_bill_links when
     * the bill was printed; the latest row of a bill number counts): bill_no => [advance, hire, supplier_code].
     */
    private function billCharges(): array
    {
        if (!$this->tableExists('supplier_bill_links')) {
            return [];
        }
        $cols = array_map('strtolower', Schema::getColumnListing('supplier_bill_links'));
        $select = ['id', 'bill_no'];
        foreach (['supplier_code', 'advance_amount', 'hire_amount'] as $c) {
            if (in_array($c, $cols, true)) $select[] = $c;
        }
        $charges = [];
        foreach (DB::table('supplier_bill_links')->orderBy('id')->get($select) as $l) {
            if ($l->bill_no === null || $l->bill_no === '') continue;
            $charges[(string) $l->bill_no] = [
                'advance' => (float) ($l->advance_amount ?? 0),
                'hire' => (float) ($l->hire_amount ?? 0),
                'supplier_code' => strtoupper(trim((string) ($l->supplier_code ?? ''))),
            ];
        }
        return $charges;
    }

    /** Advance / hire still on the supplier (not printed on a bill yet): CODE => [advance, hire]. */
    private function pendingCharges(): array
    {
        $cols = array_map('strtolower', Schema::getColumnListing('suppliers'));
        $select = ['code'];
        foreach (['advance_amount', 'hire_amount'] as $c) {
            if (in_array($c, $cols, true)) $select[] = $c;
        }
        $pending = [];
        foreach (DB::table('suppliers')->get($select) as $p) {
            $a = (float) ($p->advance_amount ?? 0);
            $h = (float) ($p->hire_amount ?? 0);
            if ($a || $h) $pending[strtoupper(trim((string) $p->code))] = ['advance' => $a, 'hire' => $h];
        }
        return $pending;
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
                    'bill_nos' => [],
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

            // Supplier bill numbers of each supplier (for the advance / hire deducted on them)
            if ($billNo !== 'NULL') {
                $pairs = DB::table("{$table} as s")
                    ->whereNotNull('s.supplier_code')->where('s.supplier_code', '<>', '')
                    ->whereNotNull('s.supplier_bill_no')->where('s.supplier_bill_no', '<>', '')
                    ->selectRaw('DISTINCT UPPER(TRIM(s.supplier_code)) AS supplier_code, s.supplier_bill_no AS bill_no')
                    ->get();
                foreach ($pairs as $p) {
                    if (isset($merged[$p->supplier_code])) {
                        $merged[$p->supplier_code]['bill_nos'][(string) $p->bill_no] = true;
                    }
                }
            }
        }

        $names = DB::table('suppliers')->get(['code', 'name'])
            ->mapWithKeys(fn ($p) => [strtoupper(trim((string) $p->code)) => $p->name]);

        $charges = $this->billCharges();
        $pending = $this->pendingCharges();

        $rows = collect($merged)->sortKeys()->values()->map(function ($m) use ($names, $charges, $pending) {
            $name = $names[$m['supplier_code']] ?? null;
            $advance = $pending[$m['supplier_code']]['advance'] ?? 0.0;
            $hire = $pending[$m['supplier_code']]['hire'] ?? 0.0;
            foreach (array_keys($m['bill_nos']) as $bn) {
                $advance += $charges[(string) $bn]['advance'] ?? 0;
                $hire += $charges[(string) $bn]['hire'] ?? 0;
            }
            return [
                'advance' => round($advance, 2),
                'hire' => round($hire, 2),
                'net' => round($m['total'] - $advance - $hire, 2),
                'supplier_code' => (string) $m['supplier_code'],
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

        $charges = $this->billCharges();
        $pending = $this->pendingCharges()[strtoupper(trim($code))] ?? ['advance' => 0.0, 'hire' => 0.0];

        $bills = $records->groupBy(fn ($r) => $r->supplier_bill_no ?: '')
            ->map(function ($lines, $billNo) use ($charges, $pending) {
                // Printed bill: the advance / hire deducted on it; not printed yet: still on the supplier
                $c = $billNo !== '' ? ($charges[(string) $billNo] ?? ['advance' => 0.0, 'hire' => 0.0]) : $pending;
                $total = round((float) $lines->sum(fn ($l) => (float) $l->SupplierTotal), 2);
                return [
                    'advance' => round($c['advance'], 2),
                    'hire' => round($c['hire'], 2),
                    'net' => round($total - $c['advance'] - $c['hire'], 2),
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
