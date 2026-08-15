<?php

namespace App\Services;

use App\Models\ExpenseTransaction;
use App\Models\IncomeTransaction;
use App\Models\Transfer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class FinancialReportService
{
    public const COGS_CATEGORIES = [
        'COGS / Cake Production',
        'COGS',
    ];

    public const OPEX_CATEGORIES = [
        'Operational',
        'Marketing',
        'Salary',
        'Rent',
        'Employee Salaries',
    ];

    /**
     * Resolve preset or custom date strings into [fromDate, toDate, dateValidationError, period].
     */
    public function resolveDateRange(string $period, ?string $from = null, ?string $to = null): array
    {
        $fromDate = null;
        $toDate   = null;
        $dateValidationError = null;

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
                $period   = 'all_time';
                $fromDate = null;
                $toDate   = null;
            } elseif ($from > $to) {
                $dateValidationError = 'The "from" date must be earlier than or equal to the "to" date.';
                $period   = 'all_time';
                $fromDate = null;
                $toDate   = null;
            } else {
                $fromDate = $from;
                $toDate   = $to;
            }
        } else {
            $period   = 'all_time';
            $fromDate = null;
            $toDate   = null;
        }

        return [
            'period'              => $period,
            'fromDate'            => $fromDate,
            'toDate'              => $toDate,
            'dateValidationError' => $dateValidationError,
        ];
    }

    /**
     * Calculate financial summary / P&L statistics for a given date range.
     */
    public function getFinancialSummary(?string $from = null, ?string $to = null): array
    {
        $incomeService  = app(IncomeCalculationService::class);
        $expenseService = app(ExpenseCalculationService::class);

        // 1. Total Revenue = SUM(total_amount) WHERE record_status='active' AND payment_status='paid'
        $totalRevenue = (float) $incomeService->calculateRevenue($from, $to);

        // 2. COGS = SUM(amount) WHERE record_status='active' AND payment_status='paid' AND expense_category IN (COGS)
        $cogsQuery = ExpenseTransaction::where('record_status', 'active')
            ->where('payment_status', 'paid')
            ->whereIn('expense_category', self::COGS_CATEGORIES);
        if ($from !== null) {
            $cogsQuery->where('transaction_date', '>=', $from);
        }
        if ($to !== null) {
            $cogsQuery->where('transaction_date', '<=', $to);
        }
        $cogs = (float) ($cogsQuery->sum('amount') ?? 0);

        // 3. Gross Profit = Revenue - COGS
        $grossProfit = $totalRevenue - $cogs;

        // 4. Profit-Eligible OpEx = SUM(amount) WHERE record_status='active' AND payment_status='paid' AND expense_category IN (OpEx)
        $opexQuery = ExpenseTransaction::where('record_status', 'active')
            ->where('payment_status', 'paid')
            ->whereIn('expense_category', self::OPEX_CATEGORIES);
        if ($from !== null) {
            $opexQuery->where('transaction_date', '>=', $from);
        }
        if ($to !== null) {
            $opexQuery->where('transaction_date', '<=', $to);
        }
        $profitEligibleOpEx = (float) ($opexQuery->sum('amount') ?? 0);

        // 5. Total Profit-Eligible Expenses = COGS + OpEx
        $profitEligibleExp = (float) $expenseService->calculateProfitEligibleExpenses($from, $to);

        // 6. Total Expenses = SUM(amount) WHERE record_status='active' AND payment_status='paid' (includes Asset & Other)
        $totalExpenses = (float) $expenseService->calculateTotalExpenses($from, $to);

        // 7. Asset Expenses = SUM(amount) WHERE record_status='active' AND payment_status='paid' AND expense_category='Asset'
        $assetQuery = ExpenseTransaction::where('record_status', 'active')
            ->where('payment_status', 'paid')
            ->where('expense_category', 'Asset');
        if ($from !== null) {
            $assetQuery->where('transaction_date', '>=', $from);
        }
        if ($to !== null) {
            $assetQuery->where('transaction_date', '<=', $to);
        }
        $assetExpenses = (float) ($assetQuery->sum('amount') ?? 0);

        // 8. Net Profit = Revenue - Profit-Eligible Expenses (Asset expenses are excluded)
        $netProfit = $totalRevenue - $profitEligibleExp;

        // 9. Expense Category Breakdown
        $categoryBreakdown = [];
        foreach (ExpenseTransaction::CATEGORIES as $cat) {
            $catQuery = ExpenseTransaction::where('record_status', 'active')
                ->where('payment_status', 'paid')
                ->where('expense_category', $cat);

            if ($from !== null) {
                $catQuery->where('transaction_date', '>=', $from);
            }
            if ($to !== null) {
                $catQuery->where('transaction_date', '<=', $to);
            }

            $count = (int) $catQuery->count();
            $total = (float) ($catQuery->sum('amount') ?? 0);

            $impact = 'Included';
            if ($cat === 'Asset') {
                $impact = 'Excluded (Asset)';
            } elseif (!in_array($cat, ExpenseTransaction::PROFIT_ELIGIBLE_CATEGORIES, true)) {
                $impact = 'Excluded';
            }

            $categoryBreakdown[] = [
                'category'          => $cat,
                'count'             => $count,
                'total_amount'      => $total,
                'net_profit_impact' => $impact,
            ];
        }

        return [
            'total_revenue'         => $totalRevenue,
            'cogs'                  => $cogs,
            'gross_profit'          => $grossProfit,
            'profit_eligible_opex'  => $profitEligibleOpEx,
            'profit_eligible_exp'   => $profitEligibleExp,
            'total_expenses'        => $totalExpenses,
            'asset_expenses'        => $assetExpenses,
            'net_profit'            => $netProfit,
            'category_breakdown'    => $categoryBreakdown,
        ];
    }

    /**
     * Build query for detailed Income transactions report.
     */
    public function getIncomeQuery(array $filters): Builder
    {
        $query = IncomeTransaction::with(['menuItem', 'account']);

        if (!empty($filters['from'])) {
            $query->where('transaction_date', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->where('transaction_date', '<=', $filters['to']);
        }
        if (!empty($filters['account_id'])) {
            $query->where('account_id', $filters['account_id']);
        }
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        if (!empty($filters['payment_status']) && $filters['payment_status'] !== 'all') {
            $query->where('payment_status', $filters['payment_status']);
        }
        if (!empty($filters['record_status']) && $filters['record_status'] !== 'all') {
            $query->where('record_status', $filters['record_status']);
        }
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('transaction_id', 'like', $search)
                  ->orWhere('description', 'like', $search)
                  ->orWhereHas('menuItem', function ($mq) use ($search) {
                      $mq->where('name', 'like', $search);
                  });
            });
        }

        return $query->orderBy('transaction_date', 'desc')->orderBy('id', 'desc');
    }

    /**
     * Build query for detailed Expense transactions report.
     */
    public function getExpenseQuery(array $filters): Builder
    {
        $query = ExpenseTransaction::with(['account']);

        if (!empty($filters['from'])) {
            $query->where('transaction_date', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->where('transaction_date', '<=', $filters['to']);
        }
        if (!empty($filters['account_id'])) {
            $query->where('account_id', $filters['account_id']);
        }
        if (!empty($filters['category'])) {
            $query->where('expense_category', $filters['category']);
        }
        if (!empty($filters['payment_status']) && $filters['payment_status'] !== 'all') {
            $query->where('payment_status', $filters['payment_status']);
        }
        if (!empty($filters['record_status']) && $filters['record_status'] !== 'all') {
            $query->where('record_status', $filters['record_status']);
        }
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('transaction_id', 'like', $search)
                  ->orWhere('transaction_name', 'like', $search)
                  ->orWhere('description', 'like', $search);
            });
        }

        return $query->orderBy('transaction_date', 'desc')->orderBy('id', 'desc');
    }

    /**
     * Build query for detailed Transfer report.
     */
    public function getTransferQuery(array $filters): Builder
    {
        $query = Transfer::with(['fromAccount', 'toAccount']);

        if (!empty($filters['from'])) {
            $query->where('transfer_date', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->where('transfer_date', '<=', $filters['to']);
        }
        if (!empty($filters['from_account_id'])) {
            $query->where('from_account_id', $filters['from_account_id']);
        }
        if (!empty($filters['to_account_id'])) {
            $query->where('to_account_id', $filters['to_account_id']);
        }
        if (!empty($filters['record_status']) && $filters['record_status'] !== 'all') {
            $query->where('record_status', $filters['record_status']);
        }
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('transfer_id', 'like', $search)
                  ->orWhere('description', 'like', $search);
            });
        }

        return $query->orderBy('transfer_date', 'desc')->orderBy('id', 'desc');
    }
}
