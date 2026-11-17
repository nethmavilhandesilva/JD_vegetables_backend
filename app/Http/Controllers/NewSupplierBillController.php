<?php

namespace App\Http\Controllers;

use App\Models\Commission;
use App\Models\Item;
use App\Models\NewSupplierBill;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * "New Supplier Bills" dashboard: supplier bills entered by hand and stored in
 * new_supplier_bills / new_supplier_bill_items. Independent of the sales tables.
 */
class NewSupplierBillController extends Controller
{
    private const MAX_ROWS = 2000;

    /** Bills list with filters + summary for the filtered range. */
    public function index(Request $request)
    {
        $data = $request->validate([
            'start_date'    => 'nullable|date_format:Y-m-d',
            'end_date'      => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'supplier_code' => 'nullable|string|max:50',
            'status'        => ['nullable', Rule::in(['unpaid', 'partial', 'paid'])],
            'search'        => 'nullable|string|max:100',
            'print_status'  => ['nullable', Rule::in(['held', 'printed'])],
            // Printed sidebar: bills printed during the user's local day (sent as a UTC time range),
            // whatever their bill date, so a held bill from an earlier day shows once printed.
            'printed_from'  => 'nullable|date',
            'printed_to'    => 'nullable|date|after:printed_from',
            'printed_on'    => 'nullable|date_format:Y-m-d',
        ]);

        $query = NewSupplierBill::query();

        if (! empty($data['start_date'])) {
            $query->whereDate('bill_date', '>=', $data['start_date']);
        }
        if (! empty($data['end_date'])) {
            $query->whereDate('bill_date', '<=', $data['end_date']);
        }
        if (! empty($data['supplier_code'])) {
            $query->whereRaw('UPPER(supplier_code) = ?', [strtoupper($data['supplier_code'])]);
        }
        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (! empty($data['print_status'])) {
            $query->where('is_printed', $data['print_status'] === 'printed');
        }
        if (! empty($data['printed_from']) && ! empty($data['printed_to'])) {
            $from = \Carbon\Carbon::parse($data['printed_from'])->utc();
            $to = \Carbon\Carbon::parse($data['printed_to'])->utc();
            $printedOn = $data['printed_on'] ?? null;
            $query->where(function ($q) use ($from, $to, $printedOn) {
                $q->where('printed_at', '>=', $from)->where('printed_at', '<', $to);
                // Older printed bills without a print time fall back to their bill date
                if ($printedOn) {
                    $q->orWhere(fn ($q2) => $q2->whereNull('printed_at')->whereDate('bill_date', $printedOn));
                }
            });
        }
        if (! empty($data['search'])) {
            $term = '%' . $data['search'] . '%';
            $query->where(function ($q) use ($term) {
                $q->where('bill_no', 'like', $term)
                    ->orWhere('reference_no', 'like', $term)
                    ->orWhere('supplier_code', 'like', $term)
                    ->orWhere('supplier_name', 'like', $term);
            });
        }

        $summary = (clone $query)->selectRaw('
            COUNT(*) AS bill_count,
            COALESCE(SUM(total_weight), 0) AS total_weight,
            COALESCE(SUM(total_packs), 0) AS total_packs,
            COALESCE(SUM(gross_amount), 0) AS gross_amount,
            COALESCE(SUM(deductions), 0) AS deductions,
            COALESCE(SUM(net_amount), 0) AS net_amount,
            COALESCE(SUM(paid_amount), 0) AS paid_amount,
            COALESCE(SUM(balance_amount), 0) AS balance_amount
        ')->first();

        $bills = $query->withCount('items')
            ->orderByDesc('bill_date')
            ->orderByDesc('id')
            ->limit(self::MAX_ROWS)
            ->get();

        return response()->json([
            'bills'   => $bills,
            'summary' => [
                'bill_count'     => (int) $summary->bill_count,
                'total_weight'   => round((float) $summary->total_weight, 3),
                'total_packs'    => round((float) $summary->total_packs, 2),
                'gross_amount'   => round((float) $summary->gross_amount, 2),
                'deductions'     => round((float) $summary->deductions, 2),
                'net_amount'     => round((float) $summary->net_amount, 2),
                'paid_amount'    => round((float) $summary->paid_amount, 2),
                'balance_amount' => round((float) $summary->balance_amount, 2),
            ],
            'truncated' => (int) $summary->bill_count > self::MAX_ROWS,
        ]);
    }

    /** Supplier and item pickers for the entry form. */
    public function options()
    {
        return response()->json([
            'suppliers' => Supplier::query()->select('code', 'name')->orderBy('code')->get(),
            'items'     => Item::query()->select('no', 'type', 'pack_cost')->orderBy('no')->get(),
        ]);
    }

    public function show(NewSupplierBill $newSupplierBill)
    {
        return response()->json($newSupplierBill->load('items'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $bill = DB::transaction(function () use ($data, $request) {
            $bill = NewSupplierBill::create($this->headerAttributes($data) + $this->printAttributes($data, false) + [
                'created_by' => optional($request->user())->id,
            ]);
            // Bill number from the auto id: unique without any race between users.
            $bill->bill_no = 'SB-' . str_pad((string) $bill->id, 5, '0', STR_PAD_LEFT);
            $bill->save();

            $bill->items()->createMany($data['lines']);

            return $bill;
        });

        return response()->json($bill->load('items'), 201);
    }

    public function update(Request $request, NewSupplierBill $newSupplierBill)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $newSupplierBill) {
            // A printed bill stays printed; a held bill becomes printed when saved with print=true.
            $newSupplierBill->update($this->headerAttributes($data) + $this->printAttributes($data, $newSupplierBill->is_printed));
            $newSupplierBill->items()->delete();
            $newSupplierBill->items()->createMany($data['lines']);
        });

        return response()->json($newSupplierBill->fresh()->load('items'));
    }

    /** Mark a held bill as printed (used when a saved bill is printed from the list). */
    public function markPrinted(NewSupplierBill $newSupplierBill)
    {
        if (! $newSupplierBill->is_printed) {
            $newSupplierBill->update(['is_printed' => true, 'printed_at' => now()]);
        }
        return response()->json($newSupplierBill->fresh()->load('items'));
    }

    public function destroy(NewSupplierBill $newSupplierBill)
    {
        $newSupplierBill->delete(); // item lines are removed by ON DELETE CASCADE
        return response()->json(['message' => 'Bill deleted']);
    }

    /**
     * Validates the request and computes every amount on the server, so totals
     * never depend on what the browser sent.
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'bill_date'            => 'required|date_format:Y-m-d',
            'supplier_code'        => 'required|string|max:50',
            'supplier_name'        => 'nullable|string|max:255',
            'reference_no'         => 'nullable|string|max:100',
            'deductions'           => 'nullable|numeric|min:0',
            'deduction_note'       => 'nullable|string|max:255',
            'paid_amount'          => 'nullable|numeric|min:0',
            'notes'                => 'nullable|string|max:2000',
            'print'                => 'nullable|boolean',
            'items'                => 'required|array|min:1',
            'items.*.item_code'    => 'required|string|max:50',
            'items.*.item_name'    => 'nullable|string|max:255',
            'items.*.packs'        => 'nullable|numeric|min:0',
            'items.*.weight'       => 'required|numeric|gt:0',
            'items.*.price_per_kg' => 'required|numeric|min:0',
            'items.*.pack_weights'   => 'nullable|array|max:1000',
            'items.*.pack_weights.*' => 'numeric|gt:0',
            'items.*.pack_prices'    => 'nullable|array|max:1000',
            'items.*.pack_prices.*'  => 'numeric|min:0',
        ]);

        $lines = [];
        $totalWeight = 0.0;
        $totalPacks = 0.0;
        $gross = 0.0;
        $commission = 0.0;
        $supplierCode = strtoupper(trim($data['supplier_code']));

        foreach ($data['items'] as $line) {
            $weight = round((float) $line['weight'], 3);
            $price = round((float) $line['price_per_kg'], 2);
            $packs = round((float) ($line['packs'] ?? 0), 2);

            $itemCode = strtoupper(trim($line['item_code']));

            // Entered pack by pack: the line is the sum of the pack weights, packs = how many.
            // Each pack has its own price, so value and commission are added up pack by pack.
            $packWeights = array_values(array_map(fn ($w) => round((float) $w, 3), $line['pack_weights'] ?? []));
            $packPrices = [];
            if ($packWeights) {
                $sentPrices = array_values($line['pack_prices'] ?? []);
                foreach ($packWeights as $j => $w) {
                    $packPrices[] = round((float) ($sentPrices[$j] ?? $price), 2);
                }
                $weight = round(array_sum($packWeights), 3);
                $packs = count($packWeights);

                $amount = 0.0;
                $lineCommission = 0.0;
                foreach ($packWeights as $j => $w) {
                    $amount += $w * $packPrices[$j];
                    $lineCommission += $w * $this->commissionPerKg($itemCode, $supplierCode, $packPrices[$j]);
                }
                $amount = round($amount, 2);
                $lineCommission = round($lineCommission, 2);
                // Price per kg of the line: the average when the packs have different prices
                $price = count(array_unique($packPrices)) === 1 ? $packPrices[0] : ($weight > 0 ? round($amount / $weight, 2) : $price);
                $commissionPerKg = $weight > 0 ? round($lineCommission / $weight, 2) : 0.0;
            } else {
                $amount = round($weight * $price, 2);
                // Commission per kg from the commission rules (same as Sales Entry); deducted from the supplier
                $commissionPerKg = $this->commissionPerKg($itemCode, $supplierCode, $price);
                $lineCommission = round($weight * $commissionPerKg, 2);
            }

            $lines[] = $this->onlyExistingColumns('new_supplier_bill_items', [
                'item_code'         => $itemCode,
                'item_name'         => $line['item_name'] ?? null,
                'packs'             => $packs,
                'weight'            => $weight,
                'price_per_kg'      => $price,
                'amount'            => $amount,
                'pack_weights'      => $packWeights ?: null,
                'pack_prices'       => $packPrices ?: null,
                'commission_per_kg' => $commissionPerKg,
                'commission_amount' => $lineCommission,
            ]);

            $totalWeight += $weight;
            $totalPacks += $packs;
            $gross += $amount;
            $commission += $lineCommission;
        }

        $gross = round($gross, 2);
        $commission = round($commission, 2);
        $deductions = round((float) ($data['deductions'] ?? 0), 2);
        // Supplier is paid: gross - commission - other deductions
        $net = round($gross - $commission - $deductions, 2);
        $paid = round((float) ($data['paid_amount'] ?? 0), 2);
        $balance = round($net - $paid, 2);

        $data['lines'] = $lines;
        $data['totals'] = $this->onlyExistingColumns('new_supplier_bills', [
            'total_weight'      => round($totalWeight, 3),
            'total_packs'       => round($totalPacks, 2),
            'gross_amount'      => $gross,
            'commission_amount' => $commission,
            'deductions'        => $deductions,
            'net_amount'        => $net,
            'paid_amount'       => $paid,
            'balance_amount'    => $balance,
            'status'            => $paid <= 0 ? 'unpaid' : ($balance > 0 ? 'partial' : 'paid'),
        ]);

        return $data;
    }

    /** Commission per kg for a line (0 when no rule applies or the rules cannot be read). */
    private function commissionPerKg(string $itemCode, string $supplierCode, float $price): float
    {
        try {
            return round((float) Commission::amountFor($itemCode, $supplierCode, $price), 2);
        } catch (\Throwable $e) {
            \Log::warning('New supplier bill commission lookup failed: ' . $e->getMessage());
            return 0.0;
        }
    }

    /** Commission per kg for the entry screen (shown while typing; the save recalculates it). */
    public function commission(Request $request)
    {
        $data = $request->validate([
            'item_code'     => 'required|string|max:50',
            'supplier_code' => 'nullable|string|max:50',
            'price_per_kg'  => 'required|numeric|min:0',
        ]);

        return response()->json([
            'commission_per_kg' => $this->commissionPerKg(
                strtoupper(trim($data['item_code'])),
                strtoupper(trim((string) ($data['supplier_code'] ?? ''))),
                (float) $data['price_per_kg']
            ),
        ]);
    }

    /** Drop fields whose column is not in the table yet (before the SQL file is imported). */
    private function onlyExistingColumns(string $table, array $fields): array
    {
        static $columns = [];
        if (!isset($columns[$table])) {
            $columns[$table] = array_map('strtolower', Schema::getColumnListing($table));
        }
        return array_filter($fields, fn ($key) => in_array(strtolower($key), $columns[$table], true), ARRAY_FILTER_USE_KEY);
    }

    /** F5 hold => print=false (stays held); F4 print => print=true. */
    private function printAttributes(array $data, bool $alreadyPrinted): array
    {
        if ($alreadyPrinted || empty($data['print'])) {
            return $alreadyPrinted ? [] : ['is_printed' => false];
        }
        return ['is_printed' => true, 'printed_at' => now()];
    }

    private function headerAttributes(array $data): array
    {
        $supplierCode = strtoupper(trim($data['supplier_code']));
        $supplierName = $data['supplier_name']
            ?? Supplier::whereRaw('UPPER(code) = ?', [$supplierCode])->value('name');

        return [
            'bill_date'      => $data['bill_date'],
            'supplier_code'  => $supplierCode,
            'supplier_name'  => $supplierName,
            'reference_no'   => $data['reference_no'] ?? null,
            'deduction_note' => $data['deduction_note'] ?? null,
            'notes'          => $data['notes'] ?? null,
        ] + $data['totals'];
    }
}
