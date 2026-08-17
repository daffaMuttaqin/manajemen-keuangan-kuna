<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\ExpenseTransaction;
use App\Models\IncomeTransaction;
use App\Models\MenuItem;
use App\Models\Transfer;
use App\Services\AccountBalanceService;
use App\Services\ExpenseCalculationService;
use App\Services\IncomeCalculationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the financial dashboard.
     */
    public function index(
        Request $request,
        AccountBalanceService $balanceService,
        IncomeCalculationService $incomeService,
        ExpenseCalculationService $expenseService
    ): View {
        $period = $request->query('period', 'this_month');
        $from   = $request->query('from');
        $to     = $request->query('to');

        $fromDate = null;
        $toDate   = null;
        $dateValidationError = null;

        // Parse and validate date range
        if ($period === 'this_month') {
            $fromDate = now()->startOfMonth()->format('Y-m-d');
            $toDate   = now()->format('Y-m-d');
        } elseif ($period === 'last_month') {
            $fromDate = now()->subMonth()->startOfMonth()->format('Y-m-d');
            $toDate   = now()->subMonth()->endOfMonth()->format('Y-m-d');
        } elseif ($period === 'this_year') {
            $fromDate = now()->startOfYear()->format('Y-m-d');
            $toDate   = now()->format('Y-m-d');
        } elseif ($period === 'custom') {
            if (!$from || !$to || !Carbon::hasFormat($from, 'Y-m-d') || !Carbon::hasFormat($to, 'Y-m-d')) {
                $dateValidationError = 'Please provide valid "from" and "to" dates in Y-m-d format.';
                $period   = 'this_month';
                $fromDate = now()->startOfMonth()->format('Y-m-d');
                $toDate   = now()->format('Y-m-d');
            } elseif ($from > $to) {
                $dateValidationError = 'The "from" date must be earlier than or equal to the "to" date.';
                $period   = 'this_month';
                $fromDate = now()->startOfMonth()->format('Y-m-d');
                $toDate   = now()->format('Y-m-d');
            } else {
                $fromDate = $from;
                $toDate   = $to;
            }
        } elseif ($period === 'all_time') {
            $period   = 'all_time';
            $fromDate = null;
            $toDate   = null;
        } else {
            $period   = 'this_month';
            $fromDate = now()->startOfMonth()->format('Y-m-d');
            $toDate   = now()->format('Y-m-d');
        }

        // Active accounts & balance metrics
        $accounts             = Account::where('is_active', true)->get();
        $totalBalance         = $balanceService->calculateTotalCompanyBalance();
        $activeMenuItemsCount = MenuItem::where('is_active', true)->count();

        // Bounded financial metrics for selected period
        $totalRevenue      = (float) $incomeService->calculateRevenue($fromDate, $toDate);
        $totalExpenses     = (float) $expenseService->calculateTotalExpenses($fromDate, $toDate);
        $profitEligibleExp = (float) $expenseService->calculateProfitEligibleExpenses($fromDate, $toDate);

        // Net Profit = Revenue - Profit-Eligible Expenses (Asset expenses are excluded)
        $netProfit = $totalRevenue - $profitEligibleExp;

        // Recent Transactions Feed (top 10 across Income, Expense, Transfer)
        $recentTransactions = $this->getRecentTransactions($fromDate, $toDate);

        // Financial Trend Chart Data
        $chartData = $this->getChartData($fromDate, $toDate);

        return view('dashboard', [
            'accounts'             => $accounts,
            'totalBalance'         => $totalBalance,
            'activeMenuItemsCount' => $activeMenuItemsCount,
            'balanceService'       => $balanceService,
            'totalRevenue'         => $totalRevenue,
            'totalExpenses'        => $totalExpenses,
            'profitEligibleExp'    => $profitEligibleExp,
            'netProfit'            => $netProfit,
            'period'               => $period,
            'fromDate'             => $fromDate,
            'toDate'               => $toDate,
            'dateValidationError'  => $dateValidationError,
            'recentTransactions'   => $recentTransactions,
            'chartData'            => $chartData,
        ]);
    }

    /**
     * Fetch and merge the top 10 latest transactions across Income, Expense, and Transfer.
     */
    private function getRecentTransactions(?string $from, ?string $to): Collection
    {
        // 1. Income Transactions
        $incomeQuery = IncomeTransaction::with(['menuItem', 'account']);
        if ($from !== null) {
            $incomeQuery->where('transaction_date', '>=', $from);
        }
        if ($to !== null) {
            $incomeQuery->where('transaction_date', '<=', $to);
        }
        $incomes = $incomeQuery->orderBy('transaction_date', 'desc')->orderBy('id', 'desc')->limit(10)->get()
            ->map(function ($tx) {
                $statusDisplay = $tx->record_status === 'cancelled' ? 'Cancelled' : ucfirst($tx->payment_status);
                return [
                    'date'           => $tx->transaction_date,
                    'raw_date'       => $tx->transaction_date->format('Y-m-d'),
                    'created_at'     => $tx->created_at ? $tx->created_at->timestamp : 0,
                    'title'          => 'Sales: ' . ($tx->menuItem->name ?? 'Menu Item'),
                    'category'       => $tx->category,
                    'type'           => 'Income',
                    'amount'         => (float) $tx->total_amount,
                    'account_name'   => $tx->account->name ?? 'N/A',
                    'status_display' => $statusDisplay,
                    'is_cancelled'   => $tx->isCancelled(),
                ];
            });

        // 2. Expense Transactions
        $expenseQuery = ExpenseTransaction::with(['account']);
        if ($from !== null) {
            $expenseQuery->where('transaction_date', '>=', $from);
        }
        if ($to !== null) {
            $expenseQuery->where('transaction_date', '<=', $to);
        }
        $expenses = $expenseQuery->orderBy('transaction_date', 'desc')->orderBy('id', 'desc')->limit(10)->get()
            ->map(function ($tx) {
                $statusDisplay = $tx->record_status === 'cancelled' ? 'Cancelled' : ucfirst($tx->payment_status);
                return [
                    'date'           => $tx->transaction_date,
                    'raw_date'       => $tx->transaction_date->format('Y-m-d'),
                    'created_at'     => $tx->created_at ? $tx->created_at->timestamp : 0,
                    'title'          => $tx->transaction_name ?? 'Expense Transaction',
                    'category'       => $tx->expense_category,
                    'type'           => 'Expense',
                    'amount'         => (float) $tx->amount,
                    'account_name'   => $tx->account->name ?? 'N/A',
                    'status_display' => $statusDisplay,
                    'is_cancelled'   => $tx->isCancelled(),
                ];
            });

        // 3. Transfers
        $transferQuery = Transfer::with(['fromAccount', 'toAccount']);
        if ($from !== null) {
            $transferQuery->where('transfer_date', '>=', $from);
        }
        if ($to !== null) {
            $transferQuery->where('transfer_date', '<=', $to);
        }
        $transfers = $transferQuery->orderBy('transfer_date', 'desc')->orderBy('id', 'desc')->limit(10)->get()
            ->map(function ($tx) {
                $statusDisplay = $tx->record_status === 'cancelled' ? 'Cancelled' : 'Completed';
                return [
                    'date'           => $tx->transfer_date,
                    'raw_date'       => $tx->transfer_date->format('Y-m-d'),
                    'created_at'     => $tx->created_at ? $tx->created_at->timestamp : 0,
                    'title'          => 'Transfer: ' . ($tx->fromAccount->name ?? '') . ' → ' . ($tx->toAccount->name ?? ''),
                    'category'       => 'Internal Transfer',
                    'type'           => 'Transfer',
                    'amount'         => (float) $tx->amount,
                    'account_name'   => ($tx->fromAccount->name ?? '') . ' → ' . ($tx->toAccount->name ?? ''),
                    'status_display' => $statusDisplay,
                    'is_cancelled'   => $tx->isCancelled(),
                ];
            });

        // Merge, sort by transaction_date desc, created_at desc, and take top 10
        return $incomes->concat($expenses)->concat($transfers)
            ->sort(function ($a, $b) {
                if ($a['raw_date'] === $b['raw_date']) {
                    return $b['created_at'] <=> $a['created_at'];
                }
                return strcmp($b['raw_date'], $a['raw_date']);
            })
            ->take(10)
            ->values();
    }

    /**
     * Build financial trend chart dataset.
     *
     * Net Profit for each date bucket is calculated as:
     *   Net Profit = Revenue - Profit-Eligible Expenses
     * Asset expenses are included in Total Expenses but excluded from Net Profit.
     */
    private function getChartData(?string $from, ?string $to): array
    {
        $buckets = [];

        // Determine buckets (Month-based if range > 31 days or all-time; Day-based if custom range <= 31 days)
        if ($from !== null && $to !== null) {
            $start = Carbon::parse($from);
            $end   = Carbon::parse($to);
            $diffDays = $start->diffInDays($end);

            if ($diffDays <= 31) {
                $curr = $start->copy();
                while ($curr->lte($end)) {
                    $key   = $curr->format('Y-m-d');
                    $label = $curr->format('d M');
                    $buckets[$key] = [
                        'label'     => $label,
                        'from_date' => $key,
                        'to_date'   => $key,
                    ];
                    $curr->addDay();
                }
            } else {
                $curr = $start->copy()->startOfMonth();
                while ($curr->lte($end)) {
                    $key   = $curr->format('Y-m');
                    $label = $curr->format('M Y');
                    $buckets[$key] = [
                        'label'     => $label,
                        'from_date' => $curr->copy()->startOfMonth()->format('Y-m-d'),
                        'to_date'   => $curr->copy()->endOfMonth()->format('Y-m-d'),
                    ];
                    $curr->addMonth();
                }
            }
        } else {
            // All-Time mode: generate 6 monthly buckets leading up to current month
            for ($i = 5; $i >= 0; $i--) {
                $curr  = now()->subMonths($i);
                $key   = $curr->format('Y-m');
                $label = $curr->format('M Y');
                $buckets[$key] = [
                    'label'     => $label,
                    'from_date' => $curr->copy()->startOfMonth()->format('Y-m-d'),
                    'to_date'   => $curr->copy()->endOfMonth()->format('Y-m-d'),
                ];
            }
        }

        $incomeService  = app(IncomeCalculationService::class);
        $expenseService = app(ExpenseCalculationService::class);

        $labels         = [];
        $revenueData    = [];
        $expenseData    = [];
        $netProfitData  = [];

        foreach ($buckets as $b) {
            $rev         = (float) $incomeService->calculateRevenue($b['from_date'], $b['to_date']);
            $expTotal    = (float) $expenseService->calculateTotalExpenses($b['from_date'], $b['to_date']);
            $profitExp   = (float) $expenseService->calculateProfitEligibleExpenses($b['from_date'], $b['to_date']);
            $netProf     = $rev - $profitExp;

            $labels[]        = $b['label'];
            $revenueData[]   = $rev;
            $expenseData[]   = $expTotal;
            $netProfitData[] = $netProf;
        }

        return [
            'labels'         => $labels,
            'revenue'        => $revenueData,
            'total_expenses' => $expenseData,
            'net_profit'     => $netProfitData,
        ];
    }
}


