<?php

namespace App\Services;

use App\Models\Account;
use App\Models\ExpenseTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * ExpenseCalculationService
 *
 * Handles all financial operations for expense transactions.
 *
 * Financial rules enforced here:
 *   INV-002 — Paid active expense affects its account exactly once.
 *   INV-004 — Unpaid expense never changes current cash.
 *   INV-009 — Cancelled records do not contribute to active totals.
 *   INV-012 — Payables never create additional Expense (upheld by not double-counting).
 *   INV-014 — Multi-record operations are atomic (DB transaction).
 *   INV-018 — Server computes all monetary columns; client values are ignored.
 *   INV-019 — Financial records are never physically deleted.
 *   INV-020 — Inactive accounts cannot be used for new paid financial transactions.
 *
 * Monetary arithmetic strategy:
 *   All intermediate calculations use integer-cents representation to avoid
 *   binary floating-point rounding errors. bcmath is NOT used because it is
 *   not available in all environments. The pattern is:
 *     1. Convert decimal input to integer cents (multiply by 100, round).
 *     2. Perform all arithmetic on integers (exact).
 *     3. Convert back to 2-decimal string for DECIMAL(19,2) storage.
 *   This guarantees deterministic results for all amounts with up to 2 decimal places.
 */
class ExpenseCalculationService
{
    /**
     * Convert a decimal monetary string/number to integer cents.
     * Safe: uses round() which produces a deterministic integer from a decimal string.
     */
    private function toCents(mixed $amount): int
    {
        // Cast to float ONLY for parsing the string representation,
        // not for accumulation. round() eliminates float imprecision at the
        // 2-decimal precision we need.
        return (int) round((float) $amount * 100);
    }

    /**
     * Format integer cents back to a 2-decimal decimal string for storage.
     */
    private function formatCents(int $cents): string
    {
        $negative = $cents < 0;
        $abs      = abs($cents);
        $whole    = intdiv($abs, 100);
        $frac     = str_pad($abs % 100, 2, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '') . $whole . '.' . $frac;
    }

    /**
     * Create a new expense transaction.
     *
     * This method is the single authoritative entry-point for expense creation.
     * It enforces server-side validation of all monetary values and validates
     * business rules before persisting.
     *
     * @param array $data {
     *   transaction_date:  string  (Y-m-d)
     *   expense_category:  string  (one of ExpenseTransaction::CATEGORIES)
     *   description:       string|null
     *   amount:            numeric (positive)
     *   account_id:        int|null
     *   payment_status:    'unpaid'|'paid'
     * }
     * @param User $creator
     *
     * @return ExpenseTransaction
     *
     * @throws InvalidArgumentException for financial rule violations.
     */
    public function createExpenseTransaction(array $data, User $creator): ExpenseTransaction
    {
        // ------------------------------------------------------------------
        // 1. Parse and validate amount server-side using integer cents. (INV-018)
        //    The client NEVER provides an authoritative amount; we parse
        //    the raw input, convert to cents, and store back.
        // ------------------------------------------------------------------
        $amountCents = $this->toCents($data['amount']);

        // ------------------------------------------------------------------
        // 2. Business rule: amount must be strictly positive.
        // ------------------------------------------------------------------
        if ($amountCents <= 0) {
            throw new InvalidArgumentException('Expense amount must be greater than zero.');
        }

        // ------------------------------------------------------------------
        // 3. Validate account rules for paid expense. (INV-020)
        // ------------------------------------------------------------------
        $paymentStatus = $data['payment_status'];

        if ($paymentStatus === 'paid') {
            if (empty($data['account_id'])) {
                throw new InvalidArgumentException('An active account is required for paid expense.');
            }

            $account = Account::findOrFail((int) $data['account_id']);

            if (! $account->is_active) {
                throw new InvalidArgumentException('Inactive accounts cannot be used for paid expense transactions. (INV-020)');
            }
        }

        // ------------------------------------------------------------------
        // 4. Generate server-side UUID for idempotency. (FT-022)
        // ------------------------------------------------------------------
        $transactionId = (string) Str::uuid();

        // ------------------------------------------------------------------
        // 5. Format cents back to DECIMAL(19,2) string for storage.
        // ------------------------------------------------------------------
        $amountStr = $this->formatCents($amountCents);

        // ------------------------------------------------------------------
        // 6. Persist inside a DB transaction for atomicity. (INV-014)
        //    Wrapping in a transaction ensures future additive operations
        //    (e.g. audit logging) are atomic.
        // ------------------------------------------------------------------
        return DB::transaction(function () use (
            $transactionId,
            $data,
            $amountStr,
            $paymentStatus,
            $creator
        ) {
            $paidAt = ($paymentStatus === 'paid') ? now() : null;

            $expense = ExpenseTransaction::create([
                'transaction_id'   => $transactionId,
                'transaction_date' => $data['transaction_date'],
                'transaction_name' => $data['transaction_name'] ?? 'Expense Transaction',
                'expense_category' => $data['expense_category'],
                'description'      => $data['description'] ?? null,
                'amount'           => $amountStr,
                'account_id'       => $data['account_id'] ?? null,
                'payment_status'   => $paymentStatus,
                'record_status'    => 'active',
                'paid_at'          => $paidAt,
                'created_by'       => $creator->id,
            ]);

            app(AuditLogService::class)->record('expense_created', $expense, [
                'new' => [
                    'transaction_date' => $expense->transaction_date->format('Y-m-d'),
                    'expense_category' => $expense->expense_category,
                    'amount'           => (string) $expense->amount,
                    'account_id'       => $expense->account_id,
                    'payment_status'   => $expense->payment_status,
                    'record_status'    => $expense->record_status,
                ],
            ], $creator);

            return $expense;
        });
    }

    /**
     * Update an existing expense transaction.
     *
     * @param ExpenseTransaction $expense
     * @param array $data
     * @param User|null $performer
     * @return ExpenseTransaction
     *
     * @throws InvalidArgumentException for invariant violations.
     */
    public function updateExpenseTransaction(ExpenseTransaction $expense, array $data, ?User $performer = null): ExpenseTransaction
    {
        return DB::transaction(function () use ($expense, $data, $performer) {
            // Concurrency protection: lock the transaction row in DB
            ExpenseTransaction::where('id', $expense->id)->lockForUpdate()->firstOrFail();

            // Refresh the passed instance to load the locked DB state in-memory
            $expense->refresh();

            if ($expense->isCancelled()) {
                throw new InvalidArgumentException('Cannot edit a cancelled expense transaction.');
            }

            $oldPaymentStatus = $expense->payment_status;
            $newPaymentStatus = $data['payment_status'];

            // Do not allow Paid -> Unpaid transition (PRD constraint)
            if ($oldPaymentStatus === 'paid' && $newPaymentStatus === 'unpaid') {
                throw new InvalidArgumentException('Cannot transition a paid transaction back to unpaid. (PRD)');
            }

            // Inactive account protection for paid transactions (INV-020)
            if ($newPaymentStatus === 'paid') {
                if (empty($data['account_id'])) {
                    throw new InvalidArgumentException('An active account is required for paid expense.');
                }

                $account = Account::findOrFail((int) $data['account_id']);
                if (!$account->is_active) {
                    throw new InvalidArgumentException('Inactive accounts cannot be used for paid expense transactions. (INV-020)');
                }
            }

            // Parse and validate amount server-side using integer cents
            $amountCents = $this->toCents($data['amount']);
            if ($amountCents <= 0) {
                throw new InvalidArgumentException('Expense amount must be greater than zero.');
            }

            $amountStr = $this->formatCents($amountCents);

            // Paid_at timestamp handling
            $paidAt = $expense->paid_at;
            if ($newPaymentStatus === 'paid' && $oldPaymentStatus === 'unpaid') {
                $paidAt = now();
            }

            $beforeState = [
                'transaction_name' => $expense->transaction_name,
                'expense_category' => $expense->expense_category,
                'amount'           => (string) $expense->amount,
                'account_id'       => $expense->account_id,
                'payment_status'   => $expense->payment_status,
            ];

            $expense->update([
                'transaction_date' => $data['transaction_date'],
                'transaction_name' => $data['transaction_name'] ?? $expense->transaction_name ?? 'Expense Transaction',
                'expense_category' => $data['expense_category'],
                'description'      => $data['description'] ?? null,
                'amount'           => $amountStr,
                'account_id'       => $data['account_id'] ?? null,
                'payment_status'   => $newPaymentStatus,
                'paid_at'          => $paidAt,
            ]);

            $afterState = [
                'amount'         => (string) $expense->amount,
                'account_id'     => $expense->account_id,
                'payment_status' => $expense->payment_status,
            ];

            app(AuditLogService::class)->record('expense_updated', $expense, [
                'before' => $beforeState,
                'after'  => $afterState,
            ], $performer);

            return $expense;
        });
    }

    /**
     * Cancel an expense transaction by setting record_status = 'cancelled'.
     *
     * Financial history is preserved (INV-019).
     * Once cancelled, the record no longer contributes to account balance or expense totals. (INV-009)
     * Cancellation is implemented by status flag, never by physical deletion.
     *
     * @param ExpenseTransaction $expense
     * @param User|null $performer
     * @return void
     *
     * @throws InvalidArgumentException if already cancelled.
     */
    public function cancelExpenseTransaction(ExpenseTransaction $expense, ?User $performer = null): void
    {
        DB::transaction(function () use ($expense, $performer) {
            // Concurrency protection: lock the transaction row in DB
            ExpenseTransaction::where('id', $expense->id)->lockForUpdate()->firstOrFail();

            // Refresh the passed instance to load the locked DB state in-memory
            $expense->refresh();

            if ($expense->isCancelled()) {
                throw new InvalidArgumentException('This expense transaction is already cancelled.');
            }

            $expense->update(['record_status' => 'cancelled']);

            app(AuditLogService::class)->record('expense_cancelled', $expense, [
                'record_status'  => 'cancelled',
                'amount'         => (string) $expense->amount,
                'payment_status' => $expense->payment_status,
            ], $performer);
        });
    }

    /**
     * Confirm payment for an unpaid expense transaction. (Phase 5)
     */
    public function confirmPayment(ExpenseTransaction $expense, ?int $accountId = null): ExpenseTransaction
    {
        return app(PaymentConfirmationService::class)->confirmExpensePayment($expense, $accountId);
    }

    /**
     * Calculate total expenses for active, paid expense transactions.
     *
     * Total Expenses = SUM(amount) WHERE record_status='active' AND payment_status='paid'
     * Filtered by optional date range.
     *
     * Used by dashboard and future reporting modules.
     *
     * @param string|null $from  Y-m-d
     * @param string|null $to    Y-m-d
     * @return string  Decimal string to preserve precision.
     */
    public function calculateTotalExpenses(?string $from = null, ?string $to = null): string
    {
        $query = ExpenseTransaction::where('record_status', 'active')
                                   ->where('payment_status', 'paid');

        if ($from !== null) {
            $query->where('transaction_date', '>=', $from);
        }

        if ($to !== null) {
            $query->where('transaction_date', '<=', $to);
        }

        return (string) ($query->sum('amount') ?? '0');
    }

    /**
     * Calculate profit-eligible expenses for active, paid expense transactions.
     *
     * Only categories in ExpenseTransaction::PROFIT_ELIGIBLE_CATEGORIES reduce Net Profit.
     * Asset expense and non-eligible categories are excluded from profit calculations.
     *
     * @param string|null $from Y-m-d
     * @param string|null $to   Y-m-d
     * @return string
     */
    public function calculateProfitEligibleExpenses(?string $from = null, ?string $to = null): string
    {
        $query = ExpenseTransaction::where('record_status', 'active')
                                   ->where('payment_status', 'paid')
                                   ->whereIn('expense_category', ExpenseTransaction::PROFIT_ELIGIBLE_CATEGORIES);

        if ($from !== null) {
            $query->where('transaction_date', '>=', $from);
        }

        if ($to !== null) {
            $query->where('transaction_date', '<=', $to);
        }

        return (string) ($query->sum('amount') ?? '0');
    }

    /**
     * Calculate total outstanding payables.
     *
     * Outstanding Payables = SUM(amount) WHERE record_status='active' AND payment_status='unpaid'
     * Unpaid expense creates an outstanding payable obligation only. (INV-012)
     *
     * @return string  Decimal string to preserve precision.
     */
    public function calculateOutstandingPayables(): string
    {
        return (string) (ExpenseTransaction::where('record_status', 'active')
                                           ->where('payment_status', 'unpaid')
                                           ->sum('amount') ?? '0');
    }
}
