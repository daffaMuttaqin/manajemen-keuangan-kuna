<?php

namespace App\Services;

use App\Models\Account;
use App\Models\ExpenseTransaction;
use App\Models\IncomeTransaction;

class AccountBalanceService
{
    /**
     * Calculate the current balance for an account.
     *
     * Formula (cash-basis):
     *   balance = opening_balance
     *           + SUM(active paid income transactions for this account)   [Phase 4]
     *           - SUM(active paid expense transactions for this account)  [Phase 4]
     *           +/- transfers                                             [Phase 7, future]
     *           +/- loan movements                                        [Phase 8, future]
     *
     * Only active (record_status='active') + paid (payment_status='paid') rows
     * contribute to the balance. This enforces:
     *   INV-001 — Paid income affects the account exactly once.
     *   INV-002 — Paid expense affects the account exactly once.
     *   INV-003 — Unpaid income never changes cash.
     *   INV-004 — Unpaid expense never changes cash.
     *   INV-009 — Cancelled records do not contribute.
     */
    public function calculateBalance(Account $account): float
    {
        $balance = (float) $account->opening_balance;

        // Phase 4: Add active paid income credited to this account. (INV-001, INV-003, INV-009)
        $incomeTotal = (float) IncomeTransaction::where('account_id', $account->id)
            ->where('record_status', 'active')
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        $balance += $incomeTotal;

        // Phase 4: Subtract active paid expense debited from this account. (INV-002, INV-004, INV-009)
        $expenseTotal = (float) ExpenseTransaction::where('account_id', $account->id)
            ->where('record_status', 'active')
            ->where('payment_status', 'paid')
            ->sum('amount');

        $balance -= $expenseTotal;

        // Phase 7 transfers will be added here.
        // Phase 8 loan movements will be added here.

        return $balance;
    }

    /**
     * Calculate the total company balance based on active accounts only.
     *
     * Calls calculateBalance() per account so all income/expense/transfer logic
     * is applied consistently.
     */
    public function calculateTotalCompanyBalance(): float
    {
        $accounts = Account::where('is_active', true)->get();

        $total = 0.0;
        foreach ($accounts as $account) {
            $total += $this->calculateBalance($account);
        }

        return $total;
    }
}
