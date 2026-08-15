<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\ExpenseTransaction;
use App\Models\IncomeTransaction;
use App\Models\MenuItem;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\ExpenseCalculationService;
use App\Services\IncomeCalculationService;
use App\Services\PaymentConfirmationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class Phase6CancellationAndEditingTest extends TestCase
{
    use RefreshDatabase;

    private function makeActiveAccount(array $overrides = []): Account
    {
        return Account::create(array_merge([
            'name'            => 'BCA Ritel',
            'account_type'    => 'bank',
            'opening_balance' => 100000.00,
            'is_active'       => true,
        ], $overrides));
    }

    private function makeMenuItem(array $overrides = []): MenuItem
    {
        return MenuItem::create(array_merge([
            'name'          => 'Chocolate Fudge Cake',
            'category'      => 'Cake Sales',
            'current_price' => 50000.00,
            'is_active'     => true,
        ], $overrides));
    }

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    // =========================================================================
    // SECTION 1: INCOME EDITING
    // =========================================================================

    public function test_edit_unpaid_income_amount_and_verify(): void
    {
        $user = $this->makeUser();
        $item = $this->makeMenuItem(['current_price' => 50000.00]);
        $service = app(IncomeCalculationService::class);
        $balanceService = app(AccountBalanceService::class);

        $income = $service->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => 'Original description',
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        // Edit amount: change qty to 2
        $updated = $service->updateIncomeTransaction($income, [
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '2',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => 'Original description',
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ]);

        $this->assertEquals('100000.00', $updated->total_amount);
        $this->assertEquals('100000.00', $service->calculateOutstandingReceivables());
        // Cash balance should remain unchanged (INV-003)
        $account = $this->makeActiveAccount(['opening_balance' => 100000.00]);
        $this->assertEquals(100000.00, $balanceService->calculateBalance($account));
        // Verify same row ID and same transaction ID (no duplicates)
        $this->assertEquals($income->id, $updated->id);
        $this->assertEquals($income->transaction_id, $updated->transaction_id);
        $this->assertCount(1, IncomeTransaction::all());
    }

    public function test_edit_paid_income_amount_and_verify(): void
    {
        $user = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 100000.00]);
        $item = $this->makeMenuItem(['current_price' => 50000.00]);
        $service = app(IncomeCalculationService::class);
        $balanceService = app(AccountBalanceService::class);

        $income = $service->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => 'Original description',
            'account_id'          => $account->id,
            'payment_status'      => 'paid',
        ], $user);

        $this->assertEquals(150000.00, $balanceService->calculateBalance($account));

        // Edit amount: qty 1 -> qty 3 (amount becomes 150000.00)
        $updated = $service->updateIncomeTransaction($income, [
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '3',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => 'Original description',
            'account_id'          => $account->id,
            'payment_status'      => 'paid',
        ]);

        $this->assertEquals('150000.00', $updated->total_amount);
        // Dynamic balance: 100000 + 150000 = 250000
        $this->assertEquals(250000.00, $balanceService->calculateBalance($account));
        $this->assertEquals($income->id, $updated->id);
        $this->assertEquals($income->transaction_id, $updated->transaction_id);
        $this->assertCount(1, IncomeTransaction::all());
    }

    public function test_edit_unpaid_income_non_financial_fields(): void
    {
        $user = $this->makeUser();
        $item = $this->makeMenuItem();
        $service = app(IncomeCalculationService::class);

        $income = $service->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => 'Old desc',
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        $updated = $service->updateIncomeTransaction($income, [
            'transaction_date'    => '2026-08-01',
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Snacks',
            'description'         => 'New desc',
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ]);

        $this->assertEquals('2026-08-01', $updated->transaction_date->format('Y-m-d'));
        $this->assertEquals('Snacks', $updated->category);
        $this->assertEquals('New desc', $updated->description);
    }

    // =========================================================================
    // SECTION 2: EXPENSE EDITING
    // =========================================================================

    public function test_edit_unpaid_expense_amount_and_verify(): void
    {
        $user = $this->makeUser();
        $service = app(ExpenseCalculationService::class);
        $balanceService = app(AccountBalanceService::class);

        $expense = $service->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'expense_category' => 'Operational',
            'description'      => 'Old desc',
            'amount'           => '10000',
            'account_id'       => null,
            'payment_status'   => 'unpaid',
        ], $user);

        $updated = $service->updateExpenseTransaction($expense, [
            'transaction_date' => now()->format('Y-m-d'),
            'expense_category' => 'Operational',
            'description'      => 'Old desc',
            'amount'           => '15000',
            'account_id'       => null,
            'payment_status'   => 'unpaid',
        ]);

        $this->assertEquals('15000.00', $updated->amount);
        $this->assertEquals('15000.00', $service->calculateOutstandingPayables());

        $account = $this->makeActiveAccount(['opening_balance' => 100000.00]);
        $this->assertEquals(100000.00, $balanceService->calculateBalance($account));
        $this->assertEquals($expense->id, $updated->id);
        $this->assertEquals($expense->transaction_id, $updated->transaction_id);
        $this->assertCount(1, ExpenseTransaction::all());
    }

    public function test_edit_paid_expense_amount_and_verify(): void
    {
        $user = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 100000.00]);
        $service = app(ExpenseCalculationService::class);
        $balanceService = app(AccountBalanceService::class);

        $expense = $service->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'expense_category' => 'Operational',
            'description'      => 'Old desc',
            'amount'           => '20000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ], $user);

        $this->assertEquals(80000.00, $balanceService->calculateBalance($account));

        // Edit amount: 20000 -> 30000
        $updated = $service->updateExpenseTransaction($expense, [
            'transaction_date' => now()->format('Y-m-d'),
            'expense_category' => 'Operational',
            'description'      => 'Old desc',
            'amount'           => '30000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ]);

        $this->assertEquals('30000.00', $updated->amount);
        // Balance: 100000 - 30000 = 70000
        $this->assertEquals(70000.00, $balanceService->calculateBalance($account));
        $this->assertEquals($expense->id, $updated->id);
        $this->assertEquals($expense->transaction_id, $updated->transaction_id);
        $this->assertCount(1, ExpenseTransaction::all());
    }

    // =========================================================================
    // SECTION 3: CANCELLATION
    // =========================================================================

    public function test_cancel_unpaid_income(): void
    {
        $user = $this->makeUser();
        $item = $this->makeMenuItem(['current_price' => 50000.00]);
        $service = app(IncomeCalculationService::class);

        $income = $service->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => 'Unpaid item',
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        $this->assertEquals('50000.00', $service->calculateOutstandingReceivables());

        $service->cancelIncomeTransaction($income);

        $income->refresh();
        $this->assertTrue($income->isCancelled());
        $this->assertEquals('0', $service->calculateOutstandingReceivables());
        $this->assertCount(1, IncomeTransaction::all()); // preserved
    }

    public function test_cancel_paid_income(): void
    {
        $user = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 100000.00]);
        $item = $this->makeMenuItem(['current_price' => 50000.00]);
        $service = app(IncomeCalculationService::class);
        $balanceService = app(AccountBalanceService::class);

        $income = $service->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => 'Paid item',
            'account_id'          => $account->id,
            'payment_status'      => 'paid',
        ], $user);

        $this->assertEquals(150000.00, $balanceService->calculateBalance($account));

        $service->cancelIncomeTransaction($income);

        $income->refresh();
        $this->assertTrue($income->isCancelled());
        // Balance reverts to 100000
        $this->assertEquals(100000.00, $balanceService->calculateBalance($account));
        $this->assertCount(1, IncomeTransaction::all());
    }

    public function test_cancel_unpaid_expense(): void
    {
        $user = $this->makeUser();
        $service = app(ExpenseCalculationService::class);

        $expense = $service->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'expense_category' => 'Operational',
            'description'      => 'Unpaid expense',
            'amount'           => '20000',
            'account_id'       => null,
            'payment_status'   => 'unpaid',
        ], $user);

        $this->assertEquals('20000.00', $service->calculateOutstandingPayables());

        $service->cancelExpenseTransaction($expense);

        $expense->refresh();
        $this->assertTrue($expense->isCancelled());
        $this->assertEquals('0', $service->calculateOutstandingPayables());
        $this->assertCount(1, ExpenseTransaction::all());
    }

    public function test_cancel_paid_expense(): void
    {
        $user = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 100000.00]);
        $service = app(ExpenseCalculationService::class);
        $balanceService = app(AccountBalanceService::class);

        $expense = $service->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'expense_category' => 'Operational',
            'description'      => 'Paid expense',
            'amount'           => '20000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ], $user);

        $this->assertEquals(80000.00, $balanceService->calculateBalance($account));

        $service->cancelExpenseTransaction($expense);

        $expense->refresh();
        $this->assertTrue($expense->isCancelled());
        // Balance reverts to 100000
        $this->assertEquals(100000.00, $balanceService->calculateBalance($account));
        $this->assertCount(1, ExpenseTransaction::all());
    }

    // =========================================================================
    // SECTION 4: INVALID TRANSITIONS
    // =========================================================================

    public function test_cancel_already_cancelled_income_throws_exception(): void
    {
        $user = $this->makeUser();
        $item = $this->makeMenuItem();
        $service = app(IncomeCalculationService::class);

        $income = $service->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => 'Test',
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        $service->cancelIncomeTransaction($income);

        $this->expectException(InvalidArgumentException::class);
        $service->cancelIncomeTransaction($income);
    }

    public function test_edit_cancelled_income_throws_exception(): void
    {
        $user = $this->makeUser();
        $item = $this->makeMenuItem();
        $service = app(IncomeCalculationService::class);

        $income = $service->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => 'Test',
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        $service->cancelIncomeTransaction($income);

        $this->expectException(InvalidArgumentException::class);
        $service->updateIncomeTransaction($income, [
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '2',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => 'Test',
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ]);
    }

    public function test_confirm_payment_for_cancelled_transaction_throws_exception(): void
    {
        $user = $this->makeUser();
        $account = $this->makeActiveAccount();
        $item = $this->makeMenuItem();
        $service = app(IncomeCalculationService::class);
        $confirmService = app(PaymentConfirmationService::class);

        $income = $service->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => 'Test',
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        $service->cancelIncomeTransaction($income);

        $this->expectException(InvalidArgumentException::class);
        $confirmService->confirmIncomePayment($income, $account->id);
    }

    public function test_attempt_invalid_account_assignment_throws_exception(): void
    {
        $user = $this->makeUser();
        $inactiveAccount = $this->makeActiveAccount(['is_active' => false]);
        $item = $this->makeMenuItem();
        $service = app(IncomeCalculationService::class);

        $income = $service->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => 'Test',
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        $this->expectException(InvalidArgumentException::class);
        $service->updateIncomeTransaction($income, [
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => 'Test',
            'account_id'          => $inactiveAccount->id,
            'payment_status'      => 'paid',
        ]);
    }

    public function test_attempt_paid_to_unpaid_status_transition_throws_exception(): void
    {
        $user = $this->makeUser();
        $account = $this->makeActiveAccount();
        $item = $this->makeMenuItem();
        $service = app(IncomeCalculationService::class);

        $income = $service->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => 'Test',
            'account_id'          => $account->id,
            'payment_status'      => 'paid',
        ], $user);

        $this->expectException(InvalidArgumentException::class);
        $service->updateIncomeTransaction($income, [
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => 'Test',
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ]);
    }

    // =========================================================================
    // SECTION 5: ATOMICITY
    // =========================================================================

    public function test_failed_income_edit_rolls_back_completely(): void
    {
        $user = $this->makeUser();
        $item = $this->makeMenuItem(['current_price' => 50000.00]);
        $service = app(IncomeCalculationService::class);

        $income = $service->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => 'Original description',
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        try {
            $service->updateIncomeTransaction($income, [
                'transaction_date'    => now()->format('Y-m-d'),
                'menu_item_id'        => $item->id,
                'quantity'            => '-2', // Invalid: quantity must be positive, this throws exception in validation
                'discount_percentage' => '0',
                'category'            => 'Cake Sales',
                'description'         => 'Failed edit desc',
                'account_id'          => null,
                'payment_status'      => 'unpaid',
            ]);
        } catch (InvalidArgumentException $e) {
            // expected
        }

        $income->refresh();
        // State must remain original
        $this->assertEquals('1.00', $income->quantity);
        $this->assertEquals('50000.00', $income->total_amount);
        $this->assertEquals('Original description', $income->description);
    }

    // =========================================================================
    // SECTION 6: FINANCIAL AUDIT SCENARIOS (A - H)
    // =========================================================================

    /**
     * Scenario A:
     * Opening balance = Rp100,000
     * Unpaid income = Rp50,000
     * Edit → Rp70,000
     * Expected: Balance = Rp100,000, Receivable = Rp70,000
     */
    public function test_scenario_a(): void
    {
        $user = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 100000.00]);
        $item = $this->makeMenuItem(['current_price' => 50000.00]);
        $service = app(IncomeCalculationService::class);
        $balanceService = app(AccountBalanceService::class);

        $income = $service->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => 'Scenario A',
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        $this->assertEquals(100000.00, $balanceService->calculateBalance($account));
        $this->assertEquals('50000.00', $service->calculateOutstandingReceivables());

        // Edit amount: change qty to 1.4 to make total amount Rp70,000 (1.4 * 50000 = 70000)
        $service->updateIncomeTransaction($income, [
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1.4',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => 'Scenario A',
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ]);

        $this->assertEquals(100000.00, $balanceService->calculateBalance($account));
        $this->assertEquals('70000.00', $service->calculateOutstandingReceivables());
    }

    /**
     * Scenario B:
     * Opening balance = Rp100,000
     * Paid income = Rp50,000
     * Edit → Rp70,000
     * Expected: Balance = Rp170,000
     */
    public function test_scenario_b(): void
    {
        $user = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 100000.00]);
        $item = $this->makeMenuItem(['current_price' => 50000.00]);
        $service = app(IncomeCalculationService::class);
        $balanceService = app(AccountBalanceService::class);

        $income = $service->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => 'Scenario B',
            'account_id'          => $account->id,
            'payment_status'      => 'paid',
        ], $user);

        $this->assertEquals(150000.00, $balanceService->calculateBalance($account));

        // Edit quantity -> 1.4 (amount Rp70,000)
        $service->updateIncomeTransaction($income, [
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1.4',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => 'Scenario B',
            'account_id'          => $account->id,
            'payment_status'      => 'paid',
        ]);

        // Dynamic balance should reflect exactly Rp100,000 (opening) + Rp70,000 (new amount) = Rp170,000
        $this->assertEquals(170000.00, $balanceService->calculateBalance($account));
    }

    /**
     * Scenario C:
     * Opening balance = Rp100,000
     * Paid expense = Rp20,000
     * Edit → Rp30,000
     * Expected: Balance = Rp70,000
     */
    public function test_scenario_c(): void
    {
        $user = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 100000.00]);
        $service = app(ExpenseCalculationService::class);
        $balanceService = app(AccountBalanceService::class);

        $expense = $service->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'expense_category' => 'Operational',
            'description'      => 'Scenario C',
            'amount'           => '20000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ], $user);

        $this->assertEquals(80000.00, $balanceService->calculateBalance($account));

        // Edit amount -> 30,000
        $service->updateExpenseTransaction($expense, [
            'transaction_date' => now()->format('Y-m-d'),
            'expense_category' => 'Operational',
            'description'      => 'Scenario C',
            'amount'           => '30000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ]);

        // Expected: 100000 - 30000 = 70000
        $this->assertEquals(70000.00, $balanceService->calculateBalance($account));
    }

    /**
     * Scenario D:
     * Opening balance = Rp100,000
     * Unpaid expense = Rp20,000
     * Cancel expense
     * Expected: Balance = Rp100,000, Payable = Rp0
     */
    public function test_scenario_d(): void
    {
        $user = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 100000.00]);
        $service = app(ExpenseCalculationService::class);
        $balanceService = app(AccountBalanceService::class);

        $expense = $service->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'expense_category' => 'Operational',
            'description'      => 'Scenario D',
            'amount'           => '20000',
            'account_id'       => null,
            'payment_status'   => 'unpaid',
        ], $user);

        $this->assertEquals(100000.00, $balanceService->calculateBalance($account));
        $this->assertEquals('20000.00', $service->calculateOutstandingPayables());

        $service->cancelExpenseTransaction($expense);

        $this->assertEquals(100000.00, $balanceService->calculateBalance($account));
        $this->assertEquals('0', $service->calculateOutstandingPayables());
    }

    /**
     * Scenario E:
     * Opening balance = Rp100,000
     * Paid income = Rp50,000
     * Cancel income
     * Expected: Balance = Rp100,000
     */
    public function test_scenario_e(): void
    {
        $user = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 100000.00]);
        $item = $this->makeMenuItem(['current_price' => 50000.00]);
        $service = app(IncomeCalculationService::class);
        $balanceService = app(AccountBalanceService::class);

        $income = $service->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => 'Scenario E',
            'account_id'          => $account->id,
            'payment_status'      => 'paid',
        ], $user);

        $this->assertEquals(150000.00, $balanceService->calculateBalance($account));

        $service->cancelIncomeTransaction($income);

        $this->assertEquals(100000.00, $balanceService->calculateBalance($account));
    }

    /**
     * Scenario F:
     * Opening balance = Rp100,000
     * Paid expense = Rp20,000
     * Cancel expense
     * Expected: Balance = Rp100,000
     */
    public function test_scenario_f(): void
    {
        $user = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 100000.00]);
        $service = app(ExpenseCalculationService::class);
        $balanceService = app(AccountBalanceService::class);

        $expense = $service->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'expense_category' => 'Operational',
            'description'      => 'Scenario F',
            'amount'           => '20000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ], $user);

        $this->assertEquals(80000.00, $balanceService->calculateBalance($account));

        $service->cancelExpenseTransaction($expense);

        $this->assertEquals(100000.00, $balanceService->calculateBalance($account));
    }

    /**
     * Scenario G:
     * Verify cancellation does not delete the transaction
     */
    public function test_scenario_g(): void
    {
        $user = $this->makeUser();
        $account = $this->makeActiveAccount();
        $service = app(ExpenseCalculationService::class);

        $expense = $service->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'expense_category' => 'Operational',
            'description'      => 'Scenario G',
            'amount'           => '20000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ], $user);

        $this->assertCount(1, ExpenseTransaction::all());
        $origId = $expense->id;
        $origTxId = $expense->transaction_id;

        $service->cancelExpenseTransaction($expense);

        $this->assertCount(1, ExpenseTransaction::all());
        $expense->refresh();
        $this->assertEquals('cancelled', $expense->record_status);
        $this->assertEquals($origId, $expense->id);
        $this->assertEquals($origTxId, $expense->transaction_id);
    }

    /**
     * Scenario H:
     * Attempt to edit a cancelled transaction.
     * Expected: Rejected, no DB changes, no financial changes.
     */
    public function test_scenario_h(): void
    {
        $user = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 100000.00]);
        $service = app(ExpenseCalculationService::class);
        $balanceService = app(AccountBalanceService::class);

        $expense = $service->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'expense_category' => 'Operational',
            'description'      => 'Scenario H',
            'amount'           => '20000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ], $user);

        $service->cancelExpenseTransaction($expense);
        $this->assertEquals(100000.00, $balanceService->calculateBalance($account));

        try {
            $service->updateExpenseTransaction($expense, [
                'transaction_date' => now()->format('Y-m-d'),
                'expense_category' => 'Operational',
                'description'      => 'Edited description',
                'amount'           => '30000',
                'account_id'       => $account->id,
                'payment_status'   => 'paid',
            ]);
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException $e) {
            // expected
        }

        $expense->refresh();
        $this->assertEquals('Scenario H', $expense->description);
        $this->assertEquals('20000.00', $expense->amount);
        $this->assertEquals('cancelled', $expense->record_status);
        $this->assertEquals(100000.00, $balanceService->calculateBalance($account));
    }

    // =========================================================================
    // SECTION 7: SPECIFIC TEST FT-024 (ACCOUNT TRANSITION BALANCES)
    // =========================================================================

    public function test_paid_transaction_edit_account_atomically_reverses_and_applies(): void
    {
        // FT-024: Move paid transaction from Account A to B. Reverse A and apply B atomically.
        $user = $this->makeUser();
        $accountA = $this->makeActiveAccount(['name' => 'BCA Ritel', 'opening_balance' => 100000.00]);
        $accountB = $this->makeActiveAccount(['name' => 'Cash Box', 'opening_balance' => 50000.00]);
        $item = $this->makeMenuItem(['current_price' => 20000.00]);
        $service = app(IncomeCalculationService::class);
        $balanceService = app(AccountBalanceService::class);

        // Record income on Account A
        $income = $service->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => 'FT-024',
            'account_id'          => $accountA->id,
            'payment_status'      => 'paid',
        ], $user);

        // Verification before: A = 120,000, B = 50,000
        $this->assertEquals(120000.00, $balanceService->calculateBalance($accountA));
        $this->assertEquals(50000.00, $balanceService->calculateBalance($accountB));

        // Move to Account B
        $service->updateIncomeTransaction($income, [
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => 'FT-024',
            'account_id'          => $accountB->id,
            'payment_status'      => 'paid',
        ]);

        // Verification after: A = 100,000 (reversed), B = 70,000 (applied)
        $this->assertEquals(100000.00, $balanceService->calculateBalance($accountA));
        $this->assertEquals(70000.00, $balanceService->calculateBalance($accountB));
    }

    // =========================================================================
    // SECTION 8: PAID EXPENSE ACCOUNT CHANGE (FT-024 expense variant)
    // =========================================================================

    public function test_paid_expense_edit_account_atomically_reverses_and_applies(): void
    {
        // FT-024 (expense): Move paid expense from Account A to Account B.
        // Old effect is reversed from A and new effect is applied to B atomically.
        $user     = $this->makeUser();
        $accountA = $this->makeActiveAccount(['name' => 'BCA Ritel',  'opening_balance' => 100000.00]);
        $accountB = $this->makeActiveAccount(['name' => 'Cash Box',   'opening_balance' =>  50000.00]);
        $service  = app(ExpenseCalculationService::class);
        $balance  = app(AccountBalanceService::class);

        $expense = $service->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'expense_category' => 'Operational',
            'description'      => 'FT-024 expense',
            'amount'           => '20000',
            'account_id'       => $accountA->id,
            'payment_status'   => 'paid',
        ], $user);

        // Before: A = 80,000 (100000 - 20000), B = 50,000
        $this->assertEquals(80000.00, $balance->calculateBalance($accountA));
        $this->assertEquals(50000.00, $balance->calculateBalance($accountB));

        // Move paid expense to Account B
        $service->updateExpenseTransaction($expense, [
            'transaction_date' => now()->format('Y-m-d'),
            'expense_category' => 'Operational',
            'description'      => 'FT-024 expense',
            'amount'           => '20000',
            'account_id'       => $accountB->id,
            'payment_status'   => 'paid',
        ]);

        // After: A = 100,000 (reversed), B = 30,000 (50000 - 20000 applied)
        $this->assertEquals(100000.00, $balance->calculateBalance($accountA));
        $this->assertEquals(30000.00,  $balance->calculateBalance($accountB));
    }

    // =========================================================================
    // SECTION 9: SERVER AUTHORITY (FT-019)
    // =========================================================================

    public function test_server_recalculates_income_and_ignores_client_amount(): void
    {
        // FT-019: Even if a client somehow submits an incorrect total or unit price,
        // the server recomputes from the trusted menu item and quantity inputs.
        // The test validates that the stored total_amount is what the server calculated,
        // not whatever the client might have submitted.
        $user    = $this->makeUser();
        $item    = $this->makeMenuItem(['current_price' => 50000.00]);
        $service = app(IncomeCalculationService::class);

        // Create with correct data; server stores 50000.00
        $income = $service->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => 'FT-019',
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        $this->assertEquals('50000.00', $income->total_amount);
        $this->assertEquals('50000.00', $income->unit_price); // snapshot of item price

        // Now update: client submits quantity=2 but also tries to inject a different
        // menu_item_id (pointing to the same item; no client total is submitted at all
        // because the service API only accepts trusted fields).
        // The server must compute: 2 × 50000 = 100000, not trust any client total.
        $updated = $service->updateIncomeTransaction($income, [
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '2',   // only trusted field
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => 'FT-019',
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ]);

        // Server-computed: 2 × 50000 = 100000. No client total was trusted.
        $this->assertEquals('100000.00', $updated->total_amount);
        $this->assertEquals('50000.00',  $updated->unit_price); // historical price locked (INV-010)
        $this->assertEquals('100000.00', $service->calculateOutstandingReceivables());
    }

    public function test_server_recalculates_expense_and_ignores_client_amount(): void
    {
        // FT-019 (expense): The service only accepts the raw amount field; it re-validates
        // and re-formats it server-side via integer-cents arithmetic.
        $user    = $this->makeUser();
        $service = app(ExpenseCalculationService::class);

        $expense = $service->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'expense_category' => 'Operational',
            'description'      => 'FT-019 expense',
            'amount'           => '25000',
            'account_id'       => null,
            'payment_status'   => 'unpaid',
        ], $user);

        $this->assertEquals('25000.00', $expense->amount);

        // Update with a new amount; server recomputes via toCents/formatCents.
        // The service does not accept or honour any pre-computed total from the caller.
        $updated = $service->updateExpenseTransaction($expense, [
            'transaction_date' => now()->format('Y-m-d'),
            'expense_category' => 'Operational',
            'description'      => 'FT-019 expense',
            'amount'           => '35000',
            'account_id'       => null,
            'payment_status'   => 'unpaid',
        ]);

        $this->assertEquals('35000.00', $updated->amount);
        $this->assertEquals('35000.00', $service->calculateOutstandingPayables());
    }

    // =========================================================================
    // SECTION 10: DUPLICATE / IDEMPOTENT CANCELLATION (FT-022)
    // =========================================================================

    public function test_duplicate_income_cancellation_is_rejected(): void
    {
        // FT-022: Cancelling an already-cancelled income is rejected,
        // ensuring the cancellation effect cannot be "doubled".
        $user    = $this->makeUser();
        $item    = $this->makeMenuItem(['current_price' => 50000.00]);
        $account = $this->makeActiveAccount(['opening_balance' => 100000.00]);
        $service = app(IncomeCalculationService::class);
        $balance = app(AccountBalanceService::class);

        $income = $service->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => 'FT-022 dup cancel',
            'account_id'          => $account->id,
            'payment_status'      => 'paid',
        ], $user);

        $this->assertEquals(150000.00, $balance->calculateBalance($account));

        // First cancellation: valid
        $service->cancelIncomeTransaction($income);
        $this->assertEquals(100000.00, $balance->calculateBalance($account));

        // Second cancellation: must be rejected
        $this->expectException(InvalidArgumentException::class);
        $service->cancelIncomeTransaction($income);
    }

    public function test_duplicate_expense_cancellation_is_rejected(): void
    {
        // FT-022 (expense): Cancelling an already-cancelled expense is rejected.
        $user    = $this->makeUser();
        $account = $this->makeActiveAccount(['opening_balance' => 100000.00]);
        $service = app(ExpenseCalculationService::class);
        $balance = app(AccountBalanceService::class);

        $expense = $service->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'expense_category' => 'Operational',
            'description'      => 'FT-022 dup cancel expense',
            'amount'           => '20000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ], $user);

        $this->assertEquals(80000.00, $balance->calculateBalance($account));

        // First cancellation: valid
        $service->cancelExpenseTransaction($expense);
        $this->assertEquals(100000.00, $balance->calculateBalance($account));

        // Second cancellation: must be rejected
        $this->expectException(InvalidArgumentException::class);
        $service->cancelExpenseTransaction($expense);
    }

    // =========================================================================
    // SECTION 11: ADDITIONAL EXPENSE INVALID TRANSITIONS
    // =========================================================================

    public function test_cancel_already_cancelled_expense_throws_exception(): void
    {
        $user    = $this->makeUser();
        $service = app(ExpenseCalculationService::class);

        $expense = $service->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'expense_category' => 'Operational',
            'description'      => 'Double cancel expense',
            'amount'           => '10000',
            'account_id'       => null,
            'payment_status'   => 'unpaid',
        ], $user);

        $service->cancelExpenseTransaction($expense);

        $this->expectException(InvalidArgumentException::class);
        $service->cancelExpenseTransaction($expense);
    }

    public function test_edit_cancelled_expense_throws_exception(): void
    {
        $user    = $this->makeUser();
        $service = app(ExpenseCalculationService::class);

        $expense = $service->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'expense_category' => 'Operational',
            'description'      => 'Cancel then edit expense',
            'amount'           => '10000',
            'account_id'       => null,
            'payment_status'   => 'unpaid',
        ], $user);

        $service->cancelExpenseTransaction($expense);

        $this->expectException(InvalidArgumentException::class);
        $service->updateExpenseTransaction($expense, [
            'transaction_date' => now()->format('Y-m-d'),
            'expense_category' => 'Operational',
            'description'      => 'Should not be applied',
            'amount'           => '20000',
            'account_id'       => null,
            'payment_status'   => 'unpaid',
        ]);
    }

    public function test_paid_to_unpaid_expense_transition_throws_exception(): void
    {
        $user    = $this->makeUser();
        $account = $this->makeActiveAccount();
        $service = app(ExpenseCalculationService::class);

        $expense = $service->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'expense_category' => 'Operational',
            'description'      => 'Paid expense',
            'amount'           => '10000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ], $user);

        $this->expectException(InvalidArgumentException::class);
        $service->updateExpenseTransaction($expense, [
            'transaction_date' => now()->format('Y-m-d'),
            'expense_category' => 'Operational',
            'description'      => 'Paid expense',
            'amount'           => '10000',
            'account_id'       => null,
            'payment_status'   => 'unpaid', // Forbidden transition
        ]);
    }

    public function test_paid_expense_with_inactive_account_throws_exception(): void
    {
        $user            = $this->makeUser();
        $inactiveAccount = $this->makeActiveAccount(['is_active' => false]);
        $service         = app(ExpenseCalculationService::class);

        $expense = $service->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'expense_category' => 'Operational',
            'description'      => 'Expense inactive account',
            'amount'           => '10000',
            'account_id'       => null,
            'payment_status'   => 'unpaid',
        ], $user);

        $this->expectException(InvalidArgumentException::class);
        $service->updateExpenseTransaction($expense, [
            'transaction_date' => now()->format('Y-m-d'),
            'expense_category' => 'Operational',
            'description'      => 'Expense inactive account',
            'amount'           => '10000',
            'account_id'       => $inactiveAccount->id,
            'payment_status'   => 'paid', // Inactive account - must reject (INV-020)
        ]);
    }

    // =========================================================================
    // SECTION 12: HISTORICAL PRICE PRESERVATION (FT-018 / INV-010)
    // =========================================================================

    public function test_editing_income_does_not_change_unit_price_when_menu_item_unchanged(): void
    {
        // FT-018 / INV-010: The unit_price on a historical income transaction must
        // stay locked at the price when the transaction was created, even if the
        // MenuItem's current_price changes between creation and editing.
        $user    = $this->makeUser();
        $item    = $this->makeMenuItem(['current_price' => 50000.00]);
        $service = app(IncomeCalculationService::class);

        $income = $service->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => 'FT-018 price lock',
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        $this->assertEquals('50000.00', $income->unit_price);
        $this->assertEquals('50000.00', $income->total_amount);

        // Simulate price change on MenuItem
        $item->update(['current_price' => 80000.00]);

        // Edit the transaction (same menu_item_id, only description changes)
        $updated = $service->updateIncomeTransaction($income, [
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id, // same item
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => 'Updated description',
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ]);

        // unit_price must remain at original 50000.00 (locked at creation, INV-010)
        // total_amount must remain 50000.00 because unit_price is unchanged
        // unit_price must remain at original 50000.00 (locked at creation, INV-010)
        // total_amount must remain 50000.00 because unit_price is unchanged
        $this->assertEquals('50000.00', $updated->unit_price);
        $this->assertEquals('50000.00', $updated->total_amount);
        $this->assertEquals('Updated description', $updated->description);
    }

    // =========================================================================
    // SECTION 13: AUDIT FOUNDATION TESTS (FT-030, FT-015, FT-016)
    // =========================================================================

    public function test_editing_income_creates_audit_record(): void
    {
        $user = $this->makeUser();
        $item = $this->makeMenuItem(['current_price' => 50000.00]);
        $service = app(IncomeCalculationService::class);

        $income = $service->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => 'Original description',
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        $service->updateIncomeTransaction($income, [
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '2',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => 'Updated description',
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        $this->assertDatabaseHas('audit_logs', [
            'action'         => 'income_updated',
            'auditable_type' => IncomeTransaction::class,
            'auditable_id'   => $income->id,
            'user_id'        => $user->id,
        ]);

        $log = AuditLog::where('action', 'income_updated')->where('auditable_id', $income->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals('1.00', $log->details['before']['quantity']);
        $this->assertEquals('2.00', $log->details['after']['quantity']);
    }

    public function test_cancelling_income_creates_audit_record(): void
    {
        $user = $this->makeUser();
        $item = $this->makeMenuItem(['current_price' => 50000.00]);
        $service = app(IncomeCalculationService::class);

        $income = $service->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => 'To cancel',
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        $service->cancelIncomeTransaction($income, $user);

        $this->assertDatabaseHas('audit_logs', [
            'action'         => 'income_cancelled',
            'auditable_type' => IncomeTransaction::class,
            'auditable_id'   => $income->id,
            'user_id'        => $user->id,
        ]);
    }

    public function test_editing_expense_creates_audit_record(): void
    {
        $user = $this->makeUser();
        $service = app(ExpenseCalculationService::class);

        $expense = $service->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'expense_category' => 'Operational',
            'description'      => 'Old desc',
            'amount'           => '10000',
            'account_id'       => null,
            'payment_status'   => 'unpaid',
        ], $user);

        $service->updateExpenseTransaction($expense, [
            'transaction_date' => now()->format('Y-m-d'),
            'expense_category' => 'Operational',
            'description'      => 'Old desc',
            'amount'           => '15000',
            'account_id'       => null,
            'payment_status'   => 'unpaid',
        ], $user);

        $this->assertDatabaseHas('audit_logs', [
            'action'         => 'expense_updated',
            'auditable_type' => ExpenseTransaction::class,
            'auditable_id'   => $expense->id,
            'user_id'        => $user->id,
        ]);

        $log = AuditLog::where('action', 'expense_updated')->where('auditable_id', $expense->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals('10000.00', $log->details['before']['amount']);
        $this->assertEquals('15000.00', $log->details['after']['amount']);
    }

    public function test_cancelling_expense_creates_audit_record(): void
    {
        $user = $this->makeUser();
        $service = app(ExpenseCalculationService::class);

        $expense = $service->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'expense_category' => 'Operational',
            'description'      => 'Expense to cancel',
            'amount'           => '20000',
            'account_id'       => null,
            'payment_status'   => 'unpaid',
        ], $user);

        $service->cancelExpenseTransaction($expense, $user);

        $this->assertDatabaseHas('audit_logs', [
            'action'         => 'expense_cancelled',
            'auditable_type' => ExpenseTransaction::class,
            'auditable_id'   => $expense->id,
            'user_id'        => $user->id,
        ]);
    }

    public function test_failed_financial_mutation_rolls_back_audit_record(): void
    {
        $user = $this->makeUser();
        $item = $this->makeMenuItem(['current_price' => 50000.00]);
        $service = app(IncomeCalculationService::class);

        $income = $service->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => 'Test rollback',
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        try {
            // Negative quantity will cause exception during monetary calculation inside DB transaction
            $service->updateIncomeTransaction($income, [
                'transaction_date'    => now()->format('Y-m-d'),
                'menu_item_id'        => $item->id,
                'quantity'            => '-5',
                'discount_percentage' => '0',
                'category'            => 'Cake Sales',
                'description'         => 'Failed edit',
                'account_id'          => null,
                'payment_status'      => 'unpaid',
            ], $user);
        } catch (InvalidArgumentException $e) {
            // expected exception
        }

        // Audit log must NOT be saved because transaction rolled back
        $this->assertDatabaseMissing('audit_logs', [
            'auditable_type' => IncomeTransaction::class,
            'auditable_id'   => $income->id,
        ]);
    }

    public function test_repeated_cancellation_fails_and_does_not_create_duplicate_audit_log(): void
    {
        $user = $this->makeUser();
        $item = $this->makeMenuItem(['current_price' => 50000.00]);
        $service = app(IncomeCalculationService::class);

        $income = $service->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => 'Test double cancel log',
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        $service->cancelIncomeTransaction($income, $user);

        $this->assertEquals(1, AuditLog::where('action', 'income_cancelled')->where('auditable_id', $income->id)->count());

        try {
            $service->cancelIncomeTransaction($income, $user);
        } catch (InvalidArgumentException $e) {
            // expected exception
        }

        // Audit log count must remain exactly 1
        $this->assertEquals(1, AuditLog::where('action', 'income_cancelled')->where('auditable_id', $income->id)->count());
    }
}


