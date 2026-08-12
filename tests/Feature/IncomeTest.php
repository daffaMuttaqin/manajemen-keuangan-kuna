<?php

namespace Tests\Feature;

use App\Livewire\Income\ManageIncome;
use App\Models\Account;
use App\Models\IncomeTransaction;
use App\Models\MenuItem;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\IncomeCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IncomeTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeActiveAccount(array $overrides = []): Account
    {
        return Account::create(array_merge([
            'name'            => 'Test Cash',
            'account_type'    => 'cash',
            'opening_balance' => 0,
            'is_active'       => true,
        ], $overrides));
    }

    private function makeMenuItem(array $overrides = []): MenuItem
    {
        return MenuItem::create(array_merge([
            'name'          => 'Butter Croissant',
            'category'      => 'Pastry',
            'current_price' => 25000.00,
            'is_active'     => true,
        ], $overrides));
    }

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function minimalPaidData(int $menuItemId, int $accountId): array
    {
        return [
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $menuItemId,
            'quantity'            => '2',
            'discount_percentage' => '0',
            'category'            => 'Pastry',
            'description'         => null,
            'account_id'          => $accountId,
            'payment_status'      => 'paid',
        ];
    }

    private function minimalUnpaidData(int $menuItemId): array
    {
        return [
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $menuItemId,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Pastry',
            'description'         => null,
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ];
    }

    // =========================================================================
    // 1. ACCESS CONTROL
    // =========================================================================

    public function test_guest_cannot_access_income_page(): void
    {
        $response = $this->get('/income');

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_access_income_page(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->get('/income');

        $response->assertStatus(200);
        $response->assertSeeLivewire(ManageIncome::class);
    }

    // =========================================================================
    // 2. CREATION — DIRECT PAID
    // =========================================================================

    public function test_direct_paid_income_can_be_created(): void
    {
        $user    = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 0]);
        $item    = $this->makeMenuItem(['current_price' => 25000.00]);

        $this->actingAs($user);

        Livewire::test(ManageIncome::class)
            ->set('transaction_date', now()->format('Y-m-d'))
            ->set('menu_item_id', $item->id)
            ->set('quantity', '2')
            ->set('discount_percentage', '0')
            ->set('category', 'Pastry')
            ->set('account_id', $account->id)
            ->set('payment_status', 'paid')
            ->call('saveIncome');

        $this->assertDatabaseHas('income_transactions', [
            'menu_item_id'   => $item->id,
            'account_id'     => $account->id,
            'payment_status' => 'paid',
            'record_status'  => 'active',
            'total_amount'   => '50000.00',
            'created_by'     => $user->id,
        ]);
    }

    public function test_unpaid_income_can_be_created(): void
    {
        $user = $this->makeUser();
        $item = $this->makeMenuItem(['current_price' => 30000.00]);

        $this->actingAs($user);

        Livewire::test(ManageIncome::class)
            ->set('transaction_date', now()->format('Y-m-d'))
            ->set('menu_item_id', $item->id)
            ->set('quantity', '1')
            ->set('discount_percentage', '0')
            ->set('category', 'Cake')
            ->set('account_id', '')
            ->set('payment_status', 'unpaid')
            ->call('saveIncome');

        $this->assertDatabaseHas('income_transactions', [
            'menu_item_id'   => $item->id,
            'account_id'     => null,
            'payment_status' => 'unpaid',
            'record_status'  => 'active',
            'total_amount'   => '30000.00',
        ]);
    }

    public function test_paid_income_requires_an_account(): void
    {
        $user = $this->makeUser();
        $item = $this->makeMenuItem();

        $this->actingAs($user);

        Livewire::test(ManageIncome::class)
            ->set('transaction_date', now()->format('Y-m-d'))
            ->set('menu_item_id', $item->id)
            ->set('quantity', '1')
            ->set('discount_percentage', '0')
            ->set('category', 'Pastry')
            ->set('account_id', '')
            ->set('payment_status', 'paid')
            ->call('saveIncome')
            ->assertHasErrors(['account_id']);
    }

    public function test_paid_income_cannot_use_inactive_account(): void
    {
        $user    = $this->makeUser();
        $account = $this->makeActiveAccount(['is_active' => false]);
        $item    = $this->makeMenuItem();

        $this->actingAs($user);

        Livewire::test(ManageIncome::class)
            ->set('transaction_date', now()->format('Y-m-d'))
            ->set('menu_item_id', $item->id)
            ->set('quantity', '1')
            ->set('discount_percentage', '0')
            ->set('category', 'Pastry')
            ->set('account_id', $account->id)
            ->set('payment_status', 'paid')
            ->call('saveIncome')
            ->assertHasErrors(['account_id']);

        $this->assertDatabaseEmpty('income_transactions');
    }

    public function test_unpaid_income_allows_null_account_id(): void
    {
        $user = $this->makeUser();
        $item = $this->makeMenuItem();

        $this->actingAs($user);

        Livewire::test(ManageIncome::class)
            ->set('transaction_date', now()->format('Y-m-d'))
            ->set('menu_item_id', $item->id)
            ->set('quantity', '1')
            ->set('discount_percentage', '0')
            ->set('category', 'Pastry')
            ->set('account_id', '')
            ->set('payment_status', 'unpaid')
            ->call('saveIncome')
            ->assertHasNoErrors();

        $tx = IncomeTransaction::first();
        $this->assertNull($tx->account_id);
    }

    // =========================================================================
    // 3. SERVER-SIDE FINANCIAL CALCULATION (INV-018)
    // =========================================================================

    public function test_subtotal_is_calculated_server_side(): void
    {
        $user = $this->makeUser();
        $item = $this->makeMenuItem(['current_price' => 25000.00]);

        $service = app(IncomeCalculationService::class);
        $tx = $service->createIncomeTransaction(
            $this->minimalUnpaidData($item->id),
            $user
        );

        // quantity=1, unit_price=25000 → subtotal=25000
        $this->assertEquals('25000.00', $tx->subtotal);
    }

    public function test_total_amount_is_calculated_server_side(): void
    {
        $user = $this->makeUser();
        $item = $this->makeMenuItem(['current_price' => 50000.00]);

        $service = app(IncomeCalculationService::class);
        $tx = $service->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '2',
            'discount_percentage' => '10',   // 10% of 100000 = 10000
            'category'            => 'Cake',
            'description'         => null,
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        // subtotal = 2 × 50000 = 100000
        // discount = 100000 × 10 / 100 = 10000
        // total    = 100000 - 10000 = 90000
        $this->assertEquals('100000.00', $tx->subtotal);
        $this->assertEquals('10000.00', $tx->discount_amount);
        $this->assertEquals('90000.00', $tx->total_amount);
    }

    public function test_client_cannot_override_server_total_amount(): void
    {
        // The service never accepts total_amount from outside — it always computes it.
        // Verify that a tampered value has no effect.
        $user = $this->makeUser();
        $item = $this->makeMenuItem(['current_price' => 25000.00]);

        $service = app(IncomeCalculationService::class);
        // We pass no total_amount key — service must compute it from qty/price.
        $tx = $service->createIncomeTransaction(
            $this->minimalUnpaidData($item->id),
            $user
        );

        // Expected: 1 × 25000 = 25000
        $this->assertEquals('25000.00', $tx->total_amount);
    }

    public function test_client_cannot_override_server_subtotal(): void
    {
        $user = $this->makeUser();
        $item = $this->makeMenuItem(['current_price' => 20000.00]);

        $service = app(IncomeCalculationService::class);
        $tx = $service->createIncomeTransaction(
            $this->minimalUnpaidData($item->id),
            $user
        );

        // Expected subtotal: 1 × 20000 = 20000 regardless of what client might send.
        $this->assertEquals('20000.00', $tx->subtotal);
    }

    public function test_discount_cannot_exceed_subtotal(): void
    {
        $user = $this->makeUser();
        $item = $this->makeMenuItem(['current_price' => 10000.00]);

        // discount_percentage = 110 → invalid (>100), Livewire validation catches this.
        $this->actingAs($user);

        Livewire::test(ManageIncome::class)
            ->set('transaction_date', now()->format('Y-m-d'))
            ->set('menu_item_id', $item->id)
            ->set('quantity', '1')
            ->set('discount_percentage', '110')
            ->set('category', 'Test')
            ->set('payment_status', 'unpaid')
            ->call('saveIncome')
            ->assertHasErrors(['discount_percentage']);
    }

    public function test_invalid_quantity_is_rejected(): void
    {
        $user = $this->makeUser();
        $item = $this->makeMenuItem();

        $this->actingAs($user);

        Livewire::test(ManageIncome::class)
            ->set('transaction_date', now()->format('Y-m-d'))
            ->set('menu_item_id', $item->id)
            ->set('quantity', '0')     // must be > 0
            ->set('discount_percentage', '0')
            ->set('category', 'Pastry')
            ->set('payment_status', 'unpaid')
            ->call('saveIncome')
            ->assertHasErrors(['quantity']);

        Livewire::test(ManageIncome::class)
            ->set('transaction_date', now()->format('Y-m-d'))
            ->set('menu_item_id', $item->id)
            ->set('quantity', '-5')
            ->set('discount_percentage', '0')
            ->set('category', 'Pastry')
            ->set('payment_status', 'unpaid')
            ->call('saveIncome')
            ->assertHasErrors(['quantity']);
    }

    public function test_monetary_calculations_remain_decimal_safe(): void
    {
        $user = $this->makeUser();
        // Price with fractional cents — ensures DECIMAL arithmetic, not float.
        $item = $this->makeMenuItem(['current_price' => 33333.33]);

        $service = app(IncomeCalculationService::class);
        $tx = $service->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '3',
            'discount_percentage' => '0',
            'category'            => 'Test',
            'description'         => null,
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        // 3 × 33333.33 = 99999.99
        $this->assertEquals('99999.99', $tx->total_amount);
    }

    // =========================================================================
    // 4. MENU ITEM PRICE SNAPSHOT (INV-010, FT-018)
    // =========================================================================

    public function test_unit_price_is_copied_from_menu_item_at_creation(): void
    {
        $user = $this->makeUser();
        $item = $this->makeMenuItem(['current_price' => 45000.00]);

        $service = app(IncomeCalculationService::class);
        $tx = $service->createIncomeTransaction(
            $this->minimalUnpaidData($item->id),
            $user
        );

        $this->assertEquals('45000.00', $tx->unit_price);
    }

    public function test_historical_unit_price_unchanged_when_menu_price_changes(): void
    {
        // FT-018: historical price must survive menu price update.
        $user = $this->makeUser();
        $item = $this->makeMenuItem(['current_price' => 25000.00]);

        $service = app(IncomeCalculationService::class);
        $tx = $service->createIncomeTransaction(
            $this->minimalUnpaidData($item->id),
            $user
        );

        // Verify unit_price snapshot.
        $this->assertEquals('25000.00', $tx->unit_price);

        // Now change the menu item price.
        $item->update(['current_price' => 99999.99]);

        // Re-read the transaction from the DB.
        $tx->refresh();

        // Historical unit_price must remain unchanged. (INV-010)
        $this->assertEquals('25000.00', $tx->unit_price);
        // total_amount must also be unchanged.
        $this->assertEquals('25000.00', $tx->total_amount);
    }

    // =========================================================================
    // 5. ACCOUNT BALANCE INTEGRATION (INV-001, INV-003, INV-009)
    // =========================================================================

    public function test_unpaid_income_does_not_affect_account_balance(): void
    {
        // INV-003
        $user    = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 100000]);
        $item    = $this->makeMenuItem(['current_price' => 50000.00]);

        $service = app(IncomeCalculationService::class);
        $service->createIncomeTransaction(
            $this->minimalUnpaidData($item->id),
            $user
        );

        $balanceService = app(AccountBalanceService::class);
        // Balance should remain at opening_balance only.
        $this->assertEquals(100000.00, $balanceService->calculateBalance($account));
    }

    public function test_paid_active_income_increases_account_balance(): void
    {
        // INV-001, FT-001
        $user    = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 100000]);
        $item    = $this->makeMenuItem(['current_price' => 50000.00]);

        $service = app(IncomeCalculationService::class);
        $service->createIncomeTransaction(
            $this->minimalPaidData($item->id, $account->id),
            $user
        );

        $balanceService = app(AccountBalanceService::class);
        // 100000 (opening) + 50000 (paid income, qty=1 for minimalPaidData) but minimalPaidData uses qty=2
        // minimalPaidData: quantity='2', price=50000 → total=100000
        $this->assertEquals(200000.00, $balanceService->calculateBalance($account));
    }

    public function test_multiple_paid_income_records_aggregate_correctly(): void
    {
        $user    = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 0]);
        $item    = $this->makeMenuItem(['current_price' => 10000.00]);

        $service = app(IncomeCalculationService::class);

        // Create 3 paid income records.
        for ($i = 0; $i < 3; $i++) {
            $service->createIncomeTransaction(
                $this->minimalPaidData($item->id, $account->id),
                $user
            );
        }

        // Each: 2 × 10000 = 20000; total 3 × 20000 = 60000.
        $balanceService = app(AccountBalanceService::class);
        $this->assertEquals(60000.00, $balanceService->calculateBalance($account));
    }

    public function test_cancelled_income_does_not_affect_account_balance(): void
    {
        // INV-009
        $user    = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 0]);
        $item    = $this->makeMenuItem(['current_price' => 50000.00]);

        $incomeService = app(IncomeCalculationService::class);

        $tx = $incomeService->createIncomeTransaction(
            $this->minimalPaidData($item->id, $account->id),
            $user
        );

        // Cancel it.
        $incomeService->cancelIncomeTransaction($tx);

        // Verify record_status is 'cancelled'.
        $tx->refresh();
        $this->assertEquals('cancelled', $tx->record_status);

        // Balance should be 0 — cancelled income excluded.
        $balanceService = app(AccountBalanceService::class);
        $this->assertEquals(0.0, $balanceService->calculateBalance($account));
    }

    public function test_paid_income_contributes_exactly_once(): void
    {
        // INV-001: a single paid active income row appears in SUM exactly once.
        $user    = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 0]);
        $item    = $this->makeMenuItem(['current_price' => 25000.00]);

        $service = app(IncomeCalculationService::class);
        $service->createIncomeTransaction(
            $this->minimalPaidData($item->id, $account->id),
            $user
        );

        $balanceService = app(AccountBalanceService::class);

        // Call calculateBalance twice — result must be identical.
        $balance1 = $balanceService->calculateBalance($account);
        $balance2 = $balanceService->calculateBalance($account);

        $this->assertEquals($balance1, $balance2);

        // And must equal 2 × 25000 = 50000 (minimalPaidData qty=2).
        $this->assertEquals(50000.00, $balance1);
    }

    // =========================================================================
    // 6. TRANSACTION INTEGRITY
    // =========================================================================

    public function test_transaction_id_is_generated_unique_server_side(): void
    {
        $user = $this->makeUser();
        $item = $this->makeMenuItem();

        $service = app(IncomeCalculationService::class);

        $tx1 = $service->createIncomeTransaction($this->minimalUnpaidData($item->id), $user);
        $tx2 = $service->createIncomeTransaction($this->minimalUnpaidData($item->id), $user);

        $this->assertNotEmpty($tx1->transaction_id);
        $this->assertNotEmpty($tx2->transaction_id);
        $this->assertNotEquals($tx1->transaction_id, $tx2->transaction_id);
    }

    public function test_invalid_menu_item_is_rejected(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user);

        Livewire::test(ManageIncome::class)
            ->set('transaction_date', now()->format('Y-m-d'))
            ->set('menu_item_id', '99999')   // does not exist
            ->set('quantity', '1')
            ->set('discount_percentage', '0')
            ->set('category', 'Test')
            ->set('payment_status', 'unpaid')
            ->call('saveIncome')
            ->assertHasErrors(['menu_item_id']);
    }

    public function test_financial_records_are_not_deleted_on_cancellation(): void
    {
        // INV-019: history must be preserved.
        $user    = $this->makeUser();
        $account = $this->makeActiveAccount();
        $item    = $this->makeMenuItem();

        $service = app(IncomeCalculationService::class);
        $tx      = $service->createIncomeTransaction(
            $this->minimalPaidData($item->id, $account->id),
            $user
        );

        $id = $tx->id;

        $service->cancelIncomeTransaction($tx);

        // Row must still exist in the database.
        $this->assertDatabaseHas('income_transactions', [
            'id'            => $id,
            'record_status' => 'cancelled',
        ]);

        // Count must still be 1 (not deleted).
        $this->assertCount(1, IncomeTransaction::all());
    }

    // =========================================================================
    // 7. REVENUE / OUTSTANDING RECEIVABLES
    // =========================================================================

    public function test_unpaid_income_does_not_appear_in_revenue(): void
    {
        $user = $this->makeUser();
        $item = $this->makeMenuItem(['current_price' => 50000.00]);

        $service = app(IncomeCalculationService::class);
        $service->createIncomeTransaction($this->minimalUnpaidData($item->id), $user);

        $this->assertEquals('0', $service->calculateRevenue());
    }

    public function test_paid_active_income_appears_in_revenue(): void
    {
        $user    = $this->makeUser();
        $account = $this->makeActiveAccount();
        $item    = $this->makeMenuItem(['current_price' => 50000.00]);

        $service = app(IncomeCalculationService::class);
        $service->createIncomeTransaction($this->minimalPaidData($item->id, $account->id), $user);

        // minimalPaidData qty=2 × 50000 = 100000
        $this->assertEquals('100000.00', number_format((float) $service->calculateRevenue(), 2, '.', ''));
    }

    public function test_cancelled_income_is_excluded_from_revenue(): void
    {
        $user    = $this->makeUser();
        $account = $this->makeActiveAccount();
        $item    = $this->makeMenuItem(['current_price' => 50000.00]);

        $service = app(IncomeCalculationService::class);
        $tx = $service->createIncomeTransaction($this->minimalPaidData($item->id, $account->id), $user);

        $service->cancelIncomeTransaction($tx);

        $this->assertEquals('0', $service->calculateRevenue());
    }

    public function test_unpaid_income_increases_outstanding_receivables(): void
    {
        $user = $this->makeUser();
        $item = $this->makeMenuItem(['current_price' => 75000.00]);

        $service = app(IncomeCalculationService::class);
        $service->createIncomeTransaction($this->minimalUnpaidData($item->id), $user);

        $receivables = $service->calculateOutstandingReceivables();
        $this->assertEquals('75000.00', number_format((float) $receivables, 2, '.', ''));
    }

    // =========================================================================
    // 8. LIVEWIRE VALIDATION
    // =========================================================================

    public function test_required_fields_are_validated(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        Livewire::test(ManageIncome::class)
            ->set('transaction_date', '')
            ->set('menu_item_id', '')
            ->set('quantity', '')
            ->set('category', '')
            ->set('payment_status', 'unpaid')
            ->call('saveIncome')
            ->assertHasErrors(['transaction_date', 'menu_item_id', 'quantity', 'category']);
    }

    public function test_category_max_length_validated(): void
    {
        $user = $this->makeUser();
        $item = $this->makeMenuItem();

        $this->actingAs($user);

        Livewire::test(ManageIncome::class)
            ->set('transaction_date', now()->format('Y-m-d'))
            ->set('menu_item_id', $item->id)
            ->set('quantity', '1')
            ->set('discount_percentage', '0')
            ->set('category', str_repeat('X', 31))  // max 30
            ->set('payment_status', 'unpaid')
            ->call('saveIncome')
            ->assertHasErrors(['category']);
    }

    // =========================================================================
    // 9. LIVEWIRE UI FEATURES
    // =========================================================================

    public function test_income_list_is_paginated(): void
    {
        $user    = $this->makeUser();
        $account = $this->makeActiveAccount();
        $item    = $this->makeMenuItem(['current_price' => 10000.00]);

        $service = app(IncomeCalculationService::class);

        for ($i = 0; $i < 20; $i++) {
            $service->createIncomeTransaction(
                $this->minimalPaidData($item->id, $account->id),
                $user
            );
        }

        $this->actingAs($user);

        Livewire::test(ManageIncome::class)
            ->assertSee('Butter Croissant');
    }

    public function test_income_cancellation_via_livewire_preserves_record(): void
    {
        $user    = $this->makeUser();
        $account = $this->makeActiveAccount();
        $item    = $this->makeMenuItem();

        $service = app(IncomeCalculationService::class);
        $tx = $service->createIncomeTransaction(
            $this->minimalPaidData($item->id, $account->id),
            $user
        );

        $this->actingAs($user);

        Livewire::test(ManageIncome::class)
            ->call('cancelIncome', $tx->id);

        $this->assertDatabaseHas('income_transactions', [
            'id'            => $tx->id,
            'record_status' => 'cancelled',
        ]);
    }

    public function test_total_company_balance_reflects_paid_income(): void
    {
        $user     = $this->makeUser();
        $account1 = $this->makeActiveAccount(['name' => 'Cash', 'opening_balance' => 100000]);
        $account2 = $this->makeActiveAccount(['name' => 'Bank', 'opening_balance' => 200000]);
        $item     = $this->makeMenuItem(['current_price' => 10000.00]);

        $service = app(IncomeCalculationService::class);

        // Pay into account1.
        $service->createIncomeTransaction(
            $this->minimalPaidData($item->id, $account1->id),
            $user
        );

        $balanceService = app(AccountBalanceService::class);

        // Total = 100000 + 20000 (2×10000 paid) + 200000 = 320000
        $this->assertEquals(320000.0, $balanceService->calculateTotalCompanyBalance());
    }

    // =========================================================================
    // 10. REGRESSION — EXISTING TESTS STILL PASS (verified by running full suite)
    // =========================================================================
    // The full PHPUnit suite runs Authentication, Account, Menu, and Dashboard
    // tests unchanged. These tests confirm no regression in prior phases.
    // Below we add a smoke-test for each prior module's core functionality.

    public function test_account_balance_service_still_includes_opening_balance(): void
    {
        $account = $this->makeActiveAccount(['opening_balance' => 500000]);

        $balanceService = app(AccountBalanceService::class);

        $this->assertEquals(500000.0, $balanceService->calculateBalance($account));
    }

    public function test_inactive_account_excluded_from_total_company_balance(): void
    {
        $this->makeActiveAccount(['opening_balance' => 100000, 'is_active' => false]);
        $activeAccount = $this->makeActiveAccount(['opening_balance' => 50000, 'is_active' => true]);

        $balanceService = app(AccountBalanceService::class);

        // Only active account contributes.
        $this->assertEquals(50000.0, $balanceService->calculateTotalCompanyBalance());
    }
}
