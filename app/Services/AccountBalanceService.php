<?php

namespace App\Services;

use App\Models\Account;
use App\Models\ExpenseTransaction;
use App\Models\IncomeTransaction;
use App\Models\Transfer;

class AccountBalanceService
{
    /**
     * Calculate the current balance for an account.
     *
     * Formula (cash-basis):
     *   balance = opening_balance
     *           + SUM(active paid income transactions for this account)   [Phase 4]
     *           - SUM(active paid expense transactions for this account)  [Phase 4]
     *           + SUM(active transfers credited to this account)          [Phase 7]
     *           - SUM(active transfers debited from this account)         [Phase 7]
     *           +/- loan movements                                        [Phase 8, future]
     *
     * Only active (record_status='active') rows contribute to the balance. This enforces:
     *   INV-001 — Paid income affects the account exactly once.
     *   INV-002 — Paid expense affects the account exactly once.
     *   INV-003 — Unpaid income never changes cash.
     *   INV-004 — Unpaid expense never changes cash.
     *   INV-005 — Transfer changes account distribution but not total company balance.
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

        // Phase 7: Add incoming active transfers credited to this account. (INV-005, INV-009)
        $transferInTotal = (float) Transfer::where('to_account_id', $account->id)
            ->where('record_status', 'active')
            ->sum('amount');

        $balance += $transferInTotal;

        // Phase 7: Subtract outgoing active transfers debited from this account. (INV-005, INV-009)
        $transferOutTotal = (float) Transfer::where('from_account_id', $account->id)
            ->where('record_status', 'active')
            ->sum('amount');

        $balance -= $transferOutTotal;

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
