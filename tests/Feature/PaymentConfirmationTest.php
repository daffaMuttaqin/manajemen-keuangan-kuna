<?php

namespace Tests\Feature;

use App\Livewire\Expense\ManageExpense;
use App\Livewire\Income\ManageIncome;
use App\Models\Account;
use App\Models\ExpenseTransaction;
use App\Models\IncomeTransaction;
use App\Models\MenuItem;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\ExpenseCalculationService;
use App\Services\IncomeCalculationService;
use App\Services\PaymentConfirmationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentConfirmationTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeActiveAccount(array $overrides = []): Account
    {
        return Account::create(array_merge([
            'name'            => 'Test Cash Account',
            'account_type'    => 'cash',
            'opening_balance' => 100000.00,
            'is_active'       => true,
        ], $overrides));
    }

    private function makeMenuItem(array $overrides = []): MenuItem
    {
        return MenuItem::create(array_merge([
            'name'          => 'Chocolate Cake',
            'category'      => 'Cake',
            'current_price' => 50000.00,
            'is_active'     => true,
        ], $overrides));
    }

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function createUnpaidIncome(Account $account, MenuItem $item, User $user, float $qty = 1.0): IncomeTransaction
    {
        $service = app(IncomeCalculationService::class);

        return $service->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => (string) $qty,
            'discount_percentage' => '0',
            'category'            => 'Cake',
            'description'         => 'Unpaid test income',
            'account_id'          => $account->id,
            'payment_status'      => 'unpaid',
        ], $user);
    }

    private function createUnpaidExpense(Account $account, User $user, string $amount = '20000'): ExpenseTransaction
    {
        $service = app(ExpenseCalculationService::class);

        return $service->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'expense_category' => 'Operational',
            'description'      => 'Unpaid test expense',
            'amount'           => $amount,
            'account_id'       => $account->id,
            'payment_status'   => 'unpaid',
        ], $user);
    }

    // =========================================================================
    // 1. INCOME PAYMENT CONFIRMATION TESTS (1 - 10)
    // =========================================================================

    public function test_1_unpaid_income_does_not_affect_balance(): void
    {
        $account = $this->makeActiveAccount(['opening_balance' => 100000]);
        $item    = $this->makeMenuItem(['current_price' => 50000]);
        $user    = $this->makeUser();

        $this->createUnpaidIncome($account, $item, $user);

        $balanceService = app(AccountBalanceService::class);
        $this->assertEquals(100000.0, $balanceService->calculateBalance($account));
    }

    public function test_2_confirming_unpaid_income_increases_balance_exactly_once(): void
    {
        $account = $this->makeActiveAccount(['opening_balance' => 100000]);
        $item    = $this->makeMenuItem(['current_price' => 50000]);
        $user    = $this->makeUser();

        $income = $this->createUnpaidIncome($account, $item, $user);

        $confirmService = app(PaymentConfirmationService::class);
        $confirmService->confirmIncomePayment($income);

        $balanceService = app(AccountBalanceService::class);
        $this->assertEquals(150000.0, $balanceService->calculateBalance($account));
    }

    public function test_3_confirming_income_changes_payment_status_to_paid(): void
    {
        $account = $this->makeActiveAccount();
        $item    = $this->makeMenuItem();
        $user    = $this->makeUser();

        $income = $this->createUnpaidIncome($account, $item, $user);

        $confirmService = app(PaymentConfirmationService::class);
        $confirmService->confirmIncomePayment($income);

        $income->refresh();
        $this->assertEquals('paid', $income->payment_status);
        $this->assertTrue($income->isPaid());
    }

    public function test_4_confirming_income_sets_paid_at(): void
    {
        $account = $this->makeActiveAccount();
        $item    = $this->makeMenuItem();
        $user    = $this->makeUser();

        $income = $this->createUnpaidIncome($account, $item, $user);
        $this->assertNull($income->paid_at);

        $confirmService = app(PaymentConfirmationService::class);
        $confirmService->confirmIncomePayment($income);

        $income->refresh();
        $this->assertNotNull($income->paid_at);
    }

    public function test_5_confirmed_income_no_longer_appears_in_outstanding_receivables(): void
    {
        $account = $this->makeActiveAccount();
        $item    = $this->makeMenuItem(['current_price' => 50000]);
        $user    = $this->makeUser();

        $income = $this->createUnpaidIncome($account, $item, $user);

        $incomeService = app(IncomeCalculationService::class);
        $this->assertEquals('50000.00', number_format((float) $incomeService->calculateOutstandingReceivables(), 2, '.', ''));

        $confirmService = app(PaymentConfirmationService::class);
        $confirmService->confirmIncomePayment($income);

        $this->assertEquals('0', $incomeService->calculateOutstandingReceivables());
    }

    public function test_6_repeated_confirmation_does_not_double_count(): void
    {
        $account = $this->makeActiveAccount(['opening_balance' => 100000]);
        $item    = $this->makeMenuItem(['current_price' => 50000]);
        $user    = $this->makeUser();

        $income = $this->createUnpaidIncome($account, $item, $user);

        $confirmService = app(PaymentConfirmationService::class);
        $confirmService->confirmIncomePayment($income);

        // Second confirmation attempt throws exception
        try {
            $confirmService->confirmIncomePayment($income);
        } catch (\InvalidArgumentException $e) {
            // Exception expected per INV-013
        }

        $balanceService = app(AccountBalanceService::class);
        $this->assertEquals(150000.0, $balanceService->calculateBalance($account));
    }

    public function test_7_cancelled_unpaid_income_cannot_be_confirmed(): void
    {
        $account = $this->makeActiveAccount(['opening_balance' => 100000]);
        $item    = $this->makeMenuItem(['current_price' => 50000]);
        $user    = $this->makeUser();

        $income = $this->createUnpaidIncome($account, $item, $user);

        $incomeService = app(IncomeCalculationService::class);
        $incomeService->cancelIncomeTransaction($income);

        $this->expectException(\InvalidArgumentException::class);

        $confirmService = app(PaymentConfirmationService::class);
        $confirmService->confirmIncomePayment($income);
    }

    public function test_8_cancelled_income_remains_excluded(): void
    {
        $account = $this->makeActiveAccount(['opening_balance' => 100000]);
        $item    = $this->makeMenuItem(['current_price' => 50000]);
        $user    = $this->makeUser();

        $income = $this->createUnpaidIncome($account, $item, $user);

        $incomeService = app(IncomeCalculationService::class);
        $incomeService->cancelIncomeTransaction($income);

        $balanceService = app(AccountBalanceService::class);
        $this->assertEquals(100000.0, $balanceService->calculateBalance($account));
        $this->assertEquals('0', $incomeService->calculateRevenue());
    }

    public function test_9_inactive_account_cannot_be_used_for_confirmation(): void
    {
        $account = $this->makeActiveAccount(['opening_balance' => 100000]);
        $item    = $this->makeMenuItem(['current_price' => 50000]);
        $user    = $this->makeUser();

        $income = $this->createUnpaidIncome($account, $item, $user);

        // Deactivate account
        $account->update(['is_active' => false]);

        $confirmService = app(PaymentConfirmationService::class);

        try {
            $confirmService->confirmIncomePayment($income);
            $this->fail('Should have thrown InvalidArgumentException for inactive account.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Inactive', $e->getMessage());
        }

        $income->refresh();
        $this->assertEquals('unpaid', $income->payment_status);
    }

    public function test_10_transaction_record_is_preserved_on_confirmation(): void
    {
        $account = $this->makeActiveAccount();
        $item    = $this->makeMenuItem();
        $user    = $this->makeUser();

        $income = $this->createUnpaidIncome($account, $item, $user);
        $id     = $income->id;
        $uuid   = $income->transaction_id;

        $confirmService = app(PaymentConfirmationService::class);
        $confirmService->confirmIncomePayment($income);

        $this->assertCount(1, IncomeTransaction::all());
        $this->assertDatabaseHas('income_transactions', [
            'id'             => $id,
            'transaction_id' => $uuid,
            'payment_status' => 'paid',
        ]);
    }

    // =========================================================================
    // 2. EXPENSE PAYMENT CONFIRMATION TESTS (11 - 20)
    // =========================================================================

    public function test_11_unpaid_expense_does_not_affect_balance(): void
    {
        $account = $this->makeActiveAccount(['opening_balance' => 100000]);
        $user    = $this->makeUser();

        $this->createUnpaidExpense($account, $user, '20000');

        $balanceService = app(AccountBalanceService::class);
        $this->assertEquals(100000.0, $balanceService->calculateBalance($account));
    }

    public function test_12_confirming_unpaid_expense_decreases_balance_exactly_once(): void
    {
        $account = $this->makeActiveAccount(['opening_balance' => 100000]);
        $user    = $this->makeUser();

        $expense = $this->createUnpaidExpense($account, $user, '20000');

        $confirmService = app(PaymentConfirmationService::class);
        $confirmService->confirmExpensePayment($expense);

        $balanceService = app(AccountBalanceService::class);
        $this->assertEquals(80000.0, $balanceService->calculateBalance($account));
    }

    public function test_13_confirming_expense_changes_payment_status_to_paid(): void
    {
        $account = $this->makeActiveAccount();
        $user    = $this->makeUser();

        $expense = $this->createUnpaidExpense($account, $user);

        $confirmService = app(PaymentConfirmationService::class);
        $confirmService->confirmExpensePayment($expense);

        $expense->refresh();
        $this->assertEquals('paid', $expense->payment_status);
        $this->assertTrue($expense->isPaid());
    }

    public function test_14_confirming_expense_sets_paid_at(): void
    {
        $account = $this->makeActiveAccount();
        $user    = $this->makeUser();

        $expense = $this->createUnpaidExpense($account, $user);
        $this->assertNull($expense->paid_at);

        $confirmService = app(PaymentConfirmationService::class);
        $confirmService->confirmExpensePayment($expense);

        $expense->refresh();
        $this->assertNotNull($expense->paid_at);
    }

    public function test_15_confirmed_expense_no_longer_appears_in_outstanding_payables(): void
    {
        $account = $this->makeActiveAccount();
        $user    = $this->makeUser();

        $expense = $this->createUnpaidExpense($account, $user, '20000');

        $expenseService = app(ExpenseCalculationService::class);
        $this->assertEquals('20000.00', number_format((float) $expenseService->calculateOutstandingPayables(), 2, '.', ''));

        $confirmService = app(PaymentConfirmationService::class);
        $confirmService->confirmExpensePayment($expense);

        $this->assertEquals('0', $expenseService->calculateOutstandingPayables());
    }

    public function test_16_repeated_confirmation_of_expense_does_not_double_count(): void
    {
        $account = $this->makeActiveAccount(['opening_balance' => 100000]);
        $user    = $this->makeUser();

        $expense = $this->createUnpaidExpense($account, $user, '20000');

        $confirmService = app(PaymentConfirmationService::class);
        $confirmService->confirmExpensePayment($expense);

        try {
            $confirmService->confirmExpensePayment($expense);
        } catch (\InvalidArgumentException $e) {
            // Exception expected per INV-013
        }

        $balanceService = app(AccountBalanceService::class);
        $this->assertEquals(80000.0, $balanceService->calculateBalance($account));
    }

    public function test_17_cancelled_unpaid_expense_cannot_be_confirmed(): void
    {
        $account = $this->makeActiveAccount(['opening_balance' => 100000]);
        $user    = $this->makeUser();

        $expense = $this->createUnpaidExpense($account, $user, '20000');

        $expenseService = app(ExpenseCalculationService::class);
        $expenseService->cancelExpenseTransaction($expense);

        $this->expectException(\InvalidArgumentException::class);

        $confirmService = app(PaymentConfirmationService::class);
        $confirmService->confirmExpensePayment($expense);
    }

    public function test_18_cancelled_expense_remains_excluded(): void
    {
        $account = $this->makeActiveAccount(['opening_balance' => 100000]);
        $user    = $this->makeUser();

        $expense = $this->createUnpaidExpense($account, $user, '20000');

        $expenseService = app(ExpenseCalculationService::class);
        $expenseService->cancelExpenseTransaction($expense);

        $balanceService = app(AccountBalanceService::class);
        $this->assertEquals(100000.0, $balanceService->calculateBalance($account));
        $this->assertEquals('0', $expenseService->calculateTotalExpenses());
    }

    public function test_19_inactive_account_cannot_be_used_for_expense_confirmation(): void
    {
        $account = $this->makeActiveAccount(['opening_balance' => 100000]);
        $user    = $this->makeUser();

        $expense = $this->createUnpaidExpense($account, $user, '20000');

        $account->update(['is_active' => false]);

        $confirmService = app(PaymentConfirmationService::class);

        try {
            $confirmService->confirmExpensePayment($expense);
            $this->fail('Should have thrown InvalidArgumentException for inactive account.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Inactive', $e->getMessage());
        }

        $expense->refresh();
        $this->assertEquals('unpaid', $expense->payment_status);
    }

    public function test_20_expense_transaction_record_is_preserved_on_confirmation(): void
    {
        $account = $this->makeActiveAccount();
        $user    = $this->makeUser();

        $expense = $this->createUnpaidExpense($account, $user);
        $id      = $expense->id;
        $uuid    = $expense->transaction_id;

        $confirmService = app(PaymentConfirmationService::class);
        $confirmService->confirmExpensePayment($expense);

        $this->assertCount(1, ExpenseTransaction::all());
        $this->assertDatabaseHas('expense_transactions', [
            'id'             => $id,
            'transaction_id' => $uuid,
            'payment_status' => 'paid',
        ]);
    }

    // =========================================================================
    // 3. ATOMICITY & AUTHORITY TESTS (21 - 25)
    // =========================================================================

    public function test_21_failed_confirmation_produces_no_partial_state_change(): void
    {
        $account = $this->makeActiveAccount(['opening_balance' => 100000]);
        $item    = $this->makeMenuItem(['current_price' => 50000]);
        $user    = $this->makeUser();

        $income = $this->createUnpaidIncome($account, $item, $user);

        // Attempt confirmation with non-existent account ID
        try {
            $confirmService = app(PaymentConfirmationService::class);
            $confirmService->confirmIncomePayment($income, 999999);
        } catch (\Exception $e) {
            // Exception expected
        }

        $income->refresh();
        $this->assertEquals('unpaid', $income->payment_status);
        $this->assertNull($income->paid_at);

        $balanceService = app(AccountBalanceService::class);
        $this->assertEquals(100000.0, $balanceService->calculateBalance($account));
    }

    public function test_22_balance_and_payment_status_remain_consistent_after_failure(): void
    {
        $account = $this->makeActiveAccount(['opening_balance' => 100000]);
        $user    = $this->makeUser();

        $expense = $this->createUnpaidExpense($account, $user, '20000');

        try {
            $confirmService = app(PaymentConfirmationService::class);
            $confirmService->confirmExpensePayment($expense, 999999);
        } catch (\Exception $e) {
            // Exception expected
        }

        $expense->refresh();
        $this->assertEquals('unpaid', $expense->payment_status);

        $balanceService = app(AccountBalanceService::class);
        $this->assertEquals(100000.0, $balanceService->calculateBalance($account));
    }

    public function test_23_client_cannot_change_the_amount_during_confirmation(): void
    {
        $account = $this->makeActiveAccount(['opening_balance' => 100000]);
        $item    = $this->makeMenuItem(['current_price' => 50000]);
        $user    = $this->makeUser();

        $income = $this->createUnpaidIncome($account, $item, $user);

        // Confirming uses the persisted total_amount (50000)
        $confirmService = app(PaymentConfirmationService::class);
        $confirmService->confirmIncomePayment($income);

        $this->assertEquals('50000.00', $income->fresh()->total_amount);

        $balanceService = app(AccountBalanceService::class);
        $this->assertEquals(150000.0, $balanceService->calculateBalance($account));
    }

    public function test_24_confirmation_uses_the_persisted_transaction_amount(): void
    {
        $account = $this->makeActiveAccount(['opening_balance' => 100000]);
        $user    = $this->makeUser();

        $expense = $this->createUnpaidExpense($account, $user, '35000');

        $confirmService = app(PaymentConfirmationService::class);
        $confirmService->confirmExpensePayment($expense);

        $this->assertEquals('35000.00', $expense->fresh()->amount);

        $balanceService = app(AccountBalanceService::class);
        $this->assertEquals(65000.0, $balanceService->calculateBalance($account));
    }

    public function test_25_no_duplicate_transaction_is_created(): void
    {
        $account = $this->makeActiveAccount();
        $item    = $this->makeMenuItem();
        $user    = $this->makeUser();

        $income = $this->createUnpaidIncome($account, $item, $user);
        $this->assertCount(1, IncomeTransaction::all());

        $confirmService = app(PaymentConfirmationService::class);
        $confirmService->confirmIncomePayment($income);

        // Must still be 1 transaction in DB — not 2
        $this->assertCount(1, IncomeTransaction::all());
    }

    // =========================================================================
    // 4. FINANCIAL AUDIT SCENARIOS (A - J)
    // =========================================================================

    public function test_scenario_a_confirm_unpaid_income(): void
    {
        $account = $this->makeActiveAccount(['opening_balance' => 100000]);
        $item    = $this->makeMenuItem(['current_price' => 50000]);
        $user    = $this->makeUser();

        $income = $this->createUnpaidIncome($account, $item, $user);
        $confirmService = app(PaymentConfirmationService::class);
        $confirmService->confirmIncomePayment($income);

        $balanceService = app(AccountBalanceService::class);
        $this->assertEquals(150000.0, $balanceService->calculateBalance($account));
    }

    public function test_scenario_b_confirm_unpaid_expense(): void
    {
        $account = $this->makeActiveAccount(['opening_balance' => 100000]);
        $user    = $this->makeUser();

        $expense = $this->createUnpaidExpense($account, $user, '20000');
        $confirmService = app(PaymentConfirmationService::class);
        $confirmService->confirmExpensePayment($expense);

        $balanceService = app(AccountBalanceService::class);
        $this->assertEquals(80000.0, $balanceService->calculateBalance($account));
    }

    public function test_scenario_c_confirm_income_twice_preserves_single_effect(): void
    {
        $account = $this->makeActiveAccount(['opening_balance' => 100000]);
        $item    = $this->makeMenuItem(['current_price' => 50000]);
        $user    = $this->makeUser();

        $income = $this->createUnpaidIncome($account, $item, $user);
        $confirmService = app(PaymentConfirmationService::class);
        $confirmService->confirmIncomePayment($income);

        try {
            $confirmService->confirmIncomePayment($income);
        } catch (\InvalidArgumentException $e) {
            // Catch repeated attempt
        }

        $balanceService = app(AccountBalanceService::class);
        // Must be 150,000 NOT 200,000
        $this->assertEquals(150000.0, $balanceService->calculateBalance($account));
    }

    public function test_scenario_d_confirm_expense_twice_preserves_single_effect(): void
    {
        $account = $this->makeActiveAccount(['opening_balance' => 100000]);
        $user    = $this->makeUser();

        $expense = $this->createUnpaidExpense($account, $user, '20000');
        $confirmService = app(PaymentConfirmationService::class);
        $confirmService->confirmExpensePayment($expense);

        try {
            $confirmService->confirmExpensePayment($expense);
        } catch (\InvalidArgumentException $e) {
            // Catch repeated attempt
        }

        $balanceService = app(AccountBalanceService::class);
        // Must be 80,000 NOT 60,000
        $this->assertEquals(80000.0, $balanceService->calculateBalance($account));
    }

    public function test_scenario_e_cancelled_unpaid_income_confirmation_rejected(): void
    {
        $account = $this->makeActiveAccount(['opening_balance' => 100000]);
        $item    = $this->makeMenuItem(['current_price' => 50000]);
        $user    = $this->makeUser();

        $income = $this->createUnpaidIncome($account, $item, $user);
        app(IncomeCalculationService::class)->cancelIncomeTransaction($income);

        try {
            app(PaymentConfirmationService::class)->confirmIncomePayment($income);
        } catch (\InvalidArgumentException $e) {
            // Rejection expected
        }

        $balanceService = app(AccountBalanceService::class);
        $this->assertEquals(100000.0, $balanceService->calculateBalance($account));
    }

    public function test_scenario_f_cancelled_unpaid_expense_confirmation_rejected(): void
    {
        $account = $this->makeActiveAccount(['opening_balance' => 100000]);
        $user    = $this->makeUser();

        $expense = $this->createUnpaidExpense($account, $user, '20000');
        app(ExpenseCalculationService::class)->cancelExpenseTransaction($expense);

        try {
            app(PaymentConfirmationService::class)->confirmExpensePayment($expense);
        } catch (\InvalidArgumentException $e) {
            // Rejection expected
        }

        $balanceService = app(AccountBalanceService::class);
        $this->assertEquals(100000.0, $balanceService->calculateBalance($account));
    }

    public function test_scenario_g_unpaid_income_confirmation_fails_if_account_inactive(): void
    {
        $account = $this->makeActiveAccount(['opening_balance' => 100000]);
        $item    = $this->makeMenuItem(['current_price' => 50000]);
        $user    = $this->makeUser();

        $income = $this->createUnpaidIncome($account, $item, $user);
        $account->update(['is_active' => false]);

        try {
            app(PaymentConfirmationService::class)->confirmIncomePayment($income);
        } catch (\InvalidArgumentException $e) {
            // Expected
        }

        $balanceService = app(AccountBalanceService::class);
        // Excluded from company balance because inactive, but balance for account itself is 100000
        $this->assertEquals(100000.0, $balanceService->calculateBalance($account));
        $income->refresh();
        $this->assertEquals('unpaid', $income->payment_status);
    }

    public function test_scenario_h_unpaid_expense_confirmation_fails_if_account_inactive(): void
    {
        $account = $this->makeActiveAccount(['opening_balance' => 100000]);
        $user    = $this->makeUser();

        $expense = $this->createUnpaidExpense($account, $user, '20000');
        $account->update(['is_active' => false]);

        try {
            app(PaymentConfirmationService::class)->confirmExpensePayment($expense);
        } catch (\InvalidArgumentException $e) {
            // Expected
        }

        $balanceService = app(AccountBalanceService::class);
        $this->assertEquals(100000.0, $balanceService->calculateBalance($account));
        $expense->refresh();
        $this->assertEquals('unpaid', $expense->payment_status);
    }

    public function test_scenario_i_confirm_income_decreases_receivable_and_increases_cash_without_new_tx(): void
    {
        $account = $this->makeActiveAccount(['opening_balance' => 100000]);
        $item    = $this->makeMenuItem(['current_price' => 50000]);
        $user    = $this->makeUser();

        $income = $this->createUnpaidIncome($account, $item, $user);

        $incomeService  = app(IncomeCalculationService::class);
        $balanceService = app(AccountBalanceService::class);

        $this->assertEquals('50000.00', number_format((float) $incomeService->calculateOutstandingReceivables(), 2, '.', ''));
        $this->assertEquals(100000.0, $balanceService->calculateBalance($account));
        $this->assertCount(1, IncomeTransaction::all());

        app(PaymentConfirmationService::class)->confirmIncomePayment($income);

        $this->assertEquals('0', $incomeService->calculateOutstandingReceivables());
        $this->assertEquals(150000.0, $balanceService->calculateBalance($account));
        $this->assertCount(1, IncomeTransaction::all());
    }

    public function test_scenario_j_confirm_expense_decreases_payable_and_decreases_cash_without_new_tx(): void
    {
        $account = $this->makeActiveAccount(['opening_balance' => 100000]);
        $user    = $this->makeUser();

        $expense = $this->createUnpaidExpense($account, $user, '20000');

        $expenseService = app(ExpenseCalculationService::class);
        $balanceService = app(AccountBalanceService::class);

        $this->assertEquals('20000.00', number_format((float) $expenseService->calculateOutstandingPayables(), 2, '.', ''));
        $this->assertEquals(100000.0, $balanceService->calculateBalance($account));
        $this->assertCount(1, ExpenseTransaction::all());

        app(PaymentConfirmationService::class)->confirmExpensePayment($expense);

        $this->assertEquals('0', $expenseService->calculateOutstandingPayables());
        $this->assertEquals(80000.0, $balanceService->calculateBalance($account));
        $this->assertCount(1, ExpenseTransaction::all());
    }

    // =========================================================================
    // 5. LIVEWIRE UI PAYMENT CONFIRMATION TESTS
    // =========================================================================

    public function test_livewire_can_confirm_income_payment(): void
    {
        $account = $this->makeActiveAccount();
        $item    = $this->makeMenuItem();
        $user    = $this->makeUser();

        $income = $this->createUnpaidIncome($account, $item, $user);

        $this->actingAs($user);

        Livewire::test(ManageIncome::class)
            ->call('openConfirmModal', $income->id)
            ->set('confirm_account_id', $account->id)
            ->call('confirmPayment')
            ->assertHasNoErrors();

        $income->refresh();
        $this->assertEquals('paid', $income->payment_status);
        $this->assertNotNull($income->paid_at);
    }

    public function test_livewire_can_confirm_expense_payment(): void
    {
        $account = $this->makeActiveAccount();
        $user    = $this->makeUser();

        $expense = $this->createUnpaidExpense($account, $user);

        $this->actingAs($user);

        Livewire::test(ManageExpense::class)
            ->call('openConfirmModal', $expense->id)
            ->set('confirm_account_id', $account->id)
            ->call('confirmPayment')
            ->assertHasNoErrors();

        $expense->refresh();
        $this->assertEquals('paid', $expense->payment_status);
        $this->assertNotNull($expense->paid_at);
    }
}
