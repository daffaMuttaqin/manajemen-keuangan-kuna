<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Transfer;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\TransferService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class TransferTest extends TestCase
{
    use RefreshDatabase;

    private function makeActiveAccount(array $overrides = []): Account
    {
        return Account::create(array_merge([
            'name'            => 'BCA Ritel',
            'account_type'    => 'bank',
            'opening_balance' => 1000000.00,
            'is_active'       => true,
        ], $overrides));
    }

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    // =========================================================================
    // SECTION 1: MANDATORY FINANCIAL TEST MATRIX SCENARIOS (FT-008, FT-009, FT-010)
    // =========================================================================

    /**
     * FT-008: Transfer conservation
     * Given BCA Rp1.000.000 and Cash Rp500.000, transfer Rp300.000 BCA -> Cash.
     * Expected: BCA Rp700.000, Cash Rp800.000, Total Company Balance Rp1.500.000.
     */
    public function test_transfer_conservation_moves_cash_and_conserves_total_company_balance(): void
    {
        $user = $this->makeUser();
        $bca = $this->makeActiveAccount(['name' => 'BCA Ritel', 'opening_balance' => 1000000.00]);
        $cash = $this->makeActiveAccount(['name' => 'Cash Box', 'opening_balance' => 500000.00]);

        $balanceService = app(AccountBalanceService::class);
        $transferService = app(TransferService::class);

        // Before: BCA = 1,000,000, Cash = 500,000, Total = 1,500,000
        $this->assertEquals(1000000.00, $balanceService->calculateBalance($bca));
        $this->assertEquals(500000.00, $balanceService->calculateBalance($cash));
        $this->assertEquals(1500000.00, $balanceService->calculateTotalCompanyBalance());

        // Transfer Rp300,000 BCA -> Cash
        $transfer = $transferService->createTransfer([
            'transfer_date'   => now()->format('Y-m-d'),
            'from_account_id' => $bca->id,
            'to_account_id'   => $cash->id,
            'amount'          => '300000',
            'description'     => 'BCA to Cash Box',
        ], $user);

        $this->assertInstanceOf(Transfer::class, $transfer);
        $this->assertEquals('300000.00', $transfer->amount);

        // After: BCA = 700,000, Cash = 800,000, Total = 1,500,000 (INV-005)
        $this->assertEquals(700000.00, $balanceService->calculateBalance($bca));
        $this->assertEquals(800000.00, $balanceService->calculateBalance($cash));
        $this->assertEquals(1500000.00, $balanceService->calculateTotalCompanyBalance());
    }

    /**
     * FT-009: Same-account transfer
     * Source and destination equal. Operation rejected and no balances change.
     */
    public function test_same_account_transfer_is_rejected(): void
    {
        $user = $this->makeUser();
        $bca = $this->makeActiveAccount(['name' => 'BCA Ritel', 'opening_balance' => 1000000.00]);

        $balanceService = app(AccountBalanceService::class);
        $transferService = app(TransferService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Source and destination accounts must be different. (INV-015)');

        try {
            $transferService->createTransfer([
                'transfer_date'   => now()->format('Y-m-d'),
                'from_account_id' => $bca->id,
                'to_account_id'   => $bca->id,
                'amount'          => '100000',
                'description'     => 'Self transfer',
            ], $user);
        } finally {
            // Balance must remain unchanged
            $this->assertEquals(1000000.00, $balanceService->calculateBalance($bca));
        }
    }

    /**
     * FT-010: Insufficient transfer
     * Given source Rp500.000, transfer Rp1.000.000. Operation rejected and no balances change.
     */
    public function test_insufficient_balance_transfer_is_rejected(): void
    {
        $user = $this->makeUser();
        $cash = $this->makeActiveAccount(['name' => 'Cash Box', 'opening_balance' => 500000.00]);
        $bca  = $this->makeActiveAccount(['name' => 'BCA Ritel', 'opening_balance' => 1000000.00]);

        $balanceService = app(AccountBalanceService::class);
        $transferService = app(TransferService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient funds in source account for transfer. (INV-016)');

        try {
            $transferService->createTransfer([
                'transfer_date'   => now()->format('Y-m-d'),
                'from_account_id' => $cash->id, // source has 500,000
                'to_account_id'   => $bca->id,
                'amount'          => '1000000', // attempting to transfer 1,000,000
                'description'     => 'Excess transfer',
            ], $user);
        } finally {
            $this->assertEquals(500000.00, $balanceService->calculateBalance($cash));
            $this->assertEquals(1000000.00, $balanceService->calculateBalance($bca));
        }
    }

    // =========================================================================
    // SECTION 2: INVARIANTS & LIFECYCLE (INV-009, INV-019, INV-020)
    // =========================================================================

    public function test_inactive_account_transfer_is_rejected(): void
    {
        $user = $this->makeUser();
        $activeAcc   = $this->makeActiveAccount(['opening_balance' => 500000.00]);
        $inactiveAcc = $this->makeActiveAccount(['is_active' => false, 'opening_balance' => 100000.00]);

        $transferService = app(TransferService::class);

        // Attempt transfer from inactive source
        $this->expectException(InvalidArgumentException::class);
        $transferService->createTransfer([
            'transfer_date'   => now()->format('Y-m-d'),
            'from_account_id' => $inactiveAcc->id,
            'to_account_id'   => $activeAcc->id,
            'amount'          => '50000',
        ], $user);
    }

    public function test_cancelling_transfer_reverses_balance_distribution_and_preserves_history(): void
    {
        $user = $this->makeUser();
        $bca = $this->makeActiveAccount(['name' => 'BCA Ritel', 'opening_balance' => 1000000.00]);
        $cash = $this->makeActiveAccount(['name' => 'Cash Box', 'opening_balance' => 500000.00]);

        $balanceService = app(AccountBalanceService::class);
        $transferService = app(TransferService::class);

        $transfer = $transferService->createTransfer([
            'transfer_date'   => now()->format('Y-m-d'),
            'from_account_id' => $bca->id,
            'to_account_id'   => $cash->id,
            'amount'          => '300000',
            'description'     => 'BCA to Cash Box',
        ], $user);

        // Transferred state: BCA = 700k, Cash = 800k
        $this->assertEquals(700000.00, $balanceService->calculateBalance($bca));
        $this->assertEquals(800000.00, $balanceService->calculateBalance($cash));

        // Cancel transfer
        $transferService->cancelTransfer($transfer, $user);

        $transfer->refresh();
        $this->assertTrue($transfer->isCancelled());

        // Restored state: BCA = 1M, Cash = 500k (INV-009)
        $this->assertEquals(1000000.00, $balanceService->calculateBalance($bca));
        $this->assertEquals(500000.00, $balanceService->calculateBalance($cash));

        // Record preserved in DB (INV-019)
        $this->assertCount(1, Transfer::all());
    }

    public function test_cancelling_already_cancelled_transfer_is_rejected(): void
    {
        $user = $this->makeUser();
        $bca = $this->makeActiveAccount(['opening_balance' => 1000000.00]);
        $cash = $this->makeActiveAccount(['opening_balance' => 500000.00]);

        $transferService = app(TransferService::class);

        $transfer = $transferService->createTransfer([
            'transfer_date'   => now()->format('Y-m-d'),
            'from_account_id' => $bca->id,
            'to_account_id'   => $cash->id,
            'amount'          => '100000',
        ], $user);

        $transferService->cancelTransfer($transfer, $user);

        $this->expectException(InvalidArgumentException::class);
        $transferService->cancelTransfer($transfer, $user);
    }

    // =========================================================================
    // SECTION 3: AUDIT & IDEMPOTENCY (FT-030, FT-022, INV-014)
    // =========================================================================

    public function test_transfer_creation_and_cancellation_create_audit_logs(): void
    {
        $user = $this->makeUser();
        $bca = $this->makeActiveAccount(['opening_balance' => 1000000.00]);
        $cash = $this->makeActiveAccount(['opening_balance' => 500000.00]);

        $transferService = app(TransferService::class);

        $transfer = $transferService->createTransfer([
            'transfer_date'   => now()->format('Y-m-d'),
            'from_account_id' => $bca->id,
            'to_account_id'   => $cash->id,
            'amount'          => '200000',
        ], $user);

        $this->assertDatabaseHas('audit_logs', [
            'action'         => 'transfer_created',
            'auditable_type' => Transfer::class,
            'auditable_id'   => $transfer->id,
            'user_id'        => $user->id,
        ]);

        $transferService->cancelTransfer($transfer, $user);

        $this->assertDatabaseHas('audit_logs', [
            'action'         => 'transfer_cancelled',
            'auditable_type' => Transfer::class,
            'auditable_id'   => $transfer->id,
            'user_id'        => $user->id,
        ]);
    }

    /**
     * FT-022: Duplicate request / submission
     * Submit the same transfer_id twice. Uniqueness constraint rejects duplicate.
     */
    public function test_duplicate_transfer_submission_is_rejected(): void
    {
        $user = $this->makeUser();
        $bca = $this->makeActiveAccount(['opening_balance' => 1000000.00]);
        $cash = $this->makeActiveAccount(['opening_balance' => 500000.00]);

        $transferService = app(TransferService::class);
        $uuid = (string) Str::uuid();

        $transferService->createTransfer([
            'transfer_id'     => $uuid,
            'transfer_date'   => now()->format('Y-m-d'),
            'from_account_id' => $bca->id,
            'to_account_id'   => $cash->id,
            'amount'          => '100000',
        ], $user);

        $this->expectException(QueryException::class); // Unique constraint violation

        $transferService->createTransfer([
            'transfer_id'     => $uuid, // duplicate UUID
            'transfer_date'   => now()->format('Y-m-d'),
            'from_account_id' => $bca->id,
            'to_account_id'   => $cash->id,
            'amount'          => '100000',
        ], $user);
    }

    public function test_negative_or_zero_amount_transfer_is_rejected(): void
    {
        $user = $this->makeUser();
        $bca = $this->makeActiveAccount();
        $cash = $this->makeActiveAccount();

        $transferService = app(TransferService::class);

        $this->expectException(InvalidArgumentException::class);
        $transferService->createTransfer([
            'transfer_date'   => now()->format('Y-m-d'),
            'from_account_id' => $bca->id,
            'to_account_id'   => $cash->id,
            'amount'          => '0',
        ], $user);
    }

    // =========================================================================
    // SECTION 4: LIVEWIRE UI INTEGRATION
    // =========================================================================

    public function test_livewire_manage_transfers_page_renders_and_executes_transfer(): void
    {
        $user = $this->makeUser();
        $bca = $this->makeActiveAccount(['name' => 'BCA Ritel', 'opening_balance' => 1000000.00]);
        $cash = $this->makeActiveAccount(['name' => 'Cash Box', 'opening_balance' => 500000.00]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Transfer\ManageTransfers::class)
            ->assertStatus(200)
            ->set('transfer_date', now()->format('Y-m-d'))
            ->set('from_account_id', (string) $bca->id)
            ->set('to_account_id', (string) $cash->id)
            ->set('amount', '250000')
            ->set('description', 'Test Livewire Transfer')
            ->call('saveTransfer')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('transfers', [
            'from_account_id' => $bca->id,
            'to_account_id'   => $cash->id,
            'amount'          => '250000.00',
            'description'     => 'Test Livewire Transfer',
        ]);

        $balanceService = app(AccountBalanceService::class);
        $this->assertEquals(750000.00, $balanceService->calculateBalance($bca));
        $this->assertEquals(750000.00, $balanceService->calculateBalance($cash));
    }
}
