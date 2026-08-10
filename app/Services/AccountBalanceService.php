<?php

namespace App\Services;

use App\Models\Account;

class AccountBalanceService
{
    /**
     * Calculate the current balance for an account.
     * Starts with opening_balance. In future phases, this will sum active financial movements.
     */
    public function calculateBalance(Account $account): float
    {
        $balance = (float) $account->opening_balance;
        
        // Future calculation logic (e.g. Income, Expense, Transfer, Loan) will be added here
        
        return $balance;
    }

    /**
     * Calculate the total company balance based on active accounts only.
     */
    public function calculateTotalCompanyBalance(): float
    {
        $accounts = Account::where('is_active', true)->get();
        
        $total = 0;
        foreach ($accounts as $account) {
            $total += $this->calculateBalance($account);
        }
        
        return $total;
    }
}
