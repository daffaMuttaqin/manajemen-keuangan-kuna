<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\MenuItem;
use App\Services\AccountBalanceService;
use App\Services\ExpenseCalculationService;
use App\Services\IncomeCalculationService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the financial dashboard.
     */
    public function index(
        AccountBalanceService $balanceService,
        IncomeCalculationService $incomeService,
        ExpenseCalculationService $expenseService
    ): View {
        $accounts             = Account::where('is_active', true)->get();
        $totalBalance         = $balanceService->calculateTotalCompanyBalance();
        $activeMenuItemsCount = MenuItem::where('is_active', true)->count();
        $totalRevenue         = (float) $incomeService->calculateRevenue();
        $totalExpenses        = (float) $expenseService->calculateTotalExpenses();

        return view('dashboard', [
            'accounts'             => $accounts,
            'totalBalance'         => $totalBalance,
            'activeMenuItemsCount' => $activeMenuItemsCount,
            'balanceService'       => $balanceService,
            'totalRevenue'         => $totalRevenue,
            'totalExpenses'        => $totalExpenses,
        ]);
    }
}

