<?php

namespace App\Services;

use App\Models\Account;
use App\Models\ExpenseTransaction;
use App\Models\IncomeTransaction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * PaymentConfirmationService
 *
 * Handles transition of unpaid financial transactions to paid status (Phase 5).
 *
 * Financial invariants enforced:
 *   INV-001 — Paid income cash effect applied exactly once upon confirmation.
 *   INV-002 — Paid expense cash effect applied exactly once upon confirmation.
 *   INV-009 — Cancelled records cannot be confirmed.
 *   INV-013 — Payment confirmation is idempotent; an already paid transaction cannot be confirmed again.
 *   INV-014 — Payment confirmation is atomic (DB transaction).
 *   INV-018 — Server authority; transaction amount comes strictly from persisted record, never client payload.
 *   INV-019 — Historical record is preserved and updated; no duplicate transaction is created.
 *   INV-020 — Inactive accounts cannot be used for payment confirmation.
 */
class PaymentConfirmationService
{
    /**
     * Confirm payment for an unpaid income transaction.
     *
     * @param IncomeTransaction $income
     * @param int|null $accountId Account to credit (required if transaction has no account_id)
     * @return IncomeTransaction
     *
     * @throws InvalidArgumentException for invariant violations.
     */
    public function confirmIncomePayment(IncomeTransaction $income, ?int $accountId = null): IncomeTransaction
    {
        if ($income->isCancelled()) {
            throw new InvalidArgumentException('Cannot confirm payment for a cancelled income transaction. (INV-009)');
        }

        if ($income->isPaid()) {
            throw new InvalidArgumentException('This income transaction is already paid. (INV-013)');
        }

        $targetAccountId = $accountId ?? $income->account_id;

        if (empty($targetAccountId)) {
            throw new InvalidArgumentException('An active account is required to confirm income payment.');
        }

        $account = Account::findOrFail((int) $targetAccountId);

        if (! $account->is_active) {
            throw new InvalidArgumentException('Inactive accounts cannot be used for payment confirmation. (INV-020)');
        }

        return DB::transaction(function () use ($income, $account) {
            $income->update([
                'payment_status' => 'paid',
                'paid_at'        => now(),
                'account_id'     => $account->id,
            ]);

            return $income->fresh();
        });
    }

    /**
     * Confirm payment for an unpaid expense transaction.
     *
     * @param ExpenseTransaction $expense
     * @param int|null $accountId Account to debit (required if transaction has no account_id)
     * @return ExpenseTransaction
     *
     * @throws InvalidArgumentException for invariant violations.
     */
    public function confirmExpensePayment(ExpenseTransaction $expense, ?int $accountId = null): ExpenseTransaction
    {
        if ($expense->isCancelled()) {
            throw new InvalidArgumentException('Cannot confirm payment for a cancelled expense transaction. (INV-009)');
        }

        if ($expense->isPaid()) {
            throw new InvalidArgumentException('This expense transaction is already paid. (INV-013)');
        }

        $targetAccountId = $accountId ?? $expense->account_id;

        if (empty($targetAccountId)) {
            throw new InvalidArgumentException('An active account is required to confirm expense payment.');
        }

        $account = Account::findOrFail((int) $targetAccountId);

        if (! $account->is_active) {
            throw new InvalidArgumentException('Inactive accounts cannot be used for payment confirmation. (INV-020)');
        }

        return DB::transaction(function () use ($expense, $account) {
            $expense->update([
                'payment_status' => 'paid',
                'paid_at'        => now(),
                'account_id'     => $account->id,
            ]);

            return $expense->fresh();
        });
    }
}
