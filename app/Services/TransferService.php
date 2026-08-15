<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * TransferService
 *
 * Handles account-to-account fund transfers for Kuna Patisserie (Phase 7).
 *
 * Financial rules enforced here:
 *   INV-005 — Transfer changes account distribution but total company balance remains constant.
 *   INV-009 — Cancelled transfers do not contribute to active account balances.
 *   INV-014 — Multi-record operations are atomic (DB transaction).
 *   INV-015 — Source and destination accounts must differ (FT-009).
 *   INV-016 — Transfer cannot exceed source account balance / create negative balance (FT-010).
 *   INV-018 — Server recalculates and validates monetary values using integer-cents arithmetic.
 *   INV-019 — Financial history is preserved; cancellation sets record_status='cancelled'.
 *   INV-020 — Inactive accounts cannot be used for new transfers.
 *   FT-022  — Duplicate submission (same transfer_id) is rejected by database constraint.
 *   FT-030  — Creation and cancellation record appropriate audit logs.
 */
class TransferService
{
    /**
     * Convert decimal monetary value to integer cents.
     */
    private function toCents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    /**
     * Format integer cents back to a 2-decimal string for storage.
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
     * Create a new transfer between two accounts.
     *
     * @param array $data {
     *   transfer_date:    string (Y-m-d)
     *   from_account_id:  int
     *   to_account_id:    int
     *   amount:           numeric (positive)
     *   description:      string|null
     *   transfer_id:      string|null (optional client UUID for testing idempotency)
     * }
     * @param User $creator
     *
     * @return Transfer
     *
     * @throws InvalidArgumentException for rule violations.
     */
    public function createTransfer(array $data, User $creator): Transfer
    {
        $fromAccountId = (int) $data['from_account_id'];
        $toAccountId   = (int) $data['to_account_id'];

        // INV-015 / FT-009: Source and destination must differ
        if ($fromAccountId === $toAccountId) {
            throw new InvalidArgumentException('Source and destination accounts must be different. (INV-015)');
        }

        $amountCents = $this->toCents($data['amount']);
        if ($amountCents <= 0) {
            throw new InvalidArgumentException('Transfer amount must be greater than zero.');
        }

        // Deterministic lock ordering to prevent deadlocks (min ID first, then max ID)
        $firstLockId  = min($fromAccountId, $toAccountId);
        $secondLockId = max($fromAccountId, $toAccountId);

        return DB::transaction(function () use ($fromAccountId, $toAccountId, $firstLockId, $secondLockId, $amountCents, $data, $creator) {
            // Lock both account rows in deterministic order BEFORE reading balance
            Account::where('id', $firstLockId)->lockForUpdate()->firstOrFail();
            Account::where('id', $secondLockId)->lockForUpdate()->firstOrFail();

            $fromAccount = Account::findOrFail($fromAccountId);
            $toAccount   = Account::findOrFail($toAccountId);

            // INV-020: Both accounts must be active
            if (!$fromAccount->is_active) {
                throw new InvalidArgumentException('Source account is inactive. (INV-020)');
            }
            if (!$toAccount->is_active) {
                throw new InvalidArgumentException('Destination account is inactive. (INV-020)');
            }

            // INV-016 / FT-010: Check source account balance AFTER locks are held
            $balanceService = app(AccountBalanceService::class);
            $currentBalanceFloat = $balanceService->calculateBalance($fromAccount);
            $currentBalanceCents = $this->toCents($currentBalanceFloat);

            if ($currentBalanceCents < $amountCents) {
                throw new InvalidArgumentException('Insufficient funds in source account for transfer. (INV-016)');
            }

            $transferUuid = $data['transfer_id'] ?? (string) Str::uuid();
            $amountStr    = $this->formatCents($amountCents);

            $transfer = Transfer::create([
                'transfer_id'     => $transferUuid,
                'transfer_date'   => $data['transfer_date'],
                'from_account_id' => $fromAccountId,
                'to_account_id'   => $toAccountId,
                'amount'          => $amountStr,
                'description'     => $data['description'] ?? null,
                'record_status'   => 'active',
                'created_by'      => $creator->id,
            ]);

            // FT-030: Record audit log entry
            app(AuditLogService::class)->record('transfer_created', $transfer, [
                'from_account_id' => $fromAccountId,
                'to_account_id'   => $toAccountId,
                'amount'          => $amountStr,
            ], $creator);

            return $transfer;
        });
    }

    /**
     * Cancel an active transfer.
     *
     * Reverses cash distribution in dynamic balance calculation and preserves record.
     *
     * @param Transfer $transfer
     * @param User|null $performer
     * @return void
     *
     * @throws InvalidArgumentException if already cancelled.
     */
    public function cancelTransfer(Transfer $transfer, ?User $performer = null): void
    {
        DB::transaction(function () use ($transfer, $performer) {
            Transfer::where('id', $transfer->id)->lockForUpdate()->firstOrFail();

            $transfer->refresh();

            if ($transfer->isCancelled()) {
                throw new InvalidArgumentException('This transfer is already cancelled.');
            }

            $transfer->update(['record_status' => 'cancelled']);

            // FT-030: Record audit log entry
            app(AuditLogService::class)->record('transfer_cancelled', $transfer, [
                'from_account_id' => $transfer->from_account_id,
                'to_account_id'   => $transfer->to_account_id,
                'amount'          => (string) $transfer->amount,
                'record_status'   => 'cancelled',
            ], $performer);
        });
    }
}
