<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\MenuItem;
use App\Services\AccountBalanceService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the financial dashboard.
     */
    public function index(AccountBalanceService $balanceService): View
    {
        $accounts = Account::where('is_active', true)->get();
        $totalBalance = $balanceService->calculateTotalCompanyBalance();
        $activeMenuItemsCount = MenuItem::where('is_active', true)->count();

        return view('dashboard', [
            'accounts' => $accounts,
            'totalBalance' => $totalBalance,
            'activeMenuItemsCount' => $activeMenuItemsCount,
            'balanceService' => $balanceService,
        ]);
    }
}
