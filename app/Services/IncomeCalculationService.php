<?php

namespace App\Services;

use App\Models\Account;
use App\Models\IncomeTransaction;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * IncomeCalculationService
 *
 * Handles all financial operations for income transactions.
 *
 * Financial rules enforced here:
 *   INV-001 — Paid active income affects its account exactly once.
 *   INV-003 — Unpaid income never changes current cash.
 *   INV-009 — Cancelled records do not contribute to active totals.
 *   INV-010 — unit_price is a snapshot; MenuItem price changes do not alter history.
 *   INV-013 — Payment confirmation (Phase 5) must be idempotent; a paid record cannot be re-paid.
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
class IncomeCalculationService
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
     * Create a new income transaction.
     *
     * This method is the single authoritative entry-point for income creation.
     * It enforces server-side calculation of all monetary values and validates
     * business rules before persisting.
     *
     * @param array $data {
     *   transaction_date: string  (Y-m-d)
     *   menu_item_id:     int
     *   quantity:         numeric (positive)
     *   discount_percentage: numeric (0–100)
     *   category:         string (max 30)
     *   description:      string|null
     *   account_id:       int|null
     *   payment_status:   'unpaid'|'paid'
     * }
     * @param User $creator
     *
     * @return IncomeTransaction
     *
     * @throws InvalidArgumentException for financial rule violations.
     */
    public function createIncomeTransaction(array $data, User $creator): IncomeTransaction
    {
        // ------------------------------------------------------------------
        // 1. Load MenuItem fresh from the database (INV-010, INV-018).
        //    Never trust client-supplied unit_price.
        // ------------------------------------------------------------------
        $menuItem = MenuItem::findOrFail((int) $data['menu_item_id']);

        // ------------------------------------------------------------------
        // 2. Snapshot the current price. This value is immutable after creation.
        //    Future MenuItem price changes must NOT affect this record. (INV-010)
        // ------------------------------------------------------------------
        $unitPriceCents = $this->toCents($menuItem->current_price);

        // ------------------------------------------------------------------
        // 3. Server-side monetary calculation using integer-cents. (INV-018)
        //    Integer arithmetic is exact — no float accumulation errors.
        //    quantity is kept as float for fractional units (e.g. 0.5 kg),
        //    but the multiplication result is rounded to integer cents.
        // ------------------------------------------------------------------
        $quantity           = (float) $data['quantity'];
        $discountPercentage = (float) ($data['discount_percentage'] ?? 0);

        // subtotal_cents = round(quantity × unitPriceCents)
        // Using round() on the product of a float × int is safe because
        // quantity has at most 2 decimal places (validated upstream).
        $subtotalCents = (int) round($quantity * $unitPriceCents);

        // discount_cents = round(subtotalCents × discountPercentage / 100)
        // Division by 100 is integer-safe since discountPercentage ≤ 100.
        $discountCents = (int) round($subtotalCents * $discountPercentage / 100);

        // total_cents = subtotalCents − discountCents (exact integer subtraction)
        $totalCents = $subtotalCents - $discountCents;

        // ------------------------------------------------------------------
        // 4. Business rule: total_amount cannot be negative.
        // ------------------------------------------------------------------
        if ($totalCents < 0) {
            throw new InvalidArgumentException('total_amount cannot be negative.');
        }

        // ------------------------------------------------------------------
        // 5. Validate account rules for paid income. (INV-020)
        // ------------------------------------------------------------------
        $paymentStatus = $data['payment_status'];

        if ($paymentStatus === 'paid') {
            if (empty($data['account_id'])) {
                throw new InvalidArgumentException('An active account is required for paid income.');
            }

            $account = Account::findOrFail((int) $data['account_id']);

            if (! $account->is_active) {
                throw new InvalidArgumentException('Inactive accounts cannot be used for paid income transactions. (INV-020)');
            }
        }

        // ------------------------------------------------------------------
        // 6. Generate server-side UUID for idempotency. (FT-022)
        // ------------------------------------------------------------------
        $transactionId = (string) Str::uuid();

        // ------------------------------------------------------------------
        // 7. Format cents back to DECIMAL(19,2) strings for storage.
        // ------------------------------------------------------------------
        $unitPriceStr   = $this->formatCents($unitPriceCents);
        $subtotalStr    = $this->formatCents($subtotalCents);
        $discountStr    = $this->formatCents($discountCents);
        $totalAmountStr = $this->formatCents($totalCents);

        // ------------------------------------------------------------------
        // 8. Persist inside a DB transaction for atomicity. (INV-014)
        //    Even though this is a single-row insert, wrapping in a transaction
        //    ensures future additive operations (e.g. audit logging) are atomic.
        // ------------------------------------------------------------------
        return DB::transaction(function () use (
            $transactionId,
            $data,
            $unitPriceStr,
            $subtotalStr,
            $discountStr,
            $totalAmountStr,
            $discountPercentage,
            $paymentStatus,
            $creator
        ) {
            $paidAt = ($paymentStatus === 'paid') ? now() : null;

            return IncomeTransaction::create([
                'transaction_id'      => $transactionId,
                'transaction_date'    => $data['transaction_date'],
                'menu_item_id'        => $data['menu_item_id'],
                'quantity'            => $data['quantity'],
                'unit_price'          => $unitPriceStr,
                'discount_percentage' => $discountPercentage,
                'subtotal'            => $subtotalStr,
                'discount_amount'     => $discountStr,
                'total_amount'        => $totalAmountStr,
                'category'            => $data['category'],
                'description'         => $data['description'] ?? null,
                'account_id'          => $data['account_id'] ?? null,
                'payment_status'      => $paymentStatus,
                'record_status'       => 'active',
                'paid_at'             => $paidAt,
                'created_by'          => $creator->id,
            ]);
        });
    }

    /**
     * Cancel an income transaction by setting record_status = 'cancelled'.
     *
     * Financial history is preserved (INV-019).
     * Once cancelled, the record no longer contributes to account balance or revenue. (INV-009)
     * Cancellation is implemented by status flag, never by physical deletion.
     *
     * @param IncomeTransaction $income
     * @return void
     *
     * @throws InvalidArgumentException if already cancelled.
     */
    public function cancelIncomeTransaction(IncomeTransaction $income): void
    {
        if ($income->isCancelled()) {
            throw new InvalidArgumentException('This income transaction is already cancelled.');
        }

        DB::transaction(function () use ($income) {
            $income->update(['record_status' => 'cancelled']);
        });
    }

    /**
     * Confirm payment for an unpaid income transaction. (Phase 5)
     */
    public function confirmPayment(IncomeTransaction $income, ?int $accountId = null): IncomeTransaction
    {
        return app(PaymentConfirmationService::class)->confirmIncomePayment($income, $accountId);
    }

    /**
     * Calculate total revenue for active, paid income transactions.
     *
     * Revenue = SUM(total_amount) WHERE record_status='active' AND payment_status='paid'
     * Filtered by optional date range.
     *
     * Used by dashboard and future reporting modules.
     *
     * @param string|null $from  Y-m-d
     * @param string|null $to    Y-m-d
     * @return string  Decimal string to preserve precision.
     */
    public function calculateRevenue(?string $from = null, ?string $to = null): string
    {
        $query = IncomeTransaction::where('record_status', 'active')
                                  ->where('payment_status', 'paid');

        if ($from !== null) {
            $query->where('transaction_date', '>=', $from);
        }

        if ($to !== null) {
            $query->where('transaction_date', '<=', $to);
        }

        return (string) ($query->sum('total_amount') ?? '0');
    }

    /**
     * Calculate total outstanding receivables.
     *
     * Outstanding Receivables = SUM(total_amount) WHERE record_status='active' AND payment_status='unpaid'
     * Unpaid income creates an outstanding receivable obligation. (INV-011)
     *
     * @return string  Decimal string to preserve precision.
     */
    public function calculateOutstandingReceivables(): string
    {
        return (string) (IncomeTransaction::where('record_status', 'active')
                                          ->where('payment_status', 'unpaid')
                                          ->sum('total_amount') ?? '0');
    }
}
