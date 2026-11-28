<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use App\Models\SupplierBillNumber;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class SupplierController extends Controller
{
    /**
     * Supplier codes whose bills drop the gram part of each sale's weight.
     * Add or remove codes here to turn the 500 g deduction on/off per supplier.
     * Every supplier not listed keeps the normal weight (e.g. 12.500 kg).
     */
    private const GRAM_DEDUCTION_SUPPLIERS = [
        'NVDS',
        'SANJ',
    ];

    /**
     * True when the given supplier is in the hardcoded gram-deduction list.
     */
    private function hasGramDeduction($supplierCode): bool
    {
        $code = strtoupper(trim((string) $supplierCode));
        $listed = array_map(fn ($c) => strtoupper(trim($c)), self::GRAM_DEDUCTION_SUPPLIERS);

        return $code !== '' && in_array($code, $listed, true);
    }

    /**
     * Prepare sales records for the supplier (farmer) bill. Only the in-memory
     * records are changed; the sales table keeps the real values.
     *
     * - `weight` becomes the supplier-side weight (SupplierWeight, which can be
     *   edited from the supplier report). The customer-side weight is kept in
     *   `customer_weight`.
     * - For suppliers in GRAM_DEDUCTION_SUPPLIERS only: drop the gram part of that
     *   weight (12.500 kg -> 12 kg) and recalculate SupplierTotal on the whole-kg
     *   weight (12 x price).
     */
    private function dropGramsFromWeights($records)
    {
        return $records->each(function ($record) {
            $record->setAttribute('customer_weight', $record->weight);
            if ($record->SupplierWeight !== null) {
                $record->weight = (float) $record->SupplierWeight;
            }

            if (!$this->hasGramDeduction($record->supplier_code)) {
                return;
            }
            $record->weight = (int) floor((float) $record->weight);
            $record->SupplierTotal = round($record->weight * (float) $record->SupplierPricePerKg, 2);
        });
    }

    /**
     * Supplier bill SMS is only sent when the Text.lk credentials are set in .env.
     */
    private function smsCredentialsConfigured(): bool
    {
        return !empty(env('TEXTLK_SMS_API_KEY')) && !empty(env('TEXTLK_SMS_SENDER_ID'));
    }

    /**
     * Run an SMS send after the HTTP response has gone back to the browser, so the
     * bill prints straight away and a slow or failing SMS never blocks or fails it.
     */
    private function sendSmsAfterResponse(callable $send, array $context): void
    {
        app()->terminating(function () use ($send, $context) {
            try {
                $result = $send();
                \Log::info('Supplier bill SMS sent after response', $context + ['result' => $result]);
            } catch (\Throwable $e) {
                \Log::error('Supplier bill SMS failed', $context + ['error' => $e->getMessage()]);
            }
        });
    }

    public function index()
    {
        $suppliers = Supplier::all();
        return response()->json($suppliers);
    }

  public function store(Request $request)
{
    $data = $request->validate([
        'code'         => 'required|unique:suppliers',
        'name'         => 'required|string',
        'dob'          => 'required|date', // Added validation for DOB
        'address'      => 'required|string',
         'telephone_no' => 'required|string|max:20',
        'profile_pic'  => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        'nic_front'    => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        'nic_back'     => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    ]);

    // Handle Profile Picture Upload
    if ($request->hasFile('profile_pic')) {
        $data['profile_pic'] = $request->file('profile_pic')->store('suppliers/profiles', 'public');
    }

    // Handle NIC Front Upload
    if ($request->hasFile('nic_front')) {
        $data['nic_front'] = $request->file('nic_front')->store('suppliers/nic', 'public');
    }

    // Handle NIC Back Upload
    if ($request->hasFile('nic_back')) {
        $data['nic_back'] = $request->file('nic_back')->store('suppliers/nic', 'public');
    }

    // Ensure code is uppercase
    $data['code'] = strtoupper($data['code']);
    
    // This will now include 'dob' in the creation process
    $supplier = Supplier::create($data);

    return response()->json([
        'message' => 'Supplier added successfully!', 
        'supplier' => $supplier
    ], 201);
}    public function show(Supplier $supplier)
    {
        return response()->json($supplier);
    }

   public function update(Request $request, Supplier $supplier)
{
    $data = $request->validate([
        'code'         => 'required|unique:suppliers,code,' . $supplier->id,
        'name'         => 'required|string',
        'dob'          => 'required|date', // Added DOB validation
        'address'      => 'required|string',
        'profile_pic'  => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
        'nic_front'    => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
        'nic_back'     => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
    ]);

    // Handle File Uploads (Profile and NIC)
    foreach (['profile_pic', 'nic_front', 'nic_back'] as $field) {
        if ($request->hasFile($field)) {
            // Delete old file if it exists
            if ($supplier->$field) {
                \Storage::disk('public')->delete($supplier->$field);
            }
            
            // Set storage path based on field type
            $path = ($field === 'profile_pic') ? 'suppliers/profiles' : 'suppliers/nic';
            $data[$field] = $request->file($field)->store($path, 'public');
        }
    }

    // Ensure code is always uppercase
    $data['code'] = strtoupper($data['code']);
    
    // Update the supplier record
    $supplier->update($data);

    return response()->json([
        'message' => 'Supplier updated successfully!', 
        'supplier' => $supplier
    ]);
}
    public function destroy(Supplier $supplier)
    {
        $supplier->delete();
        return response()->json(['message' => 'Supplier deleted successfully!']);
    }

    public function search($query)
    {
        $suppliers = Supplier::where('code', 'LIKE', $query . '%')
                    ->orWhere('name', 'LIKE', $query . '%')
                    ->orWhere('address', 'LIKE', '%' . $query . '%')
                    ->get();
        
        return response()->json($suppliers);
    }
   public function getSupplierBillStatusSummary()
{
    // 1. Get all distinct PRINTED bills (where a bill number has been successfully assigned and printed)
    $printedBills = Sale::select('supplier_code', 'supplier_bill_no')
    ->where('supplier_bill_printed', 'Y')
    ->whereNotNull('supplier_bill_no') // Essential guard: must have a bill number
    ->groupBy('supplier_code', 'supplier_bill_no')
    ->get();
        
    $unprintedBills = Sale::select('supplier_code')
        ->where(function ($query) {
            $query->where('supplier_bill_printed', 'N')
                  ->orWhereNull('supplier_bill_printed'); // Includes records not yet processed
        })
        ->whereNotNull('supplier_code') // Only include records assigned to a supplier
        ->groupBy('supplier_code') // Group only by code, as there is no bill_no yet
        ->get(); 
        
    // 3. Return the data as JSON
    return response()->json([
        'printed' => $printedBills->toArray(),
        'unprinted' => $unprintedBills->toArray(),
    ]);
}
  public function getSupplierDetails($supplierCode)
{
    Log::info('getSupplierDetails METHOD TRIGGERED', [
        'supplierCode' => $supplierCode,
        'route' => request()->path(),
        'method' => request()->method(),
        'user_id' => auth()->id(),
    ]);

    try {
        $details = Sale::select(
            'supplier_code',
            'id',
            'customer_code',
            'item_name',
            'weight',
                'SupplierWeight',
            'price_per_kg',
            'commission_amount',
            'total',
            'packs',
            'bill_no',
            'SupplierTotal',
            'SupplierPricePerKg',
            'SupplierPackCost',
            'CustomerPackLabour',
            'supplier_bill_printed',
            'CustomerPackCost',
            'supplier_bill_no',
            'profile_pic',
            'nic_front',
            'nic_back',
            DB::raw('COALESCE(DATE(`Date`), DATE(created_at)) as Date') // the sale's working date (sales.Date)
        )
        ->where('supplier_code', $supplierCode)
        ->get();

        Log::info('getSupplierDetails QUERY SUCCESS', [
            'records' => $details->count()
        ]);

        return response()->json($details);

    } catch (\Throwable $e) {
        Log::error('getSupplierDetails FAILED', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        throw $e;
    }
}
public function generateFSeriesBill(): JsonResponse
    {
        try {
            $newBillNo = DB::transaction(function () {
                
                // --- CORRECTED CODE START ---
                // 1. Get the single counter row (ID 1), applying the lock, and immediately fetching the result.
                $counter = SupplierBillNumber::where('id', 1)->lockForUpdate()->first(); 
                // --- CORRECTED CODE END ---

                if (!$counter) {
                    throw new \Exception("Bill counter configuration missing. Please check the 'supplier_bill_numbers' table.");
                }

                // 2. Increment the number (This is now safe as $counter is a Model instance)
                $nextNumber = $counter->last_number + 1;
                $newBillNo = $counter->prefix . $nextNumber;

                // 3. Update the counter
                $counter->last_number = $nextNumber;
                $counter->save();

                return $newBillNo;
            });

            // 4. Respond with the new number
            return response()->json([
                'new_bill_no' => $newBillNo,
            ]);

        } catch (\Exception $e) {
            \Log::error('Error generating sequential bill number: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to generate sequential bill number. ' . $e->getMessage()
            ], 500);
        }
    }
    public function getProfitBySupplier()
    {
        try {
            // Eloquent/Query Builder aggregation:
            // SELECT supplier_code, SUM(profit) AS total_profit FROM sales GROUP BY supplier_code
            $profitReport = Sale::select('supplier_code')
                ->selectRaw('SUM(profit) as total_profit')
                ->groupBy('supplier_code')
                ->orderByDesc('total_profit') // Optional: Order by highest profit
                ->get();

            // The resulting collection is automatically formatted as JSON:
            // [{"supplier_code": "SUP001", "total_profit": "1500.50"}, ...]
            return response()->json($profitReport);

        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Error fetching profit by supplier:', ['exception' => $e->getMessage()]);

            return response()->json([
                'message' => 'Failed to fetch profit report data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
   public function marksuppliers(Request $request)
{
    \Log::info('marksuppliers endpoint hit', [
        'request_data' => $request->all(),
        'headers' => $request->headers->all()
    ]);
    
    $validated = $request->validate([
        'transaction_ids' => 'required|array',
        'telephone_no'   => 'nullable|string',
        'advance_amount' => 'required|numeric|min:0',
        'hire_amount'    => 'nullable|numeric|min:0',
        'supplier_code'  => 'required|string'
    ]);
    $validated['hire_amount'] = (float) ($validated['hire_amount'] ?? 0);

    \Log::info('Validation passed', ['validated' => $validated]);

    $ids = $validated['transaction_ids'];

    // defer_sms: the page prints first and then calls confirm-print (send SMS) or
    // cancel-print (put the bill back to unprinted). Without it: old behaviour.
    $deferSms = $request->boolean('defer_sms');

    try {
        DB::beginTransaction();
        \Log::info('Transaction started', ['ids' => $ids]);

        // 1. Generate Bill Number
        $counter = SupplierBillNumber::where('id', 1)->lockForUpdate()->first();
        if (!$counter) {
            \Log::error('SupplierBillNumber not found');
            throw new \Exception('Supplier bill counter not found');
        }
        
        $billNumber = $counter->last_number + 1;
        $finalBillNo = $counter->prefix . $billNumber;
        $counter->increment('last_number');
        \Log::info('Bill number generated', ['bill_no' => $finalBillNo]);

        // 2. Snapshot current data for the public link
        $salesRecords = $this->dropGramsFromWeights(Sale::whereIn('id', $ids)->get());
        \Log::info('Sales records fetched', ['count' => $salesRecords->count()]);

        // Remember the state before printing so a cancelled print can be undone
        $previousState = Sale::whereIn('id', $ids)
            ->get(['id', 'supplier_bill_printed', 'supplier_bill_no'])
            ->map(fn ($sale) => [
                'id' => $sale->id,
                'supplier_bill_printed' => $sale->supplier_bill_printed,
                'supplier_bill_no' => $sale->supplier_bill_no,
            ])->all();

        // 3. Mark records as printed
        $updated = Sale::whereIn('id', $ids)->update([
            'supplier_bill_no' => $finalBillNo,
            'supplier_bill_printed' => 'Y',
        ]);
        \Log::info('Records updated', ['updated_count' => $updated]);

        // 4. Create Public Link Token
        $token = Str::random(40);
        DB::table('supplier_bill_links')->insert([
            'token'         => $token,
            'bill_no'       => $finalBillNo,
            'sales_data'    => $salesRecords->toJson(),
            'advance_amount'=> $validated['advance_amount'],
            'supplier_code' => $validated['supplier_code'],
            'created_at'    => now(),
        ] + $this->linkHireField($validated['hire_amount']));
        \Log::info('Public link created', ['token' => $token]);

        // The advance and hire are deducted on this bill only: take them off the supplier
        // so the next bill does not deduct them again (a cancelled print gives them back).
        $this->useUpSupplierCharges($validated['supplier_code'], (float) $validated['advance_amount'], $validated['hire_amount']);

        DB::commit();
        \Log::info('Transaction committed');

        if ($deferSms) {
            // Kept for 1 hour: enough for the print dialog + the "did it print?" answer
            Cache::put("supplier-print:{$token}", [
                'previous' => $previousState,
                'bill_no' => $finalBillNo,
                'bill_number' => $billNumber,
                'validated' => $validated,
            ], now()->addHour());

            return response()->json(['new_bill_no' => $finalBillNo, 'token' => $token, 'print_token' => $token, 'sms' => 'pending_print']);
        }

        // 5. SEND SMS VIA TEXT.LK - after the response, so the bill prints first.
        //    Skipped when there is no phone number or no Text.lk credentials in .env.
        if (empty($validated['telephone_no'])) {
            $smsStatus = 'skipped_no_phone';
            \Log::warning('No telephone number provided, SMS not sent');
        } elseif (!$this->smsCredentialsConfigured()) {
            $smsStatus = 'skipped_no_credentials';
            \Log::warning('Text.lk credentials not set in .env, SMS not sent', ['bill_no' => $finalBillNo]);
        } else {
            $smsStatus = 'queued';
            $this->sendSmsAfterResponse(
                fn () => $this->sendTextLKSMS($validated, $finalBillNo, $salesRecords, $token),
                ['bill_no' => $finalBillNo, 'telephone' => $validated['telephone_no']]
            );
        }

        return response()->json(['new_bill_no' => $finalBillNo, 'token' => $token, 'sms' => $smsStatus]);

    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('marksuppliers error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

/**
 * The bill printed (user answered "yes" after the print dialog): send the SMS,
 * after the response and only when Text.lk is configured.
 */
public function confirmPrint(Request $request)
{
    $request->validate(['print_token' => 'required|string']);

    $pending = Cache::pull("supplier-print:{$request->print_token}");
    if (!$pending) {
        return response()->json(['error' => 'Print request not found or expired.'], 404);
    }

    $validated = $pending['validated'];

    if (empty($validated['telephone_no'])) {
        return response()->json(['sms' => 'skipped_no_phone']);
    }
    if (!$this->smsCredentialsConfigured()) {
        \Log::warning('Text.lk credentials not set in .env, SMS not sent', ['bill_no' => $pending['bill_no']]);
        return response()->json(['sms' => 'skipped_no_credentials']);
    }

    $salesRecords = $this->dropGramsFromWeights(Sale::whereIn('id', $validated['transaction_ids'])->get());
    $this->sendSmsAfterResponse(
        fn () => $this->sendTextLKSMS($validated, $pending['bill_no'], $salesRecords, $request->print_token),
        ['bill_no' => $pending['bill_no'], 'telephone' => $validated['telephone_no']]
    );

    return response()->json(['sms' => 'queued']);
}

/**
 * The bill did not print (user answered "no"): put the records back in the unprinted
 * section, remove the bill link, and free the bill number if nothing used a later one.
 */
public function cancelPrint(Request $request)
{
    $request->validate(['print_token' => 'required|string']);

    $pending = Cache::get("supplier-print:{$request->print_token}");
    if (!$pending) {
        return response()->json(['error' => 'Print request not found or expired.'], 404);
    }

    DB::transaction(function () use ($pending, $request) {
        foreach ($pending['previous'] as $prev) {
            Sale::where('id', $prev['id'])->update([
                'supplier_bill_printed' => $prev['supplier_bill_printed'] === 'Y' ? 'Y' : 'N',
                'supplier_bill_no' => $prev['supplier_bill_no'],
            ]);
        }

        DB::table('supplier_bill_links')->where('token', $request->print_token)->delete();

        // The bill did not print: the supplier's advance and hire are still owed
        $v = $pending['validated'];
        $supplier = DB::table('suppliers')->where('code', $v['supplier_code'])->lockForUpdate()->first();
        if ($supplier) {
            $this->updateSupplierRow($supplier->code, [
                'advance_amount' => (float) ($supplier->advance_amount ?? 0) + (float) ($v['advance_amount'] ?? 0),
                'hire_amount' => (float) ($supplier->hire_amount ?? 0) + (float) ($v['hire_amount'] ?? 0),
            ]);
        }

        $counter = SupplierBillNumber::where('id', 1)->lockForUpdate()->first();
        if ($counter && (int) $counter->last_number === (int) $pending['bill_number']) {
            $counter->decrement('last_number');
        }
    });

    Cache::forget("supplier-print:{$request->print_token}");

    return response()->json(['message' => 'Bill moved back to unprinted.']);
}

private function sendTextLKSMS($data, $billNo, $records, $token)
{
    // 1. Calculate totals for the message
    $total = $records->sum('SupplierTotal');
    $hire = (float) ($data['hire_amount'] ?? 0);
    $net = $total - $data['advance_amount'] - $hire;
    
    // 2. Build the Public View URL
    $baseUrl = rtrim(env('APP_FRONTEND_URL'), '/');
    $url = "{$baseUrl}/view-supplier-bill/{$token}";

    // 3. Build Item Summary String
    $summary = $records->groupBy('item_name')->map(function ($group) {
        $weight = number_format($group->sum('weight'), 2);
        return $group->first()->item_name . ":" . $weight . "kg/" . $group->sum('packs');
    })->implode("\n");

    // 4. Construct the Final Message - NOW INCLUDING ADVANCE AMOUNT EXPLICITLY
    $message = "Supplier Bill\n" .  // Fixed typo from "supplirer" to "Supplier"
               "Bill #{$billNo}\n" .
               "{$summary}\n" .
               "Total: Rs. " . number_format($total, 2) . "\n" .
               "Advance: Rs. " . number_format($data['advance_amount'], 2) . "\n" .  // Explicitly showing advance
               ($hire > 0 ? "Hire: Rs. " . number_format($hire, 2) . "\n" : "") .
               "Net Payable: Rs. " . number_format($net, 2) . "\n" .
               "View Bill: {$url}";

    // 5. Clean the phone number
    $recipient = preg_replace('/[^0-9]/', '', $data['telephone_no']);
    
    // Log the SMS attempt for debugging
    \Log::info('Attempting to send SMS', [
        'bill_no' => $billNo,
        'recipient' => $recipient,
        'advance_amount' => $data['advance_amount'],
        'total' => $total,
        'net' => $net,
        'message' => $message,
        'api_key_present' => !empty(env('TEXTLK_SMS_API_KEY')),
        'sender_id' => env('TEXTLK_SMS_SENDER_ID')
    ]);

    // 6. Execute Text.lk API Call
    try {
        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => 'Bearer ' . env('TEXTLK_SMS_API_KEY'),
            'Accept'        => 'application/json',
        ])->post('https://app.text.lk/api/v3/sms/send', [
            'recipient' => $recipient,
            'sender_id' => env('TEXTLK_SMS_SENDER_ID'),
            'type'      => 'plain',
            'message'   => $message,
        ]);
        
        \Log::info('Text.lk API Response', [
            'status' => $response->status(),
            'body' => $response->json()
        ]);
        
        return $response;
    } catch (\Exception $e) {
        \Log::error('Text.lk API Error', [
            'error' => $e->getMessage()
        ]);
        throw $e;
    }
}
    /**
     * Get details for a specific bill number
     */
    public function getUnprintedDetails($supplierCode): JsonResponse
    {
        try {
            // Requirement: Records for the given supplier_code where supplier_bill_printed is 'N' or NULL.
            $details = Sale::select(
                'id', 
                'supplier_code',
                'customer_code',
                'item_name',
                'weight',
                'SupplierWeight',
                'price_per_kg',
                'commission_amount',
                'total', // Assuming this is for customer sale total
                'packs',
                'bill_no', // Original customer bill no
                'SupplierTotal', // Supplier's gross total for the sale
                'SupplierPricePerKg',
                'SupplierPackCost',
                'CustomerPackCost',
                'Kuliya', // printed on the supplier bill as "Bag Cost"
                'supplier_bill_printed',
                'supplier_bill_no',
                'loan_taken',
                DB::raw('COALESCE(DATE(`Date`), DATE(created_at)) as Date') // the sale's working date (sales.Date)
            )
            ->where('supplier_code', $supplierCode)
            ->where(function ($query) {
                $query->where('supplier_bill_printed', 'N')
                      ->orWhereNull('supplier_bill_printed');
            })
            ->whereNotNull('supplier_code') // Ensure a supplier code is present
            ->get();

            $this->dropGramsFromWeights($details);

            return response()->json($details);

        } catch (\Exception $e) {
            Log::error("Error fetching unprinted details for supplier {$supplierCode}: " . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch unprinted details',
                'details' => $e->getMessage()
            ], 500);
        }
    }
      public function getUnprintedDetails2($supplierCode): JsonResponse
    {
        try {
            // Requirement: Records for the given supplier_code where supplier_bill_printed is 'N' or NULL.
            $details = Sale::select(
                'id', 
                'supplier_code',
                'customer_code',
                'item_name',
                'weight',
                'SupplierWeight',
                'price_per_kg',
                'commission_amount',
                'total', // Assuming this is for customer sale total
                'packs',
                'bill_no', // Original customer bill no
                'SupplierTotal', // Supplier's gross total for the sale
                'SupplierPricePerKg',
                'SupplierPackCost',
                'supplier_bill_printed',
                'CustomerPackCost',
                'Kuliya',
                'supplier_bill_no',
                'loan_taken',
                DB::raw('COALESCE(DATE(`Date`), DATE(created_at)) as Date') // the sale's working date (sales.Date)
            )
            ->where('supplier_code', $supplierCode)
            ->where('supplier_bill_printed', 'Y')
             ->where('loan_taken', 'Y')
            ->whereNotNull('supplier_code') // Ensure a supplier code is present
            ->get();

            return response()->json($details);

        } catch (\Exception $e) {
            Log::error("Error fetching unprinted details for supplier {$supplierCode}: " . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch unprinted details',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get details for a specific supplier bill number.
     * This is used when clicking a bill in the 'Printed Bills' section.
     */
    public function getBillDetails($billNo): JsonResponse
    {
        try {
            // Requirement: Records with the exact supplier_bill_no and marked 'Y'.
            $details = Sale::select(
                'id',
                'supplier_code',
                'customer_code',
                'item_name',
                'weight',
                'SupplierWeight',
                'price_per_kg',
                'commission_amount',
                'total', // Assuming this is for customer sale total
                'packs',
                'bill_no', // Original customer bill no
                'SupplierTotal', // Supplier's gross total for the sale
                'SupplierPricePerKg',
                'CustomerPackCost',
                'SupplierPackCost',
                'Kuliya', // printed on the supplier bill as "Bag Cost"
                'supplier_bill_printed',
                'supplier_bill_no',
                DB::raw('COALESCE(DATE(`Date`), DATE(created_at)) as Date') // the sale's working date (sales.Date)
            )
            ->where('supplier_bill_no', $billNo)
            ->where('supplier_bill_printed', 'Y')
            ->get();

            $this->dropGramsFromWeights($details);

            return response()->json($details);

        } catch (\Exception $e) {
            Log::error("Error fetching details for bill {$billNo}: " . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch bill details',
                'details' => $e->getMessage()
            ], 500);
        }
    }
    public function updateSupplier(Request $request, $id)
{
    $request->validate([
        'supplier_code' => 'required|string',
        'customer_code' => 'nullable|string' // 🚀 Allow optional customer code
    ]);

    $sale = Sale::findOrFail($id);
    
    // Update supplier_code
    $sale->supplier_code = $request->supplier_code;
    
    // 🚀 Only update customer_code if a value was sent
    if ($request->filled('customer_code')) {
        $sale->customer_code = $request->customer_code;
    }
    
    $sale->save();

    return response()->json([
        'message' => 'Record updated successfully',
        'data' => $sale
    ], 200);
}
/**
 * Supplier Bills page, "Update Advance": sets the supplier's advance (0 clears it).
 * It is deducted on the supplier's next printed bill, then cleared. The date is saved so
 * the day-end process also clears an advance that was never used.
 */
public function store2(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string',
            'advance_amount' => 'required|numeric|min:0',
        ]);

        try {
            $supplier = $this->findOrCreateSupplier($validated['code']);
            $supplier = $this->updateSupplierRow($supplier->code, [
                'advance_amount' => round((float) $validated['advance_amount'], 2),
                // The live column is JSON (MariaDB checks json_valid), so the date is saved as
                // a JSON string: "2026-11-16" with the quotes. The day-end process reads both forms.
                'advance_created_date' => json_encode($this->workingDate()),
            ]);
        } catch (\Throwable $e) {
            \Log::error('Advance update failed', ['code' => $validated['code'], 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Could not save the advance: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'message' => 'Supplier data saved successfully!',
            'data' => $supplier
        ], 200);
    }

/**
 * Supplier Bills page, "Update Hire": sets the hire (transport) amount to deduct on the
 * supplier's next printed bill (0 clears it). Cleared once that bill is printed.
 */
public function updateHire(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string',
            'hire_amount' => 'required|numeric|min:0',
        ]);

        if (!$this->supplierHasColumn('hire_amount')) {
            return response()->json(['message' => 'The hire_amount column is missing: run database/sql/2026_11_16_supplier_hire_amount.sql in phpMyAdmin first.'], 500);
        }

        try {
            $supplier = $this->findOrCreateSupplier($validated['code']);
            $supplier = $this->updateSupplierRow($supplier->code, [
                'hire_amount' => round((float) $validated['hire_amount'], 2),
            ]);
        } catch (\Throwable $e) {
            \Log::error('Hire update failed', ['code' => $validated['code'], 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Could not save the hire amount: ' . $e->getMessage()], 500);
        }

        return response()->json(['message' => 'Hire amount saved.', 'data' => $supplier], 200);
    }

/**
 * Supplier report page, "Edit hire" on a printed bill: changes the hire amount saved with
 * that supplier bill number (used on reprints and in Accounts). The supplier's own pending
 * hire is not touched.
 */
public function updateBillHire(Request $request, $billNo)
    {
        $validated = $request->validate([
            'hire_amount' => 'required|numeric|min:0',
        ]);

        if (!\Illuminate\Support\Facades\Schema::hasColumn('supplier_bill_links', 'hire_amount')) {
            return response()->json(['message' => 'The hire_amount column of supplier_bill_links is missing.'], 500);
        }
        if (!DB::table('supplier_bill_links')->where('bill_no', $billNo)->exists()) {
            return response()->json(['message' => 'Supplier bill ' . $billNo . ' was not found.'], 404);
        }

        $hire = round((float) $validated['hire_amount'], 2);
        DB::table('supplier_bill_links')->where('bill_no', $billNo)->update(['hire_amount' => $hire]);

        return response()->json(['message' => 'Hire amount saved.', 'bill_no' => $billNo, 'hire_amount' => $hire]);
    }

/** Advance and hire a printed supplier bill was printed with (for reprints). */
public function billCharges($billNo)
    {
        $link = DB::table('supplier_bill_links')->where('bill_no', $billNo)->orderByDesc('id')->first();

        return response()->json([
            'bill_no' => $billNo,
            'found' => (bool) $link,
            'advance_amount' => $link ? (float) ($link->advance_amount ?? 0) : 0,
            'hire_amount' => $link ? (float) ($link->hire_amount ?? 0) : 0,
        ]);
    }

/**
 * The supplier with this code; when the code is not in the suppliers table yet (sales can
 * use codes that were never registered), a supplier record is added for it, named by the
 * code. Required columns of the suppliers table get a blank value (text '', number 0,
 * date today), so this works whatever extra columns the table has.
 */
private function findOrCreateSupplier(string $code): object
    {
        $code = strtoupper(trim($code));
        $supplier = DB::table('suppliers')->where('code', $code)->first();
        if ($supplier) {
            return $supplier;
        }

        $attributes = ['code' => $code];
        foreach (['name' => $code, 'advance_amount' => 0] as $column => $value) {
            if ($this->supplierHasColumn($column)) {
                $attributes[$column] = $value;
            }
        }
        foreach (['created_at', 'updated_at'] as $column) {
            if ($this->supplierHasColumn($column)) {
                $attributes[$column] = now();
            }
        }

        try {
            foreach (\Illuminate\Support\Facades\Schema::getColumns('suppliers') as $column) {
                $name = $column['name'];
                if (array_key_exists($name, $attributes) || $column['nullable'] || $column['default'] !== null
                    || !empty($column['auto_increment']) || $name === 'id') {
                    continue;
                }
                $type = strtolower($column['type_name'] ?? '');
                $fullType = strtolower($column['type'] ?? '');
                if ($type === 'enum' && preg_match("/enum\('((?:[^']|'')*)'/", $fullType, $m)) {
                    $attributes[$name] = str_replace("''", "'", $m[1]); // first allowed value
                } elseif ($type === 'json') {
                    $attributes[$name] = '[]';
                } elseif (str_contains($type, 'int') || in_array($type, ['decimal', 'double', 'float', 'numeric', 'real', 'bit'], true)) {
                    $attributes[$name] = 0;
                } elseif ($type === 'date') {
                    $attributes[$name] = now()->toDateString();
                } elseif (str_contains($type, 'time') || $type === 'year') {
                    $attributes[$name] = $type === 'year' ? (int) now()->format('Y') : now();
                } else {
                    $attributes[$name] = '';
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Could not read suppliers columns: ' . $e->getMessage());
        }

        DB::table('suppliers')->insert($attributes);

        return DB::table('suppliers')->where('code', $code)->first();
    }

/** Take the amounts deducted on a bill off the supplier (never below 0). */
private function useUpSupplierCharges(string $supplierCode, float $advance, float $hire): void
    {
        if ($advance <= 0 && $hire <= 0) {
            return;
        }
        $supplier = DB::table('suppliers')->where('code', $supplierCode)->lockForUpdate()->first();
        if (!$supplier) {
            return;
        }
        $newAdvance = max(0, round((float) ($supplier->advance_amount ?? 0) - $advance, 2));
        $fields = [
            'advance_amount' => $newAdvance,
            'hire_amount' => max(0, round((float) ($supplier->hire_amount ?? 0) - $hire, 2)),
        ];
        if ($newAdvance <= 0) {
            $fields['advance_created_date'] = null;
        }
        $this->updateSupplierRow($supplier->code, $fields);
    }

/** ['hire_amount' => x] for supplier_bill_links, or [] while that column is not added yet. */
private function linkHireField(float $hire): array
    {
        static $hasColumn = null;
        if ($hasColumn === null) {
            $hasColumn = \Illuminate\Support\Facades\Schema::hasColumn('supplier_bill_links', 'hire_amount');
        }
        return $hasColumn ? ['hire_amount' => $hire] : [];
    }

/** Columns of the suppliers table (read once per request). */
private function supplierColumnList(): array
    {
        static $columns = null;
        if ($columns === null) {
            $columns = array_map('strtolower', \Illuminate\Support\Facades\Schema::getColumnListing('suppliers'));
        }
        return $columns;
    }

private function supplierHasColumn(string $column): bool
    {
        return in_array(strtolower($column), $this->supplierColumnList(), true);
    }

/**
 * Update a supplier row by code. Fields whose column does not exist are skipped (so a
 * missing hire_amount / updated_at column never breaks printing). Returns the row.
 */
private function updateSupplierRow(string $code, array $fields): object
    {
        $fields = array_filter($fields, fn ($key) => $this->supplierHasColumn($key), ARRAY_FILTER_USE_KEY);
        if ($this->supplierHasColumn('updated_at')) {
            $fields['updated_at'] = now();
        }
        if ($fields) {
            DB::table('suppliers')->where('code', $code)->update($fields);
        }
        return DB::table('suppliers')->where('code', $code)->first();
    }

/** Date saved with an advance; the day-end process clears advances up to its date. */
private function workingDate(): string
    {
        return now()->toDateString();
    }
    public function getByCode($code) {
    return Supplier::where('code', $code)->firstOrFail();
}
// SupplierController.php

public function dobreport(Request $request)
{
    $query = Supplier::query();

    // 1. Filter by Today's Birthdays
    if ($request->has('today_birthday') && $request->today_birthday == 'true') {
        $today = now()->format('m-d'); // Get current Month and Day
        $query->whereRaw("DATE_FORMAT(dob, '%m-%d') = ?", [$today]);
    } 
    // 2. Filter by Date Range (if provided)
    elseif ($request->has('start_date') && $request->has('end_date')) {
        $query->whereBetween('dob', [$request->start_date, $request->end_date]);
    }

    // Select only the requested columns
    $suppliers = $query->select('id', 'code', 'name', 'dob')->get();

    return response()->json($suppliers);
}
// app/Http/Controllers/SupplierController.php

public function updatePhone(Request $request) {
    $validated = $request->validate([
        'code' => 'required|string',
        'telephone_no' => 'required|string',
    ]);

    // Finds by 'code', updates or creates with 'telephone_no'
    $supplier = Supplier::updateOrCreate(
        ['code' => $validated['code']],
        ['telephone_no' => $validated['telephone_no']]
    );

    return response()->json([
        'message' => 'සැපයුම්කරුගේ දුරකථන අංකය යාවත්කාලීන විය!',
        'supplier' => $supplier
    ]);
}
public function resendSupplierSMS(Request $request)
{
    \Log::info('resendSupplierSMS endpoint hit', [
        'request_data' => $request->all(),
        'headers' => $request->headers->all()
    ]);
    
    $validated = $request->validate([
        'bill_no' => 'required|string',
        'telephone_no' => 'required|string',
        'supplier_code' => 'required|string',
        'transaction_ids' => 'required|array',
        'advance_amount' => 'required|numeric|min:0',
        'hire_amount' => 'nullable|numeric|min:0',
        'is_reprint' => 'boolean'
    ]);
    $validated['hire_amount'] = (float) ($validated['hire_amount'] ?? 0);

    try {
        // Fetch the CURRENT sales records for this bill with updated data
        $salesRecords = $this->dropGramsFromWeights(Sale::whereIn('id', $validated['transaction_ids'])->get());
        
        if ($salesRecords->isEmpty()) {
            return response()->json(['error' => 'No records found'], 404);
        }

        \Log::info('Sales records fetched for reprint', [
            'count' => $salesRecords->count(),
            'bill_no' => $validated['bill_no']
        ]);

        // Check if there's an existing token for this bill
        $existingLink = DB::table('supplier_bill_links')
            ->where('bill_no', $validated['bill_no'])
            ->first();

        // IMPORTANT: Update the existing link with new data or create new one
        if ($existingLink) {
            // Update the existing link with current data
            DB::table('supplier_bill_links')
                ->where('bill_no', $validated['bill_no'])
                ->update([
                    'sales_data' => $salesRecords->toJson(),
                    'advance_amount' => $validated['advance_amount'],
                    'supplier_code' => $validated['supplier_code'],
                    'updated_at' => now(),
                ] + $this->linkHireField($validated['hire_amount']));
            
            $token = $existingLink->token;
            \Log::info('Updated existing bill link with current data', [
                'bill_no' => $validated['bill_no'],
                'token' => $token
            ]);
        } else {
            // Create new link if doesn't exist
            $token = $this->createNewBillLink($validated, $salesRecords);
        }

        // Send SMS with updated data - after the response, and only when Text.lk is configured
        if (!$this->smsCredentialsConfigured()) {
            \Log::warning('Text.lk credentials not set in .env, reprint SMS not sent', ['bill_no' => $validated['bill_no']]);

            return response()->json([
                'success' => true,
                'sms' => 'skipped_no_credentials',
                'message' => 'Bill link updated; SMS not sent (no SMS credentials configured)',
                'token' => $token
            ]);
        }

        $this->sendSmsAfterResponse(
            fn () => $this->sendReprintSMS($validated, $salesRecords, $token),
            ['bill_no' => $validated['bill_no'], 'telephone' => $validated['telephone_no']]
        );

        return response()->json([
            'success' => true,
            'sms' => 'queued',
            'message' => 'SMS is being sent with updated bill data',
            'token' => $token
        ]);

    } catch (\Exception $e) {
        \Log::error('resendSupplierSMS error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

private function createNewBillLink($data, $records)
{
    $token = Str::random(40);
    
    DB::table('supplier_bill_links')->insert([
        'token' => $token,
        'bill_no' => $data['bill_no'],
        'sales_data' => $records->toJson(),
        'advance_amount' => $data['advance_amount'],
        'supplier_code' => $data['supplier_code'],
        'created_at' => now(),
        'updated_at' => now(),
    ] + $this->linkHireField((float) ($data['hire_amount'] ?? 0)));
    
    return $token;
}

private function sendReprintSMS($data, $records, $token)
{
    // Calculate totals with current data
    $total = $records->sum('SupplierTotal');
    $hire = (float) ($data['hire_amount'] ?? 0);
    $net = $total - $data['advance_amount'] - $hire;
    
    // Build the Public View URL
    $baseUrl = rtrim(env('APP_FRONTEND_URL'), '/');
    $url = "{$baseUrl}/view-supplier-bill/{$token}";

    // Build Item Summary String with current data
    $summary = $records->groupBy('item_name')->map(function ($group) {
        $weight = number_format($group->sum('weight'), 2);
        $packs = $group->sum('packs');
        return $group->first()->item_name . ":" . $weight . "kg/" . $packs;
    })->implode("\n");

    // Message with clear indication of updated data
    if (isset($data['is_reprint']) && $data['is_reprint']) {
        $message = "🔄 REPRINT - Supplier Bill (UPDATED)\n" .
                   "Bill #{$data['bill_no']} (Reprinted)\n" .
                   "{$summary}\n" .
                   "Total: Rs. " . number_format($total, 2) . "\n" .
                   "Advance: Rs. " . number_format($data['advance_amount'], 2) . "\n" .
                   ($hire > 0 ? "Hire: Rs. " . number_format($hire, 2) . "\n" : "") .
                   "Net: Rs. " . number_format($net, 2) . "\n" .
                   "View Updated Bill: {$url}\n" .
                   "Please check the updated details.";
    } else {
        $message = "Supplier Bill\n" .
                   "Bill #{$data['bill_no']}\n" .
                   "{$summary}\n" .
                   "Total: Rs. " . number_format($total, 2) . "\n" .
                   "Advance: Rs. " . number_format($data['advance_amount'], 2) . "\n" .
                   ($hire > 0 ? "Hire: Rs. " . number_format($hire, 2) . "\n" : "") .
                   "Net: Rs. " . number_format($net, 2) . "\n" .
                   "View Bill: {$url}";
    }

    // Clean the phone number
    $recipient = preg_replace('/[^0-9]/', '', $data['telephone_no']);
    
    \Log::info('Attempting to send reprint SMS with updated data', [
        'bill_no' => $data['bill_no'],
        'recipient' => $recipient,
        'advance_amount' => $data['advance_amount'],
        'total' => $total,
        'net' => $net,
        'is_reprint' => $data['is_reprint'] ?? false
    ]);

    // Execute Text.lk API Call
    try {
        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => 'Bearer ' . env('TEXTLK_SMS_API_KEY'),
            'Accept' => 'application/json',
        ])->post('https://app.text.lk/api/v3/sms/send', [
            'recipient' => $recipient,
            'sender_id' => env('TEXTLK_SMS_SENDER_ID'),
            'type' => 'plain',
            'message' => $message,
        ]);
        
        \Log::info('Text.lk API Response for reprint', [
            'status' => $response->status(),
            'body' => $response->json()
        ]);
        
        return $response;
    } catch (\Exception $e) {
        \Log::error('Text.lk API Error for reprint', [
            'error' => $e->getMessage()
        ]);
        throw $e;
    }
}
/**
 * Update the supplier-side weight and/or price per kg of sales records from the
 * supplier report, then recalculate the amounts derived from them:
 *   SupplierTotal     = SupplierWeight x SupplierPricePerKg
 *   profit            = total - SupplierTotal
 *   commission_amount = price_per_kg - SupplierPricePerKg
 *
 * Body: { "updates": [ { "id": 1, "SupplierWeight": 12.5, "SupplierPricePerKg": 95 }, ... ] }
 * Each update needs at least one of the two values.
 */
public function updateSupplierValues(Request $request)
{
    $validated = $request->validate([
        'updates'                      => 'required|array|min:1',
        'updates.*.id'                 => 'required|integer|exists:sales,id',
        'updates.*.SupplierWeight'     => 'nullable|numeric|min:0',
        'updates.*.SupplierPricePerKg' => 'nullable|numeric|min:0',
    ]);

    foreach ($validated['updates'] as $index => $update) {
        if (!isset($update['SupplierWeight']) && !isset($update['SupplierPricePerKg'])) {
            return response()->json([
                'error' => "Update #{$index} needs SupplierWeight or SupplierPricePerKg."
            ], 422);
        }
    }

    try {
        $sales = DB::transaction(function () use ($validated) {
            $saved = [];
            foreach ($validated['updates'] as $update) {
                $sale = Sale::lockForUpdate()->findOrFail($update['id']);

                if (isset($update['SupplierWeight'])) {
                    $sale->SupplierWeight = (float) $update['SupplierWeight'];
                }
                if (isset($update['SupplierPricePerKg'])) {
                    $sale->SupplierPricePerKg = (float) $update['SupplierPricePerKg'];
                }

                $supplierWeight = (float) ($sale->SupplierWeight ?? $sale->weight ?? 0);
                $supplierPrice  = (float) ($sale->SupplierPricePerKg ?? 0);

                $sale->SupplierTotal     = round($supplierWeight * $supplierPrice, 2);
                $sale->profit            = round((float) ($sale->total ?? 0) - $sale->SupplierTotal, 2);
                $sale->commission_amount = round((float) ($sale->price_per_kg ?? 0) - $supplierPrice, 2);

                // supplier_code / item_code / price_per_kg are untouched, so the
                // model's commission hook does not overwrite these values.
                $sale->save();
                $saved[] = $sale->fresh();
            }
            return $saved;
        });

        return response()->json([
            'message'       => count($sales) . ' record(s) updated.',
            'updated_count' => count($sales),
            'data'          => $sales,
        ], 200);
    } catch (\Exception $e) {
        \Log::error('updateSupplierValues failed', ['error' => $e->getMessage()]);
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

public function bulkUpdateSupplier(Request $request)
{
    $validated = $request->validate([
        'transaction_ids' => 'required|array',
        'transaction_ids.*' => 'exists:sales,id',
        'supplier_code'   => 'nullable|string',
        'customer_code'   => 'nullable|string'
    ]);

    if (!$request->filled('supplier_code') && !$request->filled('customer_code')) {
        return response()->json([
            'error' => 'Provide at least one of supplier_code or customer_code.'
        ], 422);
    }

    try {
        $updateData = [];

        if ($request->filled('supplier_code')) {
            $updateData['supplier_code'] = strtoupper($validated['supplier_code']);
        }

        if ($request->filled('customer_code')) {
            $updateData['customer_code'] = strtoupper($validated['customer_code']);
        }

        // Mass update all IDs sent from React
        $updatedCount = Sale::whereIn('id', $validated['transaction_ids'])
            ->update($updateData);

        // A mass update skips model events, so re-apply the commission rules
        // (a new supplier can fall under a different rule).
        if (isset($updateData['supplier_code'])) {
            Sale::recalculateCommissionFor($validated['transaction_ids']);
        }

        return response()->json([
            'message' => 'ගනුදෙනු ' . $updatedCount . ' ක් යාවත්කාලීන කරන ලදී.',
            'updated_count' => $updatedCount
        ], 200);

    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}
 

}