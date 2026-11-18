<?php

namespace App\Http\Controllers;
use App\Models\CustomersLoan;
use App\Models\IncomeExpenses;
use App\Models\SalesHistory;
use App\Models\Supplier;
use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\Item;
use App\Models\GrnEntry;// Replace with your actual model name
use Carbon\Carbon;
use App\Models\Salesadjustment;
use App\Mail\DailyReportMail;
use Illuminate\Support\Facades\Mail;
use App\Models\Setting;
use App\Mail\ChangeReportMail;
use App\Mail\TotalSalesReportMail;
use App\Mail\BillSummaryReportMail;
use App\Mail\CreditReportMail;
use App\Mail\ItemWiseReportMail;
use App\Mail\GrnSalesReportMail;
use App\Mail\SupplierSalesReportMail;
use App\Mail\GrnSalesOverviewMail;
use App\Mail\SalesReportMail;
use App\Mail\FinancialReportMail;
use App\Mail\LoanReportMail;
use App\Mail\GrnbladeReportMail;
use App\Mail\CombinedReportsMail;
use App\Mail\CombinedReportsMail2;
use App\Models\GrnEntry2;
use Illuminate\Support\Facades\DB;


class ReportController extends Controller
{
    public function fetchItems()
    {
        $items = Item::select('no', 'type')->get();

        return response()->json([
            'items' => $items
        ]);
    }

   public function itemReport(Request $request)
{
    \Log::info('Item Report Request:', $request->all());

    $itemCode  = $request->query('item_code');
    $startDate = $request->query('start_date');
    $endDate   = $request->query('end_date');

    \Log::info("Searching for item: $itemCode, Date range: $startDate to $endDate");

    if (!$itemCode) {
        return response()->json(['error' => 'Item code is required'], 400);
    }

    // ✅ Decide model based on date range
    if ($startDate && $endDate) {
        $model = SalesHistory::query();
        $dateColumn = 'Date'; // Your sales history date column
    } else {
        $model = Sale::query();
        $dateColumn = 'created_at';
    }

    $query = $model->where('item_code', $itemCode);

    // ✅ Apply date filter only if provided
    if ($startDate && $endDate) {
        $query->whereBetween($dateColumn, [
            Carbon::parse($startDate)->startOfDay(),
            Carbon::parse($endDate)->endOfDay(),
        ]);
    }

    $sales = $query->get();

    \Log::info("Found {$sales->count()} sales records for item: $itemCode");

    return response()->json([
        'sales' => $sales,
        'source_table' => $startDate && $endDate ? 'sales_history' : 'sales',
        'filters' => [
            'item_code' => $itemCode,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'record_count' => $sales->count()
        ]
    ]);
}

 public function getweight(Request $request)
{
    $grnCode = $request->input('grn_code');
    $startDate = $request->input('start_date');
    $endDate = $request->input('end_date');
    $supplierCode = $request->input('supplier_code');

    if ($startDate && $endDate) {
        $model = SalesHistory::query();
        $dateColumn = 'Date';
    } else {
        $model = Sale::query();
        $dateColumn = null;
    }

    $query = $model->selectRaw("
        item_code,
        item_name,
        SUM(packs) AS packs,
        SUM(weight) AS weight,
        SUM(total) AS total
    ");

    if ($dateColumn) {
        $query->whereBetween($dateColumn, [
            Carbon::parse($startDate)->startOfDay(),
            Carbon::parse($endDate)->endOfDay(),
        ]);
    }

    if (!empty($supplierCode)) {
        $query->where('supplier_code', $supplierCode);
    }

    if (!empty($grnCode)) {
        $query->where('code', $grnCode);
    }

    $sales = $query
        ->groupBy('item_code', 'item_name')
        ->orderBy('item_name', 'asc')
        ->get();

    // UPDATED: Fetching pack_cost instead of pack_due
    $sales = $sales->map(function ($sale) {
        $item = Item::where('no', $sale->item_code)->first();
        $sale->pack_cost = $item ? $item->pack_cost : 0; 
        return $sale;
    });

    $selectedGrnEntry = $grnCode
        ? GrnEntry::where('code', $grnCode)->first()
        : null;

    return response()->json([
        'sales' => $sales,
        'selectedGrnCode' => $grnCode,
        'selectedGrnEntry' => $selectedGrnEntry,
        'filters' => $request->all(),
    ]);
}
    public function getGrnEntries()
    {
        $entries = GrnEntry::select('code', 'supplier_code', 'item_code', 'item_name', 'packs', 'grn_no', 'txn_date')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'entries' => $entries
        ]);
    }

    public function getGrnSalecodereport(Request $request)
    {
        $grnCode = $request->input('grn_code');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if (!$grnCode) {
            return response()->json(['error' => 'GRN code is required'], 400);
        }

        // Your existing logic...
        if ($startDate && $endDate) {
            $query = SalesHistory::query();
            $query->whereBetween('Date', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ]);
        } else {
            $query = Sale::query();
        }

        $query->where('code', $grnCode);
        $sales = $query->orderBy('created_at', 'asc')->get();
        $selectedGrnEntry = GrnEntry::where('code', $grnCode)->first();

        return response()->json([
            'sales' => $sales,
            'selectedGrnCode' => $grnCode,
            'selectedGrnEntry' => $selectedGrnEntry,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'filters' => $request->all(),
        ]);
    }

    public function getGrnSalesOverviewReport()
    {
        // Your existing logic...
        $grnEntries = GrnEntry::all();
        $reportData = [];

        foreach ($grnEntries->groupBy('code') as $code => $entries) {
            // Your existing calculation logic...
            $totalOriginalPacks = $entries->sum('original_packs');
            $totalOriginalWeight = $entries->sum('original_weight');

            $currentSales = Sale::where('code', $code)->get();
            $historicalSales = SalesHistory::where('code', $code)->get();
            $relatedSales = $currentSales->merge($historicalSales);
            $totalSalesValueForGrn = $relatedSales->sum('total');

            $soldPacksFromSales = $relatedSales->sum('packs');
            $soldWeightFromSales = $relatedSales->sum('weight');

            $remainingPacks = $totalOriginalPacks - $soldPacksFromSales;
            $remainingWeight = $totalOriginalWeight - $soldWeightFromSales;

            $reportData[] = [
                'date' => Carbon::parse($entries->first()->created_at)
                    ->timezone('Asia/Colombo')
                    ->format('Y-m-d H:i:s'),
                'grn_code' => $code,
                'item_name' => $entries->first()->item_name,
                'sp' => $entries->first()->SalesKGPrice,
                'original_packs' => $totalOriginalPacks,
                'original_weight' => $totalOriginalWeight,
                'total_sales_value' => $totalSalesValueForGrn,
                'remaining_packs' => $remainingPacks,
                'remaining_weight' => $remainingWeight,
                'sold_packs' => $soldPacksFromSales,
                'sold_weight' => $soldWeightFromSales,
            ];
        }

        $reportData = collect($reportData)->sortBy('grn_code')->values();

        $companyName = Setting::value('CompanyName') ?? 'Default Company';
        $settingDate = Setting::value('value');
        $formattedDate = $settingDate ? Carbon::parse($settingDate)->format('Y-m-d') : Carbon::now()->format('Y-m-d');

        return response()->json([
            'reportData' => $reportData,
            'companyName' => $companyName,
            'settingDate' => $formattedDate
        ]);
    }
    public function getGrnSalesOverviewReport2()
    {
        // Your existing logic...
        $grnEntries = GrnEntry::all();
        $reportData = [];

        // Group entries by item_name
        $grouped = $grnEntries->groupBy('item_name');

        foreach ($grouped as $itemName => $entries) {
            $originalPacks = 0;
            $originalWeight = 0;
            $soldPacks = 0;
            $soldWeight = 0;
            $totalSalesValue = 0;

            foreach ($entries as $grnEntry) {
                // Fetch all sales (current + history) for this GRN code
                $currentSales = Sale::where('code', $grnEntry->code)->get();
                $historicalSales = SalesHistory::where('code', $grnEntry->code)->get();
                $relatedSales = $currentSales->merge($historicalSales);

                // Sold quantities and values
                $totalSoldWeight = $relatedSales->sum('weight');
                $totalSoldPacks = $relatedSales->sum('packs');
                $totalSalesValueForGrn = $relatedSales->sum('total');

                // Add to totals
                $originalPacks += $grnEntry->original_packs;
                $originalWeight += $grnEntry->original_weight;
                $soldPacks += $totalSoldPacks;
                $soldWeight += $totalSoldWeight;
                $totalSalesValue += $totalSalesValueForGrn;
            }

            // Compute remaining after summing everything
            $remainingPacks = $originalPacks - $soldPacks;
            $remainingWeight = $originalWeight - $soldWeight;

            // Add to report
            $reportData[] = [
                'item_name' => $itemName,
                'original_packs' => $originalPacks,
                'original_weight' => $originalWeight,
                'sold_packs' => $soldPacks,
                'sold_weight' => $soldWeight,
                'remaining_packs' => $remainingPacks,
                'remaining_weight' => $remainingWeight,
                'total_sales_value' => $totalSalesValue,
            ];
        }

        // Group and sum by item_name to combine duplicates
        $finalReportData = collect($reportData)->groupBy('item_name')->map(function ($group) {
            return [
                'item_name' => $group->first()['item_name'],
                'original_packs' => $group->sum('original_packs'),
                'original_weight' => $group->sum('original_weight'),
                'sold_packs' => $group->sum('sold_packs'),
                'sold_weight' => $group->sum('sold_weight'),
                'remaining_packs' => $group->sum('remaining_packs'),
                'remaining_weight' => $group->sum('remaining_weight'),
                'total_sales_value' => $group->sum('total_sales_value'),
            ];
        })->values();

        $companyName = Setting::value('CompanyName') ?? 'Default Company';
        $settingDate = Setting::value('value');
        $formattedDate = $settingDate ? Carbon::parse($settingDate)->format('Y-m-d') : Carbon::now()->format('Y-m-d');

        return response()->json([
            'reportData' => $finalReportData,
            'companyName' => $companyName,
            'settingDate' => $formattedDate
        ]);
    }

    public function salesAdjustmentReport(Request $request)
    {
        $code = $request->input('code');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $settingDate = Setting::value('value');

        $query = Salesadjustment::query();

        // If user did NOT select a date range → use settingDate filter
        if (!$startDate && !$endDate) {
            $query->whereDate('Date', $settingDate);
        } else {
            // If date range is provided → filter using Date column
            if ($startDate) {
                $query->whereDate('Date', '>=', $startDate);
            }
            if ($endDate) {
                $query->whereDate('Date', '<=', $endDate);
            }
        }

        // Apply code filter if provided
        if ($code) {
            $query->where('code', $code);
        }

        // Final results with pagination
        $entries = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'entries' => $entries,
            'code' => $code,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'filters' => $request->all(),
        ]);
    }
    public function loanReport()
    {
        $allLoans = CustomersLoan::all();
        $groupedLoans = $allLoans->groupBy('customer_short_name');

        // Get all customers with Non realized status from IncomeExpenses
        $nonRealizedCustomers = IncomeExpenses::where('status', 'Non realized')
            ->where('settling_way', 'Cheque')
            ->pluck('customer_short_name')
            ->toArray();

        $finalLoans = [];

        foreach ($groupedLoans as $customerShortName => $loans) {
            $lastOldLoan = $loans->where('loan_type', 'old')->sortByDesc('created_at')->first();
            $firstTodayAfterOld = $loans->where('loan_type', 'today')
                ->where('created_at', '>', $lastOldLoan->created_at ?? '1970-01-01')
                ->sortBy('created_at')
                ->first();

            $highlightColor = null;

            if ($lastOldLoan && $firstTodayAfterOld) {
                $daysBetweenLoans = Carbon::parse($lastOldLoan->created_at)
                    ->diffInDays(Carbon::parse($firstTodayAfterOld->created_at));

                if ($daysBetweenLoans > 30) {
                    $highlightColor = 'red-highlight';
                } elseif ($daysBetweenLoans >= 14 && $daysBetweenLoans <= 30) {
                    $highlightColor = 'blue-highlight';
                }

                $extraTodayLoanExists = $loans->where('loan_type', 'today')
                    ->where('created_at', '>', $firstTodayAfterOld->created_at)
                    ->count() > 0;

                if ($extraTodayLoanExists) {
                    $highlightColor = null;
                }
            } elseif ($lastOldLoan && !$firstTodayAfterOld) {
                $daysSinceLastOldLoan = Carbon::parse($lastOldLoan->created_at)
                    ->diffInDays(Carbon::now());

                if ($daysSinceLastOldLoan > 30) {
                    $highlightColor = 'red-highlight';
                } elseif ($daysSinceLastOldLoan >= 14 && $daysSinceLastOldLoan <= 30) {
                    $highlightColor = 'blue-highlight';
                }
            }

            $totalToday = $loans->where('loan_type', 'today')->sum('amount');
            $totalOld = $loans->where('loan_type', 'old')->sum('amount');
            $totalAmount = $totalToday - $totalOld;

            // If this customer has Non realized cheques → force orange highlight
            if (in_array($customerShortName, $nonRealizedCustomers)) {
                $highlightColor = 'orange-highlight';
            }

            $finalLoans[] = [
                'customer_short_name' => $customerShortName,
                'total_amount' => $totalAmount,
                'highlight_color' => $highlightColor,
            ];
        }

        // Get company settings
        $companyName = Setting::value('CompanyName') ?? 'Default Company';
        $settingDate = Setting::value('value') ?? now()->format('Y-m-d');

        return response()->json([
            'loans' => $finalLoans,
            'companyName' => $companyName,
            'settingDate' => $settingDate
        ]);
    }
public function fetchGrnCodes()
{
    $codes = GrnEntry::pluck('code')->unique()->values();
    
    return response()->json([
        'codes' => $codes
    ]);
}

public function grnReport2(Request $request)
{
    $code = $request->input('code');

    $grnQuery = GrnEntry::query();
    if ($code) {
        $grnQuery->where('code', $code);
    }
    $grnEntries = $grnQuery->get();

    $groupedData = [];

    foreach ($grnEntries as $entry) {
        // --- Current + Historical Sales ---
        $currentSales = Sale::where('code', $entry->code)->get([
            'code',
            'customer_code',
            'item_code',
            'supplier_code',
            'weight',
            'price_per_kg',
            'total',
            'packs',
            'item_name',
            'Date',
            'bill_no',
        ])->map(function ($sale) {
            $sale->type = 'Sales';
            return $sale;
        });

        $historicalSales = SalesHistory::where('code', $entry->code)->get([
            'code',
            'customer_code',
            'item_code',
            'supplier_code',
            'weight',
            'price_per_kg',
            'total',
            'packs',
            'item_name',
            'Date',
            'bill_no',
        ])->map(function ($sale) {
            $sale->type = 'Sales';
            return $sale;
        });

        $sales = $currentSales->concat($historicalSales);

        // --- GRN Transactions ---
        $grnTransactions = GrnEntry2::where('code', $entry->code)->get()->map(function ($txn) {
            return (object) [
                'Date' => $txn->txn_date,
                'type' => 'GRN',
                'bill_no' => '-',
                'customer_code' => '-',
                'weight' => $txn->weight,
                'price_per_kg' => '-',
                'packs' => $txn->packs,
                'total' => '-',
            ];
        });

        // --- Merge and mix Sales + GRN by date ---
        $allRows = $sales->concat($grnTransactions)
            ->map(function ($row) {
                $row->sort_date = $row->Date ?? now();
                return $row;
            })
            ->sortByDesc('sort_date')
            ->values();

        // --- Totals ---
        $totalSales = $sales->sum('total');
        $damageValue = $entry->wasted_weight * $entry->PerKGPrice;

        $groupedData[$entry->code] = [
            'purchase_price' => $entry->total_grn,
            'item_name' => $entry->item_name,
            'all_rows' => $allRows,
            'damage' => [
                'wasted_packs' => $entry->wasted_packs,
                'wasted_weight' => $entry->wasted_weight,
                'damage_value' => $damageValue,
            ],
            'profit' => $entry->total_grn - $totalSales - $damageValue,
            'updated_at' => $entry->updated_at,
            'remaining_packs' => $entry->packs,
            'remaining_weight' => $entry->weight,
            'totalOriginalPacks' => $entry->original_packs,
            'totalOriginalWeight' => $entry->original_weight,
        ];
    }

    return response()->json([
        'groupedData' => $groupedData,
        'selectedCode' => $code,
    ]);
}
    public function financialReport()
    {
        // Fetch today's Income/Expenses records
        $records = IncomeExpenses::select('customer_short_name', 'supplier_code', 'bill_no', 'description', 'amount', 'loan_type')
            ->whereDate('created_at', Carbon::today())
            ->get();

        $reportData = [];
        $totalDr = 0;
        $totalCr = 0;

        // 🆕 Fetch last_day_started_date row (contains Balance column)
        $balanceRow = Setting::where('key', 'last_day_started_date')
            ->whereColumn('value', '<>', 'Date_of_balance')
            ->first();

        if ($balanceRow) {
            $reportData[] = [
                'description' => 'Balance As At ' . \Carbon\Carbon::parse($balanceRow->value)->format('Y-m-d'),
                'dr' => $balanceRow->Balance, // Use the Balance column
                'cr' => null
            ];
            $totalDr += $balanceRow->Balance; // Add Balance to total DR
        }

        // Loop through Income/Expenses records
        foreach ($records as $record) {
            $dr = null;
            $cr = null;

            // Build description
            $desc = $record->customer_short_name ?? $record->supplier_code;
            if (!empty($record->bill_no)) {
                $desc .= " ({$record->bill_no})";
            }
            $desc .= " - {$record->description}";

            // Determine DR or CR based on loan_type
            if (in_array($record->loan_type, ['old', 'farmer_old', 'ingoing'])) {
                $dr = $record->amount;
                $totalDr += $record->amount;
            } elseif (in_array($record->loan_type, ['today', 'farmer_today', 'outgoing'])) {
                $cr = $record->amount;
                $totalCr += $record->amount;
            }

            $reportData[] = [
                'description' => $desc,
                'dr' => $dr,
                'cr' => $cr
            ];
        }

        // Add Sales total
        $salesTotal = Sale::sum('total');
        $totalDr += $salesTotal;
        $reportData[] = [
            'description' => 'Sales Total',
            'dr' => $salesTotal,
            'cr' => null
        ];

        // Get Profit from SellingKGTotal
        $profitTotal = Sale::sum('SellingKGTotal');

        // Calculate Total Damages
        $totalDamages = GrnEntry::select(DB::raw('SUM(wasted_weight * PerKGPrice)'))
            ->value(DB::raw('SUM(wasted_weight * PerKGPrice)'));
        $totalDamages = $totalDamages ?? 0;

        return view('dashboard.reports.financial', compact(
            'reportData',
            'totalDr',
            'totalCr',
            'salesTotal',
            'profitTotal',
            'totalDamages'
        ));
    }
    public function getSuppliers()
    {
        $suppliers = Supplier::select('code', 'name')->get();
        return response()->json(['suppliers' => $suppliers]);
    }

    public function getCustomers()
    {
        $customers = Sale::select('customer_code')->distinct()->get();
        return response()->json(['customers' => $customers]);
    }

    public function getBillNumbers()
    {
        $billNos = Sale::select('bill_no')->whereNotNull('bill_no')->where('bill_no', '<>', '')->distinct()->get();
        return response()->json(['billNos' => $billNos]);
    }

    public function getCompanyInfo()
    {
        $companyName = Setting::value('CompanyName') ?? 'Default Company';
        $settingDate = Setting::value('value');
        $formattedDate = $settingDate ? Carbon::parse($settingDate)->format('Y-m-d') : Carbon::now()->format('Y-m-d');

        return response()->json([
            'companyName' => $companyName,
            'settingDate' => $formattedDate
        ]);
    }

 /**
     * Sales report (customer side).
     *
     * Data source:
     *  - No date filter  -> today's `sales` table.
     *  - Any date filter -> `sales_histories` (records moved there at day end).
     *
     * Filters (all optional): start_date, end_date (Y-m-d), customer_code, item_code,
     * supplier_code, bill_no, transaction_type (cash|credit), bill_status (printed|not_printed).
     *
     * Line total, the same as the printed customer bill:
     *   weight x price_per_kg + packs x CustomerPackCost + Kuliya
     *
     * Rows are capped at SALES_REPORT_MAX_ROWS; the summary always covers every
     * matching record, so totals stay correct even when the rows are truncated.
     */
    private const SALES_REPORT_MAX_ROWS = 20000;

    public function salesReport(Request $request)
    {
        $validated = $request->validate([
            'start_date'       => 'nullable|date_format:Y-m-d',
            'end_date'         => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'customer_code'    => 'nullable|string|max:100',
            'item_code'        => 'nullable|string|max:100',
            'supplier_code'    => 'nullable|string|max:100',
            'bill_no'          => 'nullable|string|max:100',
            'transaction_type' => 'nullable|in:cash,credit',
            'bill_status'      => 'nullable|in:printed,not_printed',
        ]);

        $useHistory = $request->filled('start_date') || $request->filled('end_date');
        $model = $useHistory ? new SalesHistory() : new Sale();
        $table = $model->getTable();

        $query = $model->newQuery()->where('Processed', 'Y');

        if ($request->filled('customer_code')) {
            $query->whereRaw('UPPER(customer_code) = ?', [strtoupper(trim($validated['customer_code']))]);
        }
        if ($request->filled('item_code')) {
            $query->where('item_code', trim($validated['item_code']));
        }
        if ($request->filled('supplier_code')) {
            $query->whereRaw('UPPER(supplier_code) = ?', [strtoupper(trim($validated['supplier_code']))]);
        }
        if ($request->filled('bill_no')) {
            $query->where('bill_no', trim($validated['bill_no']));
        }
        if ($request->filled('transaction_type')) {
            $query->where('credit_transaction', $validated['transaction_type'] === 'credit' ? 'Y' : 'N');
        }
        if ($request->filled('bill_status')) {
            $query->where('bill_printed', $validated['bill_status'] === 'printed' ? 'Y' : 'N');
        }
        if ($request->filled('start_date')) {
            $query->where('Date', '>=', $validated['start_date']);
        }
        if ($request->filled('end_date')) {
            $query->where('Date', '<=', $validated['end_date']);
        }

        // Summary over every matching record (not just the rows returned)
        $lineValue  = 'COALESCE(weight,0) * COALESCE(price_per_kg,0)';
        $lineBags   = 'COALESCE(packs,0) * COALESCE(CustomerPackCost,0)';
        $lineKuliya = 'COALESCE(Kuliya,0)';
        $summaryRow = (clone $query)->selectRaw("
                COUNT(*) AS record_count,
                COUNT(DISTINCT UPPER(customer_code)) AS customer_count,
                COUNT(DISTINCT CONCAT(UPPER(COALESCE(customer_code,'')), '|', COALESCE(Date,''), '|', COALESCE(bill_no,''))) AS bill_count,
                COUNT(DISTINCT item_code) AS item_count,
                COALESCE(SUM(weight),0) AS total_weight,
                COALESCE(SUM(packs),0) AS total_packs,
                COALESCE(SUM($lineValue),0) AS items_value,
                COALESCE(SUM($lineBags),0) AS bag_cost,
                COALESCE(SUM($lineKuliya),0) AS kuliya,
                MIN(Date) AS first_date,
                MAX(Date) AS last_date
            ")->first();

        $summary = [
            'record_count'   => (int) $summaryRow->record_count,
            'customer_count' => (int) $summaryRow->customer_count,
            'bill_count'     => (int) $summaryRow->bill_count,
            'item_count'     => (int) $summaryRow->item_count,
            'total_weight'   => round((float) $summaryRow->total_weight, 3),
            'total_packs'    => (int) $summaryRow->total_packs,
            'items_value'    => round((float) $summaryRow->items_value, 2),
            'bag_cost'       => round((float) $summaryRow->bag_cost, 2),
            'kuliya'         => round((float) $summaryRow->kuliya, 2),
            'first_date'     => $summaryRow->first_date,
            'last_date'      => $summaryRow->last_date,
        ];
        $summary['grand_total'] = round($summary['items_value'] + $summary['bag_cost'] + $summary['kuliya'], 2);

        $salesData = $query
            ->select([
                'id', 'Date', 'customer_code', 'customer_name', 'bill_no', 'item_code', 'item_name',
                'supplier_code', 'weight', 'price_per_kg', 'packs', 'CustomerPackCost', 'Kuliya',
                'total', 'bill_printed', 'credit_transaction',
            ])
            ->orderBy('Date', 'DESC')
            ->orderBy('customer_code')
            ->orderBy('bill_no')
            ->orderBy('id')
            ->limit(self::SALES_REPORT_MAX_ROWS)
            ->get();

        return response()->json([
            'salesData' => $salesData,
            'summary'   => $summary,
            'source'    => $useHistory ? 'history' : 'sales',
            'table'     => $table,
            'truncated' => $summary['record_count'] > $salesData->count(),
            'max_rows'  => self::SALES_REPORT_MAX_ROWS,
            'filters'   => $validated,
        ]);
    }

    /**
     * Options for the sales report filters: customer codes and suppliers seen in
     * today's sales and the sales history, plus the item master list.
     */
    public function salesReportFilterOptions()
    {
        $customerCodes = Sale::query()->whereNotNull('customer_code')->where('customer_code', '<>', '')
            ->distinct()->pluck('customer_code')
            ->merge(SalesHistory::query()->whereNotNull('customer_code')->where('customer_code', '<>', '')
                ->distinct()->pluck('customer_code'))
            ->map(fn ($c) => strtoupper(trim($c)))
            ->unique()->sort()->values();

        $names = \App\Models\Customer::query()
            ->whereIn(\DB::raw('UPPER(short_name)'), $customerCodes->all())
            ->pluck('name', 'short_name')
            ->mapWithKeys(fn ($name, $code) => [strtoupper(trim($code)) => $name]);

        $customers = $customerCodes->map(fn ($code) => [
            'code' => $code,
            'name' => $names[$code] ?? null,
        ])->values();

        $supplierCodes = Sale::query()->whereNotNull('supplier_code')->where('supplier_code', '<>', '')
            ->distinct()->pluck('supplier_code')
            ->merge(SalesHistory::query()->whereNotNull('supplier_code')->where('supplier_code', '<>', '')
                ->distinct()->pluck('supplier_code'))
            ->map(fn ($c) => strtoupper(trim($c)))
            ->unique()->sort()->values();

        $supplierNames = Supplier::query()->pluck('name', 'code')
            ->mapWithKeys(fn ($name, $code) => [strtoupper(trim($code)) => $name]);

        $suppliers = $supplierCodes->map(fn ($code) => [
            'code' => $code,
            'name' => $supplierNames[$code] ?? null,
        ])->values();

        $items = Item::query()->select('no', 'type')->orderBy('no')->get()
            ->map(fn ($item) => ['code' => (string) $item->no, 'name' => $item->type])
            ->values();

        return response()->json([
            'customers' => $customers,
            'suppliers' => $suppliers,
            'items'     => $items,
        ]);
    }

    public function grnReport(Request $request)
    {
        $code = $request->input('code');

        $grnQuery = GrnEntry::query();
        if ($code) {
            $grnQuery->where('code', $code);
        }
        $grnEntries = $grnQuery->get();

        $groupedData = [];

        foreach ($grnEntries as $entry) {
            // --- Current + Historical Sales ---
            $currentSales = Sale::where('code', $entry->code)->get([
                'code',
                'customer_code',
                'item_code',
                'supplier_code',
                'weight',
                'price_per_kg',
                'total',
                'packs',
                'item_name',
                'Date',
                'bill_no',
            ])->map(function ($sale) {
                $sale->type = 'Sales';
                return $sale;
            });

            $historicalSales = SalesHistory::where('code', $entry->code)->get([
                'code',
                'customer_code',
                'item_code',
                'supplier_code',
                'weight',
                'price_per_kg',
                'total',
                'packs',
                'item_name',
                'Date',
                'bill_no',
            ])->map(function ($sale) {
                $sale->type = 'Sales';
                return $sale;
            });

            $sales = $currentSales->concat($historicalSales);

            // --- GRN Transactions ---
            $grnTransactions = GrnEntry2::where('code', $entry->code)->get()->map(function ($txn) {
                return (object) [
                    'Date' => $txn->txn_date,
                    'type' => 'GRN',
                    'bill_no' => '-',
                    'customer_code' => '-',
                    'weight' => $txn->weight,
                    'price_per_kg' => '-',
                    'packs' => $txn->packs,
                    'total' => '-',
                ];
            });

            // --- Merge and mix Sales + GRN by date ---
            $allRows = $sales->concat($grnTransactions)
                ->map(function ($row) {
                    $row->sort_date = $row->Date ?? now();
                    return $row;
                })
                ->sortByDesc('sort_date')
                ->values();

            // --- Totals ---
            $totalSales = $sales->sum('total');
            $damageValue = $entry->wasted_weight * $entry->PerKGPrice;

            $groupedData[$entry->code] = [
                'purchase_price' => $entry->total_grn,
                'item_name' => $entry->item_name,
                'all_rows' => $allRows,
                'damage' => [
                    'wasted_packs' => $entry->wasted_packs,
                    'wasted_weight' => $entry->wasted_weight,
                    'damage_value' => $damageValue,
                ],
                'profit' => $entry->total_grn - $totalSales - $damageValue,
                'updated_at' => $entry->updated_at,
                'remaining_packs' => $entry->packs,
                'remaining_weight' => $entry->weight,
                'totalOriginalPacks' => $entry->original_packs,
                'totalOriginalWeight' => $entry->original_weight,
            ];
        }

        return view('dashboard.reports.grn', [
            'groupedData' => $groupedData,
            'selectedCode' => $code,
        ]);
    }
    public function sendDailyReport()
    {
        // Group by item_code and aggregate
        $sales = Sale::select('item_code', 'item_name')
            ->selectRaw('SUM(packs) as packs')
            ->selectRaw('SUM(weight) as weight')
            ->selectRaw('SUM(total) as total')
            ->groupBy('item_code', 'item_name')
            ->get();

        $reportData = [
            'sales' => $sales,
            'settingDate' => now()->format('Y-m-d')
        ];

        // Send email to multiple recipients
        Mail::to(['nethmavilhan2005@gmail.com', 'thrcorner@gmail.com', 'wey.b32@gmail.com'])
            ->send(new DailyReportMail($reportData));

        // Stay on the same page with a success message
        return back()->with('success', 'Daily report email sent successfully!');
    }

    public function emailChangesReport()
    {
        $settingDate = Setting::value('value');

        $query = Salesadjustment::query();

        // Always filter by the setting date (like your web report default)
        $query->whereDate('Date', $settingDate);

        // Final results
        $entries = $query->orderBy('created_at', 'desc')->get();

        // Send the email to both recipients (no grouping)
        Mail::to(['thrcorner@gmail.com', 'nethmavilhan2005@gmail.com', 'wey.b32@gmail.com'])
            ->send(new ChangeReportMail($entries, $settingDate));

        return redirect()->back()->with('success', 'Changes report email sent successfully!');
    }

    public function emailTotalSalesReport()
    {
        // Fetch the same data you would for your web report.
        $sales = Sale::all(); // Or your filtered query
        $grandTotal = $sales->sum('total');

        // Send the email to both recipients
        Mail::to(['thrcorner@gmail.com', 'nethmavilhan2005@gmail.com', 'wey.b32@gmail.com'])
            ->send(new TotalSalesReportMail($sales, $grandTotal));

        // Redirect back with a success message
        return redirect()->back()->with('success', 'Total sales report email sent successfully!');
    }

    public function emailBillSummaryReport(Request $request)
    {
        // Start with the base query, exactly as in your salesReport method
        $query = Sale::query()->whereNotNull('bill_no')->where('bill_no', '<>', '');

        // Apply all the same filters from the salesReport method
        if ($request->filled('supplier_code')) {
            $query->where('supplier_code', $request->supplier_code);
        }

        if ($request->filled('item_code')) {
            $query->where('item_code', $request->item_code);
        }

        if ($request->filled('customer_short_name')) {
            $search = $request->customer_short_name;
            $query->where(function ($q) use ($search) {
                $q->where('customer_code', 'like', '%' . $search . '%')
                    ->orWhereIn('customer_code', function ($sub) use ($search) {
                        $sub->select('short_name')
                            ->from('customers')
                            ->where('name', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($request->filled('customer_code')) {
            $query->where('customer_code', $request->customer_code);
        }

        if ($request->filled('bill_no')) {
            $query->where('bill_no', $request->bill_no);
        }

        // Fetch the filtered sales data and group it by bill number
        $salesByBill = $query->get()->groupBy('bill_no');

        // Calculate the grand total
        $grandTotal = $salesByBill->sum(function ($sales) {
            return $sales->sum('total');
        });

        // Send the email to both recipients
        Mail::to(['thrcorner@gmail.com', 'nethmavilhan2005@gmail.com', 'wey.b32@gmail.com'])
            ->send(new BillSummaryReportMail($salesByBill, $grandTotal));

        // Redirect back with a success message
        return redirect()->back()->with('success', 'Bill summary report email sent successfully!');
    }

    public function emailCreditReport(Request $request)
    {
        // Fetch loans. You can add filtering logic here if your original report page has filters.
        $settingDate = Setting::value('value');
        $loans = CustomersLoan::query()
            ->whereDate('Date', $settingDate);

        // Calculate totals, replicating the logic from your Blade file
        $receivedTotal = 0;
        $paidTotal = 0;
        foreach ($loans as $loan) {
            if ($loan->loan_type === 'old') {
                $receivedTotal += $loan->amount;
            } elseif ($loan->loan_type === 'today') {
                $paidTotal += $loan->amount;
            }
        }

        $netBalance = $paidTotal - $receivedTotal;

        // Send the email to both recipients
        Mail::to(['thrcorner@gmail.com', 'nethmavilhan2005@gmail.com', 'wey.b32@gmail.com'])
            ->send(new CreditReportMail($loans, $receivedTotal, $paidTotal, $netBalance));

        // Redirect back with a success message
        return redirect()->back()->with('success', 'Credit report email sent successfully!');
    }

    public function emailItemWiseReport(Request $request)
    {
        $query = Sale::query();

        // Apply any filters from the request to the query
        if ($request->filled('item_code')) {
            $query->where('item_code', $request->item_code);
        }

        $sales = $query->get();

        // Calculate totals
        $total_packs = $sales->sum('packs');
        $total_weight = $sales->sum('weight');
        $total_amount = $sales->sum('total');

        Mail::to(['thrcorner@gmail.com', 'nethmavilhan2005@gmail.com', 'wey.b32@gmail.com'])
            ->send(new ItemWiseReportMail($sales, $total_packs, $total_weight, $total_amount));

        // Redirect back with a success message
        return redirect()->back()->with('success', 'Item-wise report email sent successfully!');
    }
    public function emailGrnSalesReport(Request $request)
    {
        // Fetch all sales as there are no filters
        $sales = Sale::all();

        // Calculate totals
        $total_packs = $sales->sum('packs');
        $total_weight = $sales->sum('weight');
        $total_amount = $sales->sum('total');

        // Send the email to both addresses
        Mail::to(['thrcorner@gmail.com', 'nethmavilhan2005@gmail.com', 'wey.b32@gmail.com'])
            ->send(new GrnSalesReportMail($sales, $total_packs, $total_weight, $total_amount));

        // Redirect back with a success message
        return back()->with('success', 'GRN sales report email sent successfully!');
    }

    public function emailSupplierSalesReport(Request $request)
    {
        // Fetch all GRN entries
        $grnEntries = GrnEntry::all();
        $reportData = [];

        foreach ($grnEntries->groupBy('code') as $code => $entries) {
            // --- GRN Totals ---
            $totalOriginalPacks = $entries->sum('original_packs');
            $totalOriginalWeight = $entries->sum('original_weight');

            $remainingPacks = $entries->sum('packs');
            $remainingWeight = $entries->sum('weight');

            // --- Sold quantities ---
            $totalSoldPacks = $totalOriginalPacks - $remainingPacks;
            $totalSoldWeight = $totalOriginalWeight - $remainingWeight;

            // --- Total sales value ---
            $currentSales = Sale::where('code', $code)->get();
            $historicalSales = SalesHistory::where('code', $code)->get();
            $relatedSales = $currentSales->merge($historicalSales);
            $totalSalesValueForGrn = $relatedSales->sum('total');

            $reportData[] = [
                'grn_code' => $code,
                'item_name' => $entries->first()->item_name,
                'original_packs' => $totalOriginalPacks,
                'original_weight' => $totalOriginalWeight,
                'sold_packs' => $totalSoldPacks,
                'sold_weight' => $totalSoldWeight,
                'total_sales_value' => $totalSalesValueForGrn,
                'remaining_packs' => $remainingPacks,
                'remaining_weight' => $remainingWeight,
            ];
        }

        // Send the email to both addresses
        Mail::to(['thrcorner@gmail.com', 'nethmavilhan2005@gmail.com', 'wey.b32@gmail.com'])
            ->send(new SupplierSalesReportMail(collect($reportData)));

        return back()->with('success', 'Supplier sales report email sent successfully!');
    }

    // Example method to get report data (adjust based on your logic)
    private function getSupplierReportData()
    {

        $reportData = []; // Replace with your actual data fetching logic.
        return $reportData;
    }
    public function emailOverviewReport(Request $request)
    {
        // Fetch all GRN entries
        $grnEntries = GrnEntry::all();
        $reportData = [];

        // Group by item_name
        $grouped = $grnEntries->groupBy('item_name');

        foreach ($grouped as $itemName => $entries) {
            $originalPacks = 0;
            $originalWeight = 0;
            $soldPacks = 0;
            $soldWeight = 0;
            $remainingPacks = 0;
            $remainingWeight = 0;

            foreach ($entries as $grnEntry) {
                // Fetch current and historical sales for this GRN code
                $currentSales = Sale::where('code', $grnEntry->code)->get();
                $historicalSales = SalesHistory::where('code', $grnEntry->code)->get();
                $relatedSales = $currentSales->merge($historicalSales);

                // Sum original packs and weight
                $originalPacks += $grnEntry->original_packs;
                $originalWeight += $grnEntry->original_weight;

                // Sum sold packs and weight
                $soldPacks += $grnEntry->original_packs - $grnEntry->packs;
                $soldWeight += $grnEntry->original_weight - $grnEntry->weight;

                // Sum remaining packs and weight (direct from GRN entry)
                $remainingPacks += $grnEntry->packs;
                $remainingWeight += $grnEntry->weight;
            }

            $reportData[] = [
                'item_name' => $itemName,
                'original_packs' => $originalPacks,
                'original_weight' => $originalWeight,
                'sold_packs' => $soldPacks,
                'sold_weight' => $soldWeight,
                'remaining_packs' => $remainingPacks,
                'remaining_weight' => $remainingWeight,
            ];
        }

        // Send the email to both addresses
        Mail::to(['thrcorner@gmail.com', 'nethmavilhan2005@gmail.com', 'wey.b32@gmail.com'])
            ->send(new GrnSalesOverviewMail(collect($reportData)));

        return back()->with('success', 'Overview report email sent successfully!');
    }

    public function salesfinalReport(Request $request)
    {
        $query = Sale::query()->whereNotNull('bill_no')->where('bill_no', '<>', '');

        // Supplier filter
        if ($request->filled('supplier_code')) {
            $query->where('supplier_code', $request->supplier_code);
        }

        // Item filter
        if ($request->filled('item_code')) {
            $query->where('item_code', $request->item_code);
        }

        // Customer short name filter
        if ($request->filled('customer_short_name')) {
            $search = $request->customer_short_name;
            $query->where(function ($q) use ($search) {
                $q->where('customer_code', 'like', '%' . $search . '%')
                    ->orWhereIn('customer_code', function ($sub) use ($search) {
                        $sub->select('short_name')
                            ->from('customers')
                            ->where('name', 'like', '%' . $search . '%');
                    });
            });
        }

        // Customer code filter
        if ($request->filled('customer_code')) {
            $query->where('customer_code', $request->customer_code);
        }

        // Bill No filter
        if ($request->filled('bill_no')) {
            $query->where('bill_no', $request->bill_no);
        }

        $salesByBill = $query->get()->groupBy('bill_no');

        // Calculate grand total
        $grandTotal = $salesByBill->sum(function ($billSales) {
            return $billSales->sum('total');
        });

        // Send the email to both addresses
        try {
            Mail::to(['thrcorner@gmail.com', 'nethmavilhan2005@gmail.com', 'wey.b32@gmail.com'])
                ->send(new SalesReportMail($salesByBill, $grandTotal));

            return back()->with('success', 'Sales report email sent successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to send email. ' . $e->getMessage());
        }
    }

    public function sendFinancialReportEmail()
    {
        // Initialize all variables at the start
        $reportData = [];
        $totalDr = 0;
        $totalCr = 0;

        // Fetch records from the database
        $settingDate = Setting::value('value');

        $records = IncomeExpenses::select('customer_short_name', 'supplier_code', 'bill_no', 'description', 'amount', 'loan_type')
            ->whereDate('created_at', $settingDate)
            ->get();

        // The foreach loop will be skipped if $records is empty
        foreach ($records as $record) {
            $dr = null;
            $cr = null;

            // Use null coalescing to provide default values for potentially null fields
            $customerShortName = $record->customer_short_name ?? $record->supplier_code ?? 'N/A';
            $billNo = $record->bill_no ?? '';
            $itemDescription = $record->description ?? 'No Description';
            $amount = $record->amount ?? 0;
            $loanType = $record->loan_type ?? '';

            $desc = $customerShortName;
            if (!empty($billNo)) {
                $desc .= " ({$billNo})";
            }
            $desc .= " - {$itemDescription}";

            if (in_array($loanType, ['old', 'farmer_old', 'ingoing'])) {
                $dr = $amount;
                $totalDr += $amount;
            } elseif (in_array($loanType, ['today', 'farmer_today', 'outgoing'])) {
                $cr = $amount;
                $totalCr += $amount;
            }

            $reportData[] = [
                'description' => $desc,
                'dr' => $dr,
                'cr' => $cr
            ];
        }

        // Add Sales total
        $salesTotal = Sale::sum('total') ?? 0;
        $totalDr += $salesTotal;
        $reportData[] = [
            'description' => 'Sales Total',
            'dr' => $salesTotal,
            'cr' => null
        ];

        // Get Profit and Damages, with fallbacks
        $profitTotal = Sale::sum('SellingKGTotal') ?? 0;
        $totalDamages = GrnEntry::sum(DB::raw('wasted_weight * PerKGPrice')) ?? 0;

        // Log the data to find the problematic array entry
        Log::info('Report Data for Email:', ['data' => $reportData]);

        $data = compact('reportData', 'totalDr', 'totalCr', 'salesTotal', 'profitTotal', 'totalDamages');

        // Send the email to both addresses
        Mail::to(['thrcorner@gmail.com', 'nethmavilhan2005@gmail.com', 'wey.b32@gmail.com'])
            ->send(new FinancialReportMail($data));

        return back()->with('success', 'Financial report emailed successfully!');
    }

    // NEW: Method to send the email without filters
    public function sendLoanReportEmail()
    {
        $settingDate = Setting::value('value');
        if (!$settingDate) {
            $settingDate = now()->toDateString();
        }

        // Fetch all loans for the specified date without additional filters
        $loans = CustomersLoan::whereDate('Date', $settingDate)
            ->orderBy('Date', 'desc')
            ->get();

        // Send to multiple email addresses
        Mail::to(['thrcorner@gmail.com', 'nethmavilhan2005@gmail.com', 'wey.b32@gmail.com'])
            ->send(new LoanReportMail($loans));

        return back()->with('success', 'Loan report emailed successfully!');
    }
    // salesadjustment exports


    private function prepareGrnSalesOverviewData()
    {
        $grnEntries = GrnEntry::all();
        $reportData = [];

        foreach ($grnEntries->groupBy('code') as $code => $entries) {
            $totalOriginalPacks = $entries->sum('original_packs');
            $totalOriginalWeight = $entries->sum('original_weight');

            // Get all related sales first
            $currentSales = Sale::where('code', $code)->get();
            $historicalSales = SalesHistory::where('code', $code)->get();
            $relatedSales = $currentSales->merge($historicalSales);

            // Now calculate sold totals
            $totalSoldPacks = $relatedSales->sum('packs');
            $totalSoldWeight = $relatedSales->sum('weight');
            $totalSalesValueForGrn = $relatedSales->sum('total');

            // Remaining
            $remainingPacks = $totalOriginalPacks - $totalSoldPacks;
            $remainingWeight = $totalOriginalWeight - $totalSoldWeight;

            $reportData[] = [
                'date' => Carbon::parse($entries->first()->created_at)->timezone('Asia/Colombo')->format('Y-m-d H:i:s'),
                'grn_code' => $code,
                'item_name' => $entries->first()->item_name,
                'price' => $entries->first()->SalesKGPrice,
                'original_packs' => $totalOriginalPacks,
                'original_weight' => $totalOriginalWeight,
                'sold_packs' => $totalSoldPacks,
                'sold_weight' => $totalSoldWeight,
                'total_sales_value' => $totalSalesValueForGrn,
                'remaining_packs' => $remainingPacks,
                'remaining_weight' => $remainingWeight,
            ];
        }
        $reportData = collect($reportData)->sortBy('grn_code')->values();


        return $reportData;
    }

    public function sendGrnEmail(Request $request)
    {
        $code = $request->input('code');

        $grnEntries = GrnEntry::when($code, fn($q) => $q->where('code', $code))->get();

        $groupedData = [];

        foreach ($grnEntries as $entry) {
            $sales = Sale::where('code', $entry->code)->get([
                'code',
                'customer_code',
                'item_code',
                'supplier_code',
                'weight',
                'price_per_kg',
                'total',
                'packs',
                'item_name',
                'Date',
                'bill_no'
            ]);

            if ($sales->isEmpty()) {
                $sales = SalesHistory::where('code', $entry->code)->get([
                    'code',
                    'customer_code',
                    'item_code',
                    'supplier_code',
                    'weight',
                    'price_per_kg',
                    'total',
                    'packs',
                    'item_name',
                    'Date',
                    'bill_no'
                ]);
            }

            $damageValue = $entry->wasted_weight * $entry->PerKGPrice;

            $totalSoldPacks = $sales->sum('packs');
            $totalSoldWeight = $sales->sum('weight');

            $groupedData[$entry->code] = [
                'purchase_price' => $entry->total_grn,
                'item_name' => $entry->item_name,
                'sales' => $sales,
                'damage' => [
                    'wasted_packs' => $entry->wasted_packs,
                    'wasted_weight' => $entry->wasted_weight,
                    'damage_value' => $damageValue
                ],
                'profit' => $entry->total_grn - $sales->sum('total') - $damageValue,
                'updated_at' => $entry->updated_at,
                'remaining_packs' => $entry->original_packs - $totalSoldPacks,
                'remaining_weight' => $entry->original_weight - $totalSoldWeight,
                'totalOriginalPacks' => $entry->original_packs,
                'totalOriginalWeight' => $entry->original_weight,
            ];
        }

        Mail::to(['thrcorner@gmail.com', 'nethmavilhan2005@gmail.com', 'wey.b32@gmail.com'])
            ->send(new GrnbladeReportMail($groupedData));

        return back()->with('success', 'GRN Report has been sent successfully!');
    }

    public function returnsReport()
    {
        $data = IncomeExpenses::select(
            'GRN_Code',
            'Item_Code',
            'bill_no',
            'weight',
            'packs',
            'Reason'
        )
            ->whereNotNull('Reason') // only records with a value in Reason
            ->get();

        return view('dashboard.reports.returns_report', compact('data'));
    }
    public function chequePaymentsReport(Request $request)
    {
        $query = IncomeExpenses::whereNotNull('cheque_no')
            ->where('cheque_no', '<>', '');

        // Apply date range filter if provided
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('cheque_date', [
                $request->start_date,
                $request->end_date
            ]);
        }

        $chequePayments = $query->orderBy('cheque_date', 'desc')->get();

        // Pass start/end dates back to the view to retain values
        return view('dashboard.reports.cheque_payments', [
            'chequePayments' => $chequePayments,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date
        ]);
    }
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:Realized,Non realized,Return',
        ]);

        $payment = IncomeExpenses::findOrFail($id);
        $payment->status = $request->status;
        $payment->save();

        return response()->json(['success' => true, 'status' => $payment->status]);
    }
    public function generateReport2()
    {
        $grnEntries = GrnEntry::all();
        $dayStartReportData = [];

        // --- Day Start Report ---
        foreach ($grnEntries->groupBy('code') as $code => $entries) {
            $totalOriginalPacks = $entries->sum('original_packs');
            $totalOriginalWeight = $entries->sum('original_weight');

            $remainingPacks = $entries->sum('packs');
            $remainingWeight = $entries->sum('weight');

            $totalSoldPacks = $totalOriginalPacks - $remainingPacks;
            $totalSoldWeight = $totalOriginalWeight - $remainingWeight;

            $currentSales = Sale::where('code', $code)->get();
            $historicalSales = SalesHistory::where('code', $code)->get();
            $relatedSales = $currentSales->merge($historicalSales);
            $totalSalesValue = $relatedSales->sum('total');

            $totalWastedPacks = $entries->sum('wasted_packs');
            $totalWastedWeight = $entries->sum('wasted_weight');

            $dayStartReportData[] = [
                'date' => Carbon::parse($entries->first()->created_at)
                    ->timezone('Asia/Colombo')
                    ->format('Y-m-d H:i:s'),
                'grn_code' => $code,
                'item_name' => $entries->first()->item_name,
                'original_packs' => $totalOriginalPacks,
                'original_weight' => $totalOriginalWeight,
                'sold_packs' => $totalSoldPacks,
                'sold_weight' => $totalSoldWeight,
                'total_sales_value' => $totalSalesValue,
                'remaining_packs' => $remainingPacks,
                'remaining_weight' => $remainingWeight,
                'totalWastedPacks' => $totalWastedPacks,
                'totalWastedWeight' => $totalWastedWeight,
            ];
        }

        // --- GRN Report ---
        $grnReportData = [];
        $grouped = $grnEntries->groupBy('item_name');
        foreach ($grouped as $itemName => $entries) {
            $originalPacks = $originalWeight = $soldPacks = $soldWeight = $totalSalesValue = $remainingPacks = $remainingWeight = 0;

            foreach ($entries as $grnEntry) {
                $currentSales = Sale::where('code', $grnEntry->code)->get();
                $historicalSales = SalesHistory::where('code', $grnEntry->code)->get();
                $relatedSales = $currentSales->merge($historicalSales);
                $totalSalesValueForGrn = $relatedSales->sum('total');

                $originalPacks += $grnEntry->original_packs;
                $originalWeight += $grnEntry->original_weight;

                $soldPacks += $grnEntry->original_packs - $grnEntry->packs;
                $soldWeight += $grnEntry->original_weight - $grnEntry->weight;

                $remainingPacks += $grnEntry->packs;
                $remainingWeight += $grnEntry->weight;

                $totalSalesValue += $totalSalesValueForGrn;
            }

            $grnReportData[] = [
                'item_name' => $itemName,
                'original_packs' => $originalPacks,
                'original_weight' => $originalWeight,
                'sold_packs' => $soldPacks,
                'sold_weight' => $soldWeight,
                'total_sales_value' => $totalSalesValue,
                'remaining_packs' => $remainingPacks,
                'remaining_weight' => $remainingWeight,
            ];
        }

        // --- Weight-Based Report ---
        $weightBasedReportData = Sale::selectRaw('item_name, item_code, SUM(packs) as packs, SUM(weight) as weight, SUM(total) as total')
            ->groupBy('item_name', 'item_code')
            ->orderBy('item_name', 'asc')
            ->get();

        $salesByBill = Sale::query()
            ->whereNotNull('bill_no')
            ->where('bill_no', '<>', '')
            ->get()
            ->groupBy('bill_no');

        $settingDate = Setting::value('value');

        // --- Sales Adjustments ---
        $salesadjustments = Salesadjustment::whereDate('Date', $settingDate)
            ->orderBy('created_at', 'desc')
            ->get();

        // --- Financial Report ---
        $financialRecords = IncomeExpenses::select('customer_short_name', 'supplier_code', 'bill_no', 'description', 'amount', 'loan_type')
            ->whereDate('Date', $settingDate)
            ->get() ?? collect([]);

        $financialReportData = [];
        $totalDr = $totalCr = 0;

        foreach ($financialRecords as $record) {
            $dr = $cr = null;
            $desc = $record->customer_short_name ?? $record->supplier_code;
            if (!empty($record->bill_no))
                $desc .= " ({$record->bill_no})";
            $desc .= " - {$record->description}";

            if (in_array($record->loan_type, ['old', 'farmer_old', 'ingoing'])) {
                $dr = $record->amount;
                $totalDr += $record->amount;
            } elseif (in_array($record->loan_type, ['today', 'farmer_today', 'outgoing'])) {
                $cr = $record->amount;
                $totalCr += $record->amount;
            }

            $financialReportData[] = [
                'description' => $desc,
                'dr' => $dr,
                'cr' => $cr
            ];
        }

        $salesTotal = Sale::sum('total');
        $totalDr += $salesTotal;
        $financialReportData[] = [
            'description' => 'Sales Total',
            'dr' => $salesTotal,
            'cr' => null
        ];

        $profitTotal = Sale::sum('SellingKGTotal');
        $totalDamages = GrnEntry::select(DB::raw('SUM(wasted_weight * PerKGPrice)'))
            ->value(DB::raw('SUM(wasted_weight * PerKGPrice)')) ?? 0;

        // --- Loans ---
        $allLoans = CustomersLoan::all() ?? collect([]);
        $groupedLoans = $allLoans->groupBy('customer_short_name');
        $finalLoans = collect([]);

        foreach ($groupedLoans as $customerShortName => $loans) {
            $lastOldLoan = $loans->where('loan_type', 'old')
                ->sortByDesc(fn($l) => Carbon::parse($l->created_at))
                ->first();

            $firstTodayAfterOld = $loans->filter(function ($l) use ($lastOldLoan) {
                return $l->loan_type === 'today' &&
                    Carbon::parse($l->created_at) > ($lastOldLoan ? Carbon::parse($lastOldLoan->created_at) : Carbon::parse('1970-01-01'));
            })->sortBy(fn($l) => Carbon::parse($l->created_at))
                ->first();

            $highlightColor = null;

            if ($lastOldLoan && $firstTodayAfterOld) {
                $daysBetweenLoans = Carbon::parse($lastOldLoan->created_at)->diffInDays(Carbon::parse($firstTodayAfterOld->created_at));
                if ($daysBetweenLoans > 30)
                    $highlightColor = 'red-highlight';
                elseif ($daysBetweenLoans >= 14)
                    $highlightColor = 'blue-highlight';

                $extraTodayLoanExists = $loans->filter(fn($l) => $l->loan_type === 'today' && Carbon::parse($l->created_at) > Carbon::parse($firstTodayAfterOld->created_at))->count() > 0;
                if ($extraTodayLoanExists)
                    $highlightColor = null;
            } elseif ($lastOldLoan && !$firstTodayAfterOld) {
                $daysSinceLastOldLoan = Carbon::parse($lastOldLoan->created_at)->diffInDays(Carbon::now());
                if ($daysSinceLastOldLoan > 30)
                    $highlightColor = 'red-highlight';
                elseif ($daysSinceLastOldLoan >= 14)
                    $highlightColor = 'blue-highlight';
            }

            $totalToday = $loans->where('loan_type', 'today')->sum('amount');
            $totalOld = $loans->where('loan_type', 'old')->sum('amount');
            $totalAmount = $totalToday - $totalOld;

            $finalLoans->push((object) [
                'customer_short_name' => $customerShortName,
                'total_amount' => $totalAmount,
                'highlight_color' => $highlightColor,
            ]);
        }

        // --- Send Emails ---
        Mail::send(new CombinedReportsMail(
            $dayStartReportData,
            $grnReportData,
            $grnEntries,
            now(), // or your $dayStartDate
            $weightBasedReportData,
            salesByBill: $salesByBill,
            salesadjustments: $salesadjustments,
            financialReportData: $financialReportData,
            financialTotalDr: $totalDr,
            financialTotalCr: $totalCr,
            financialProfit: $profitTotal,
            financialDamages: $totalDamages,
            profitTotal: $profitTotal,
            totalDamages: $totalDamages,
            loans: $allLoans,
            finalLoans: $finalLoans,
        ));

        Mail::send(new CombinedReportsMail2(
            $dayStartReportData,
            $grnReportData,
            $grnEntries,
            now(),
            $weightBasedReportData,
            salesByBill: $salesByBill,
            salesadjustments: $salesadjustments,
            financialReportData: $financialReportData,
            financialTotalDr: $totalDr,
            financialTotalCr: $totalCr,
            financialProfit: $profitTotal,
            financialDamages: $totalDamages,
            profitTotal: $profitTotal,
            totalDamages: $totalDamages,
            loans: $allLoans,
            finalLoans: $finalLoans,
        ));

        return back()->with('success', 'Report generated and emails sent successfully!');
    }
  public function getSupplierReport(Request $request)
{
    // A date range reads sales_histories only; no dates reads today's sales.
    // One date alone means that single day.
    $startDate = $request->query('start_date') ?: $request->query('end_date');
    $endDate   = $request->query('end_date') ?: $startDate;

    // Decide table
    if ($startDate && $endDate) {
        $model = SalesHistory::query();
        $dateColumn = 'Date';
    } else {
        $model = Sale::query();
        $dateColumn = 'Date';
    }

    $query = $model->select([
        'supplier_code', 'customer_code', 'item_code', 'item_name',
        'SupplierWeight', 'SupplierPricePerKg', 'SupplierTotal',
        'SupplierPackCost', 'SupplierPackLabour', 'profit',
        'supplier_bill_printed', 'supplier_bill_no', 'CustomerPackCost', 'Date'
    ]);

    // Apply date filter
    // DATE() so it works whether `Date` is a DATE, DATETIME or text column (a text '2026-11-18'
    // compared with '2026-11-18 00:00:00' would drop the first day)
    if ($startDate && $endDate) {
        $query->whereRaw("DATE(`{$dateColumn}`) BETWEEN ? AND ?", [
            Carbon::parse($startDate)->toDateString(),
            Carbon::parse($endDate)->toDateString(),
        ]);
    }

    $reportData = $query->orderBy('Date', 'desc')->get();

    // Billed Records
    $billedGroups = $reportData->whereNotNull('supplier_bill_no')
        ->where('supplier_bill_no', '!=', '')
        ->groupBy(function ($item) {
            return $item->supplier_code . ' - Bill: ' . $item->supplier_bill_no;
        });

    // Non-Billed Records
    $nonBilledGroups = $reportData->where(function ($item) {
        return is_null($item->supplier_bill_no) || $item->supplier_bill_no == '';
    })->groupBy('supplier_code');

    return response()->json([
        'billed' => $billedGroups,
        'nonBilled' => $nonBilledGroups,
        'source_table' => ($startDate && $endDate) ? 'sales_history' : 'sales'
    ]);
}
public function getPrintedReport(Request $request)
{
    // Fetches the transaction type from query (N or Y)
    $type = $request->query('transaction_type', 'N');

    $sales = Sale::where('bill_printed', 'Y')
        ->where('credit_transaction', $type)
        // Corrected: Included 'given_amount' so React can calculate remaining balances
        ->select('customer_code', 'bill_no', 'total', 'given_amount', 'created_at')
        ->orderBy('customer_code')
        ->orderBy('bill_no')
        ->get();

    $groupedSales = $sales->groupBy('customer_code');

    return response()->json([
        'status' => 'success',
        'data' => $groupedSales
    ], 200);
}
public function getSalesSummary(Request $request)
{
    $startDate = $request->query('start_date');
    $endDate   = $request->query('end_date');

    // 🔥 Switch model exactly like Farmers Summary
    $model = ($startDate || $endDate)
        ? \App\Models\SalesHistory::class
        : \App\Models\Sale::class;

    $report = $model::query()
        ->where('bill_printed', 'Y')

        // ✅ Filter using Date column
        ->when($startDate, function ($query) use ($startDate) {
            $query->whereDate('Date', '>=', $startDate);
        })
        ->when($endDate, function ($query) use ($endDate) {
            $query->whereDate('Date', '<=', $endDate);
        })

        ->select(
            'customer_code',
            'customer_name',
            'bill_no',
            'Date',
            DB::raw('SUM(total) as total_amount'),
            DB::raw('MAX(given_amount) as given_amount')
        )
        ->groupBy('customer_code', 'customer_name', 'bill_no', 'Date')
        ->orderBy('Date', 'desc')
        ->get();

    return response()->json($report);
}
 public function getBillDetails(Request $request, $billNo, $customerCode)
{
    $startDate = $request->query('start_date');
    $endDate   = $request->query('end_date');

    // 🔥 SAME LOGIC AS getFarmerBillDetails()
    $model = ($startDate || $endDate)
        ? new \App\Models\SalesHistory
        : new \App\Models\Sale;

    $details = $model::where('bill_no', $billNo)
        ->where('customer_code', $customerCode)
        ->orderBy('Date', 'desc')
        ->orderBy('id', 'asc')
        ->get();

    return response()->json($details);
}
  public function getFarmersSummary(Request $request)
{
    $startDate = $request->query('start_date');
    $endDate = $request->query('end_date');

    // Choose model based on input
    $model = ($startDate || $endDate) ? new SalesHistory : new Sale;

    return $model::where('supplier_bill_printed', 'Y')
        ->whereNotNull('supplier_bill_no')
        // We use the 'Date' column specifically as you requested
        ->when($startDate, function ($query) use ($startDate) {
            return $query->whereDate('Date', '>=', $startDate);
        })
        ->when($endDate, function ($query) use ($endDate) {
            return $query->whereDate('Date', '<=', $endDate);
        })
        ->select(
            'supplier_code',
            'supplier_bill_no',
            'Date', // Ensure this column is selected for grouping
            DB::raw('SUM(SupplierTotal) as total_amount')
        )
        ->groupBy('supplier_code', 'supplier_bill_no', 'Date')
        ->orderBy('Date', 'desc')
        ->get();
}

public function getFarmerBillDetails(Request $request, $billNo, $supplierCode)
{
    $startDate = $request->query('start_date');
    $endDate = $request->query('end_date');

    // Use same logic to pick the table
    $model = ($startDate || $endDate) ? new SalesHistory : new Sale;

    return $model::where('supplier_bill_no', $billNo)
        ->where('supplier_code', $supplierCode)
        ->get();
}
}