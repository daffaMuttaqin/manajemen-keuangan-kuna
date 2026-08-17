<?php

namespace Tests\Feature;

use App\Livewire\Expense\ManageExpense;
use App\Models\Account;
use App\Models\ExpenseTransaction;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\ExpenseCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExpenseTest extends TestCase
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

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    /**
     * Minimal data for a paid expense (requires active account).
     */
    private function minimalPaidData(int $accountId, array $overrides = []): array
    {
        return array_merge([
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_name' => 'Test Expense Transaction',
            'expense_category' => 'Operational',
            'description'      => null,
            'amount'           => '50000',
            'account_id'       => $accountId,
            'payment_status'   => 'paid',
        ], $overrides);
    }

    /**
     * Minimal data for an unpaid expense (no account required).
     */
    private function minimalUnpaidData(array $overrides = []): array
    {
        return array_merge([
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_name' => 'Test Unpaid Expense',
            'expense_category' => 'Operational',
            'description'      => null,
            'amount'           => '30000',
            'account_id'       => null,
            'payment_status'   => 'unpaid',
        ], $overrides);
    }

    // =========================================================================
    // 1. ACCESS CONTROL
    // =========================================================================

    public function test_guest_cannot_access_expense_page(): void
    {
        $response = $this->get('/expense');

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_access_expense_page(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->get('/expense');

        $response->assertStatus(200);
        $response->assertSeeLivewire(ManageExpense::class);
    }

    // =========================================================================
    // 2. CREATION — DIRECT PAID
    // =========================================================================

    public function test_direct_paid_expense_can_be_created(): void
    {
        $user    = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 500000]);

        $this->actingAs($user);

        Livewire::test(ManageExpense::class)
            ->set('transaction_date', now()->format('Y-m-d'))
            ->set('transaction_name', 'Test Expense Transaction')
            ->set('expense_category', 'Operational')
            ->set('amount', '150000')
            ->set('account_id', $account->id)
            ->set('payment_status', 'paid')
            ->call('saveExpense');

        $this->assertDatabaseHas('expense_transactions', [
            'account_id'       => $account->id,
            'expense_category' => 'Operational',
            'payment_status'   => 'paid',
            'record_status'    => 'active',
            'amount'           => '150000.00',
            'created_by'       => $user->id,
        ]);
    }

    public function test_unpaid_expense_can_be_created(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user);

        Livewire::test(ManageExpense::class)
            ->set('transaction_date', now()->format('Y-m-d'))
            ->set('transaction_name', 'Test Unpaid Expense')
            ->set('expense_category', 'Marketing')
            ->set('amount', '75000')
            ->set('account_id', '')
            ->set('payment_status', 'unpaid')
            ->call('saveExpense');

        $this->assertDatabaseHas('expense_transactions', [
            'account_id'       => null,
            'expense_category' => 'Marketing',
            'payment_status'   => 'unpaid',
            'record_status'    => 'active',
            'amount'           => '75000.00',
        ]);
    }

    public function test_paid_expense_requires_an_account(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user);

        Livewire::test(ManageExpense::class)
            ->set('transaction_date', now()->format('Y-m-d'))
            ->set('transaction_name', 'Paid Expense')
            ->set('expense_category', 'Operational')
            ->set('amount', '50000')
            ->set('account_id', '')
            ->set('payment_status', 'paid')
            ->call('saveExpense')
            ->assertHasErrors(['account_id']);
    }

    public function test_paid_expense_cannot_use_inactive_account(): void
    {
        $user    = $this->makeUser();
        $account = $this->makeActiveAccount(['is_active' => false]);

        $this->actingAs($user);

        Livewire::test(ManageExpense::class)
            ->set('transaction_date', now()->format('Y-m-d'))
            ->set('transaction_name', 'Paid Expense Inactive')
            ->set('expense_category', 'Operational')
            ->set('amount', '50000')
            ->set('account_id', $account->id)
            ->set('payment_status', 'paid')
            ->call('saveExpense')
            ->assertHasErrors(['account_id']);

        $this->assertDatabaseEmpty('expense_transactions');
    }

    public function test_unpaid_expense_allows_null_account_id(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user);

        Livewire::test(ManageExpense::class)
            ->set('transaction_date', now()->format('Y-m-d'))
            ->set('transaction_name', 'Rent Expense')
            ->set('expense_category', 'Rent')
            ->set('amount', '500000')
            ->set('account_id', '')
            ->set('payment_status', 'unpaid')
            ->call('saveExpense')
            ->assertHasNoErrors();

        $tx = ExpenseTransaction::first();
        $this->assertNull($tx->account_id);
    }

    // =========================================================================
    // 3. SERVER-SIDE FINANCIAL CALCULATION (INV-018)
    // =========================================================================

    public function test_amount_is_parsed_server_side(): void
    {
        $user    = $this->makeUser();
        $service = app(ExpenseCalculationService::class);

        $tx = $service->createExpenseTransaction(
            $this->minimalUnpaidData(['amount' => '100000']),
            $user
        );

        $this->assertEquals('100000.00', $tx->amount);
    }

    public function test_client_cannot_override_server_amount(): void
    {
        // The service never accepts a pre-computed amount from outside.
        // It always parses and validates the raw input.
        $user    = $this->makeUser();
        $service = app(ExpenseCalculationService::class);

        $data         = $this->minimalUnpaidData(['amount' => '50000']);
        // Adding an untrustworthy key that should be ignored
        $data['fake_total'] = '999999999';

        $tx = $service->createExpenseTransaction($data, $user);

        // Only the amount field matters: 50000.00
        $this->assertEquals('50000.00', $tx->amount);
    }

    public function test_monetary_calculations_remain_decimal_safe(): void
    {
        // FT-019 variant: ensure no float accumulation errors for awkward amounts.
        $user    = $this->makeUser();
        $service = app(ExpenseCalculationService::class);

        // 33333.33 — not an exact binary float
        $tx = $service->createExpenseTransaction(
            $this->minimalUnpaidData(['amount' => '33333.33']),
            $user
        );

        $this->assertEquals('33333.33', $tx->amount);
    }

    public function test_zero_amount_is_rejected(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user);

        Livewire::test(ManageExpense::class)
            ->set('transaction_date', now()->format('Y-m-d'))
            ->set('transaction_name', 'Zero Amount Test')
            ->set('expense_category', 'Operational')
            ->set('amount', '0')
            ->set('payment_status', 'unpaid')
            ->call('saveExpense')
            ->assertHasErrors(['amount']);
    }

    public function test_negative_amount_is_rejected(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user);

        Livewire::test(ManageExpense::class)
            ->set('transaction_date', now()->format('Y-m-d'))
            ->set('transaction_name', 'Negative Amount Test')
            ->set('expense_category', 'Operational')
            ->set('amount', '-500')
            ->set('payment_status', 'unpaid')
            ->call('saveExpense')
            ->assertHasErrors(['amount']);
    }

    // =========================================================================
    // 4. ACCOUNT BALANCE INTEGRATION (INV-002, INV-004, INV-009)
    // =========================================================================

    public function test_paid_active_expense_decreases_account_balance(): void
    {
        // INV-002, FT-003
        $user    = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 500000]);

        $service = app(ExpenseCalculationService::class);
        $service->createExpenseTransaction(
            $this->minimalPaidData($account->id, ['amount' => '150000']),
            $user
        );

        $balanceService = app(AccountBalanceService::class);
        // 500000 (opening) - 150000 (paid expense) = 350000
        $this->assertEquals(350000.00, $balanceService->calculateBalance($account));
    }

    public function test_unpaid_expense_does_not_affect_account_balance(): void
    {
        // INV-004, FT-004
        $user    = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 100000]);

        $service = app(ExpenseCalculationService::class);
        $service->createExpenseTransaction(
            $this->minimalUnpaidData(['amount' => '50000']),
            $user
        );

        $balanceService = app(AccountBalanceService::class);
        // Balance must remain unchanged — unpaid expense has no cash effect.
        $this->assertEquals(100000.0, $balanceService->calculateBalance($account));
    }

    public function test_multiple_paid_expense_records_aggregate_correctly(): void
    {
        $user    = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 1000000]);

        $service = app(ExpenseCalculationService::class);

        // Create 3 paid expense records of 50000 each.
        for ($i = 0; $i < 3; $i++) {
            $service->createExpenseTransaction(
                $this->minimalPaidData($account->id, ['amount' => '50000']),
                $user
            );
        }

        $balanceService = app(AccountBalanceService::class);
        // 1000000 - (3 × 50000) = 850000
        $this->assertEquals(850000.0, $balanceService->calculateBalance($account));
    }

    public function test_cancelled_expense_does_not_affect_account_balance(): void
    {
        // INV-009
        $user    = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 500000]);

        $expenseService = app(ExpenseCalculationService::class);

        $tx = $expenseService->createExpenseTransaction(
            $this->minimalPaidData($account->id, ['amount' => '100000']),
            $user
        );

        // Verify balance is reduced before cancellation.
        $balanceService = app(AccountBalanceService::class);
        $this->assertEquals(400000.0, $balanceService->calculateBalance($account));

        // Cancel the expense.
        $expenseService->cancelExpenseTransaction($tx);

        // Re-read and verify cancelled.
        $tx->refresh();
        $this->assertEquals('cancelled', $tx->record_status);

        // Balance must revert to opening_balance only — cancelled expense excluded.
        $this->assertEquals(500000.0, $balanceService->calculateBalance($account));
    }

    public function test_paid_expense_contributes_exactly_once(): void
    {
        // INV-002: a single paid active expense row appears in SUM exactly once.
        $user    = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 500000]);

        $service = app(ExpenseCalculationService::class);
        $service->createExpenseTransaction(
            $this->minimalPaidData($account->id, ['amount' => '100000']),
            $user
        );

        $balanceService = app(AccountBalanceService::class);

        // Call calculateBalance twice — result must be identical.
        $balance1 = $balanceService->calculateBalance($account);
        $balance2 = $balanceService->calculateBalance($account);

        $this->assertEquals($balance1, $balance2);
        // 500000 - 100000 = 400000
        $this->assertEquals(400000.0, $balance1);
    }

    // =========================================================================
    // 5. EXPENSE TOTALS / OUTSTANDING PAYABLES
    // =========================================================================

    public function test_unpaid_expense_does_not_appear_in_total_expenses(): void
    {
        // FT-004: unpaid expense must not affect cash metrics.
        $user    = $this->makeUser();
        $service = app(ExpenseCalculationService::class);

        $service->createExpenseTransaction(
            $this->minimalUnpaidData(['amount' => '50000']),
            $user
        );

        $this->assertEquals('0', $service->calculateTotalExpenses());
    }

    public function test_paid_active_expense_appears_in_total_expenses(): void
    {
        // FT-003
        $user    = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 500000]);

        $service = app(ExpenseCalculationService::class);
        $service->createExpenseTransaction(
            $this->minimalPaidData($account->id, ['amount' => '200000']),
            $user
        );

        $this->assertEquals('200000.00', number_format((float) $service->calculateTotalExpenses(), 2, '.', ''));
    }

    public function test_cancelled_expense_is_excluded_from_total_expenses(): void
    {
        // INV-009
        $user    = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 500000]);

        $service = app(ExpenseCalculationService::class);
        $tx = $service->createExpenseTransaction(
            $this->minimalPaidData($account->id, ['amount' => '100000']),
            $user
        );

        $service->cancelExpenseTransaction($tx);

        $this->assertEquals('0', $service->calculateTotalExpenses());
    }

    public function test_unpaid_expense_increases_outstanding_payables(): void
    {
        // FT-029: Payable no double count.
        $user    = $this->makeUser();
        $service = app(ExpenseCalculationService::class);

        $service->createExpenseTransaction(
            $this->minimalUnpaidData(['amount' => '75000']),
            $user
        );

        $payables = $service->calculateOutstandingPayables();
        $this->assertEquals('75000.00', number_format((float) $payables, 2, '.', ''));
    }

    public function test_paid_expense_does_not_appear_in_outstanding_payables(): void
    {
        // INV-012: payables never create additional Expense.
        $user    = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 500000]);

        $service = app(ExpenseCalculationService::class);
        $service->createExpenseTransaction(
            $this->minimalPaidData($account->id, ['amount' => '50000']),
            $user
        );

        // Paid expense should NOT appear in outstanding payables.
        $this->assertEquals('0', $service->calculateOutstandingPayables());
    }

    // =========================================================================
    // 6. TRANSACTION INTEGRITY
    // =========================================================================

    public function test_transaction_id_is_generated_unique_server_side(): void
    {
        $user    = $this->makeUser();
        $service = app(ExpenseCalculationService::class);

        $tx1 = $service->createExpenseTransaction($this->minimalUnpaidData(), $user);
        $tx2 = $service->createExpenseTransaction($this->minimalUnpaidData(), $user);

        $this->assertNotEmpty($tx1->transaction_id);
        $this->assertNotEmpty($tx2->transaction_id);
        $this->assertNotEquals($tx1->transaction_id, $tx2->transaction_id);
    }

    public function test_financial_records_are_not_deleted_on_cancellation(): void
    {
        // INV-019: history must be preserved.
        $user    = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 500000]);

        $service = app(ExpenseCalculationService::class);
        $tx      = $service->createExpenseTransaction(
            $this->minimalPaidData($account->id),
            $user
        );

        $id = $tx->id;

        $service->cancelExpenseTransaction($tx);

        // Row must still exist in the database.
        $this->assertDatabaseHas('expense_transactions', [
            'id'            => $id,
            'record_status' => 'cancelled',
        ]);

        // Count must still be 1 (not deleted).
        $this->assertCount(1, ExpenseTransaction::all());
    }

    public function test_cancelling_already_cancelled_expense_throws_exception(): void
    {
        $user    = $this->makeUser();
        $service = app(ExpenseCalculationService::class);

        $tx = $service->createExpenseTransaction(
            $this->minimalUnpaidData(),
            $user
        );

        $service->cancelExpenseTransaction($tx);

        $this->expectException(\InvalidArgumentException::class);
        $service->cancelExpenseTransaction($tx);
    }

    // =========================================================================
    // 7. VALIDATION
    // =========================================================================

    public function test_required_fields_are_validated(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        Livewire::test(ManageExpense::class)
            ->set('transaction_date', '')
            ->set('expense_category', '')
            ->set('amount', '')
            ->set('payment_status', 'unpaid')
            ->call('saveExpense')
            ->assertHasErrors(['transaction_date', 'expense_category', 'amount']);
    }

    public function test_invalid_expense_category_is_rejected(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        Livewire::test(ManageExpense::class)
            ->set('transaction_date', now()->format('Y-m-d'))
            ->set('transaction_name', 'Category Test')
            ->set('expense_category', 'Totally Invalid Category')
            ->set('amount', '50000')
            ->set('payment_status', 'unpaid')
            ->call('saveExpense')
            ->assertHasErrors(['expense_category']);
    }

    public function test_all_valid_expense_categories_are_accepted(): void
    {
        $user    = $this->makeUser();
        $service = app(ExpenseCalculationService::class);

        foreach (ExpenseTransaction::CATEGORIES as $category) {
            $tx = $service->createExpenseTransaction(
                $this->minimalUnpaidData(['expense_category' => $category]),
                $user
            );

            $this->assertEquals($category, $tx->expense_category);
        }

        $this->assertCount(count(ExpenseTransaction::CATEGORIES), ExpenseTransaction::all());
    }

    public function test_invalid_payment_status_is_rejected(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        Livewire::test(ManageExpense::class)
            ->set('transaction_date', now()->format('Y-m-d'))
            ->set('transaction_name', 'Status Test')
            ->set('expense_category', 'Operational')
            ->set('amount', '50000')
            ->set('payment_status', 'invalid_status')
            ->call('saveExpense')
            ->assertHasErrors(['payment_status']);
    }

    // =========================================================================
    // 8. LIVEWIRE UI FEATURES
    // =========================================================================

    public function test_expense_list_is_paginated(): void
    {
        $user    = $this->makeUser();
        $service = app(ExpenseCalculationService::class);

        for ($i = 0; $i < 20; $i++) {
            $service->createExpenseTransaction(
                $this->minimalUnpaidData(['expense_category' => 'Operational', 'description' => 'Expense ' . $i]),
                $user
            );
        }

        $this->actingAs($user);

        Livewire::test(ManageExpense::class)
            ->assertSee('Operational');
    }

    public function test_expense_cancellation_via_livewire_preserves_record(): void
    {
        $user    = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 500000]);

        $service = app(ExpenseCalculationService::class);
        $tx = $service->createExpenseTransaction(
            $this->minimalPaidData($account->id),
            $user
        );

        $this->actingAs($user);

        Livewire::test(ManageExpense::class)
            ->call('cancelExpense', $tx->id);

        $this->assertDatabaseHas('expense_transactions', [
            'id'            => $tx->id,
            'record_status' => 'cancelled',
        ]);
    }

    public function test_total_company_balance_reflects_paid_expense(): void
    {
        $user     = $this->makeUser();
        $account1 = $this->makeActiveAccount(['name' => 'Cash', 'opening_balance' => 500000]);
        $account2 = $this->makeActiveAccount(['name' => 'Bank', 'opening_balance' => 300000]);

        $service = app(ExpenseCalculationService::class);

        // Pay 100000 from account1.
        $service->createExpenseTransaction(
            $this->minimalPaidData($account1->id, ['amount' => '100000']),
            $user
        );

        $balanceService = app(AccountBalanceService::class);

        // Total = (500000 - 100000) + 300000 = 700000
        $this->assertEquals(700000.0, $balanceService->calculateTotalCompanyBalance());
    }

    // =========================================================================
    // 9. INCOME vs EXPENSE — NO CROSS-CONTAMINATION (regression)
    // =========================================================================

    public function test_expense_does_not_appear_in_income_revenue(): void
    {
        // INV-012: payables never create additional Expense (and vice versa for income).
        $user    = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 500000]);

        $expenseService = app(ExpenseCalculationService::class);
        $expenseService->createExpenseTransaction(
            $this->minimalPaidData($account->id, ['amount' => '200000']),
            $user
        );

        // Income revenue should remain zero.
        $incomeService = app(\App\Services\IncomeCalculationService::class);
        $this->assertEquals('0', $incomeService->calculateRevenue());
    }

    public function test_expense_and_income_both_affect_account_balance_correctly(): void
    {
        // Combined: opening + paid income - paid expense.
        $user    = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 100000]);

        $item = \App\Models\MenuItem::create([
            'name'          => 'Test Cake',
            'category'      => 'Cake Sales',
            'current_price' => 50000.00,
            'is_active'     => true,
        ]);

        $incomeService  = app(\App\Services\IncomeCalculationService::class);
        $expenseService = app(ExpenseCalculationService::class);

        // Add income: 1 × 50000 = 50000
        $incomeService->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => null,
            'account_id'          => $account->id,
            'payment_status'      => 'paid',
        ], $user);

        // Add expense: 30000
        $expenseService->createExpenseTransaction(
            $this->minimalPaidData($account->id, ['amount' => '30000']),
            $user
        );

        $balanceService = app(AccountBalanceService::class);

        // 100000 (opening) + 50000 (income) - 30000 (expense) = 120000
        $this->assertEquals(120000.0, $balanceService->calculateBalance($account));
    }

    // =========================================================================
    // 10. FINANCIAL HISTORY PRESERVATION
    // =========================================================================

    public function test_expense_record_status_defaults_to_active(): void
    {
        $user    = $this->makeUser();
        $service = app(ExpenseCalculationService::class);

        $tx = $service->createExpenseTransaction(
            $this->minimalUnpaidData(),
            $user
        );

        $this->assertEquals('active', $tx->record_status);
    }

    public function test_paid_at_is_set_for_paid_expense(): void
    {
        $user    = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 500000]);
        $service = app(ExpenseCalculationService::class);

        $tx = $service->createExpenseTransaction(
            $this->minimalPaidData($account->id),
            $user
        );

        $this->assertNotNull($tx->paid_at);
    }

    public function test_paid_at_is_null_for_unpaid_expense(): void
    {
        $user    = $this->makeUser();
        $service = app(ExpenseCalculationService::class);

        $tx = $service->createExpenseTransaction(
            $this->minimalUnpaidData(),
            $user
        );

        $this->assertNull($tx->paid_at);
    }

    public function test_created_by_is_set_correctly(): void
    {
        $user    = $this->makeUser();
        $service = app(ExpenseCalculationService::class);

        $tx = $service->createExpenseTransaction(
            $this->minimalUnpaidData(),
            $user
        );

        $this->assertEquals($user->id, $tx->created_by);
    }

    // =========================================================================
    // SECTION: TRANSACTION NAME & ASSET PROFIT-ELIGIBILITY TESTS
    // =========================================================================

    public function test_expense_transaction_name_is_stored_and_displayed(): void
    {
        $user = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 500000]);
        $service = app(ExpenseCalculationService::class);

        $tx = $service->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_name' => 'Oven Equipment Purchase',
            'expense_category' => 'Asset',
            'description'      => 'Baking oven for kitchen',
            'amount'           => '150000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ], $user);

        $this->assertEquals('Oven Equipment Purchase', $tx->transaction_name);
        $this->assertDatabaseHas('expense_transactions', [
            'id'               => $tx->id,
            'transaction_name' => 'Oven Equipment Purchase',
        ]);

        Livewire::actingAs($user)
            ->test(ManageExpense::class)
            ->assertSee('Oven Equipment Purchase');
    }

    public function test_paid_asset_decreases_cash_balance_but_does_not_reduce_net_profit(): void
    {
        $user = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 1000000]);
        $expenseService = app(ExpenseCalculationService::class);
        $balanceService = app(AccountBalanceService::class);

        // Paid Asset expense of 300,000
        $expenseService->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_name' => 'New Mixer Machine',
            'expense_category' => 'Asset',
            'amount'           => '300000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ], $user);

        // Cash account is reduced by 300,000 (1,000,000 -> 700,000)
        $this->assertEquals(700000.00, $balanceService->calculateBalance($account));

        // Profit-eligible expenses sum is 0
        $this->assertEquals('0', $expenseService->calculateProfitEligibleExpenses());

        // Total expenses sum still includes Asset
        $this->assertEquals('300000.00', $expenseService->calculateTotalExpenses());
    }

    public function test_profit_eligible_expense_categories_reduce_net_profit(): void
    {
        $user = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 2000000]);
        $service = app(ExpenseCalculationService::class);

        $categories = [
            'COGS / Cake Production' => '50000',
            'Operational'            => '40000',
            'Marketing'              => '30000',
            'Salary'                 => '20000',
            'Rent'                   => '10000',
            'Employee Salaries'      => '15000',
        ];

        foreach ($categories as $cat => $amt) {
            $service->createExpenseTransaction([
                'transaction_date' => now()->format('Y-m-d'),
                'transaction_name' => 'Expense ' . $cat,
                'expense_category' => $cat,
                'amount'           => $amt,
                'account_id'       => $account->id,
                'payment_status'   => 'paid',
            ], $user);
        }

        // Total profit-eligible expenses: 50000 + 40000 + 30000 + 20000 + 10000 + 15000 = 165000
        $this->assertEquals('165000.00', $service->calculateProfitEligibleExpenses());
    }

    public function test_editing_expense_between_asset_and_profit_eligible_updates_net_profit(): void
    {
        $user = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 1000000]);
        $service = app(ExpenseCalculationService::class);

        // Initially created as Asset (non-profit-eligible)
        $expense = $service->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_name' => 'Ambiguous Item',
            'expense_category' => 'Asset',
            'amount'           => '100000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ], $user);

        $this->assertEquals('0', $service->calculateProfitEligibleExpenses());

        // Edit category from Asset -> Operational (profit-eligible)
        $service->updateExpenseTransaction($expense, [
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_name' => 'Ambiguous Item',
            'expense_category' => 'Operational',
            'amount'           => '100000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ], $user);

        $this->assertEquals('100000.00', $service->calculateProfitEligibleExpenses());

        // Edit back from Operational -> Asset
        $service->updateExpenseTransaction($expense, [
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_name' => 'Ambiguous Item',
            'expense_category' => 'Asset',
            'amount'           => '100000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ], $user);

        $this->assertEquals('0', $service->calculateProfitEligibleExpenses());
    }

    public function test_cancelling_asset_expense_does_not_alter_profit_eligible_expenses(): void
    {
        $user = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 1000000]);
        $service = app(ExpenseCalculationService::class);

        $expense = $service->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_name' => 'Asset to Cancel',
            'expense_category' => 'Asset',
            'amount'           => '200000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ], $user);

        $this->assertEquals('0', $service->calculateProfitEligibleExpenses());

        $service->cancelExpenseTransaction($expense, $user);

        $this->assertEquals('0', $service->calculateProfitEligibleExpenses());
    }

    public function test_expense_form_initializes_transaction_name_as_empty(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        Livewire::test(ManageExpense::class)
            ->assertSet('transaction_name', '')
            ->call('createExpense')
            ->assertSet('transaction_name', '')
            ->call('resetForm')
            ->assertSet('transaction_name', '');
    }

    public function test_expense_total_expenses_card_displays_canonical_total(): void
    {
        $user = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 1000000]);
        $this->actingAs($user);

        $expenseService = app(ExpenseCalculationService::class);

        // Paid active expense (50000)
        $expenseService->createExpenseTransaction($this->minimalPaidData($account->id, ['amount' => '50000']), $user);

        // Unpaid active expense (30000) — excluded from total expenses
        $expenseService->createExpenseTransaction($this->minimalUnpaidData(['amount' => '30000']), $user);

        // Cancelled expense (80000) — excluded from total expenses
        $cancelled = $expenseService->createExpenseTransaction($this->minimalPaidData($account->id, ['amount' => '80000']), $user);
        $expenseService->cancelExpenseTransaction($cancelled);

        Livewire::test(ManageExpense::class)
            ->assertSeeHtml('Total Expenses')
            ->assertSeeHtml('Rp 50.000');
    }
}

