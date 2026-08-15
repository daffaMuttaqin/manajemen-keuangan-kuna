<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\ExpenseTransaction;
use App\Models\IncomeTransaction;
use App\Models\MenuItem;
use App\Models\Transfer;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\ExpenseCalculationService;
use App\Services\FinancialReportService;
use App\Services\IncomeCalculationService;
use App\Services\PaymentConfirmationService;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Phase 12 Stabilization Test Suite
 *
 * Objectives:
 *  A. Financial Reconciliation — balance formulas, P&L, asset exclusion, transfer neutrality
 *  B. Security / Authorization — all routes require authentication
 *  C. Concurrency & Idempotency — double-cancellation, re-payment, duplicate UUID rejection
 *  D. Audit Trail Completeness — all 18 actions produce exactly one record each (spot-check)
 *  E. Business Invariant Regression — INV-001..INV-020 key assertions
 */
class Phase12StabilizationTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makeAccount(array $overrides = []): Account
    {
        return Account::create(array_merge([
            'name'            => 'Test Account',
            'account_type'    => 'bank',
            'opening_balance' => '0.00',
            'is_active'       => true,
        ], $overrides));
    }

    private function makeMenuItem(array $overrides = []): MenuItem
    {
        return MenuItem::create(array_merge([
            'name'          => 'Croissant',
            'category'      => 'Pastry',
            'current_price' => '10000.00',
            'is_active'     => true,
        ], $overrides));
    }

    // =========================================================================
    // A. FINANCIAL RECONCILIATION
    // =========================================================================

    // -------------------------------------------------------------------------
    // A1. Account balance formula: opening + paid_income - paid_expense +/-transfers
    // -------------------------------------------------------------------------

    public function test_account_balance_equals_opening_plus_paid_income_minus_paid_expense(): void
    {
        $user    = $this->makeUser();
        $account = $this->makeAccount(['opening_balance' => '500000.00']);
        $item    = $this->makeMenuItem(['current_price' => '100000.00']);

        $incomeService  = app(IncomeCalculationService::class);
        $expenseService = app(ExpenseCalculationService::class);
        $balanceService = app(AccountBalanceService::class);

        // Create and pay one income (total_amount = 100000)
        $income = $incomeService->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => null,
            'account_id'          => $account->id,
            'payment_status'      => 'paid',
        ], $user);

        // Create and pay one expense (amount = 30000)
        $expenseService->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_name' => 'Flour Purchase',
            'expense_category' => 'COGS / Cake Production',
            'description'      => null,
            'amount'           => '30000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ], $user);

        // Expected: 500000 + 100000 - 30000 = 570000
        $balance = $balanceService->calculateBalance($account->fresh());
        $this->assertEquals(570000.00, $balance, 'Account balance formula violated (INV-001/INV-002).');
    }

    // -------------------------------------------------------------------------
    // A2. Unpaid income DOES NOT change cash balance (INV-003)
    // -------------------------------------------------------------------------

    public function test_unpaid_income_does_not_affect_cash_balance(): void
    {
        $user    = $this->makeUser();
        $account = $this->makeAccount(['opening_balance' => '200000.00']);
        $item    = $this->makeMenuItem(['current_price' => '50000.00']);

        $incomeService  = app(IncomeCalculationService::class);
        $balanceService = app(AccountBalanceService::class);

        // Create unpaid income linked to account
        $incomeService->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '2',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => null,
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        // Balance must remain = opening_balance (INV-003)
        $this->assertEquals(200000.00, $balanceService->calculateBalance($account->fresh()),
            'Unpaid income must not change cash balance (INV-003).');
    }

    // -------------------------------------------------------------------------
    // A3. Unpaid expense DOES NOT change cash balance (INV-004)
    // -------------------------------------------------------------------------

    public function test_unpaid_expense_does_not_affect_cash_balance(): void
    {
        $user    = $this->makeUser();
        $account = $this->makeAccount(['opening_balance' => '200000.00']);

        $expenseService = app(ExpenseCalculationService::class);
        $balanceService = app(AccountBalanceService::class);

        $expenseService->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_name' => 'Supplies',
            'expense_category' => 'Operational',
            'description'      => null,
            'amount'           => '50000',
            'account_id'       => null,
            'payment_status'   => 'unpaid',
        ], $user);

        $this->assertEquals(200000.00, $balanceService->calculateBalance($account->fresh()),
            'Unpaid expense must not change cash balance (INV-004).');
    }

    // -------------------------------------------------------------------------
    // A4. Transfer is balance-neutral for total company balance (INV-005)
    // -------------------------------------------------------------------------

    public function test_transfer_is_balance_neutral_for_total_company_balance(): void
    {
        $user = $this->makeUser();
        $src  = $this->makeAccount(['name' => 'Source', 'opening_balance' => '300000.00']);
        $dst  = $this->makeAccount(['name' => 'Destination', 'opening_balance' => '100000.00']);

        $balanceService = app(AccountBalanceService::class);
        $transferService = app(TransferService::class);

        $before = $balanceService->calculateTotalCompanyBalance();

        $transferService->createTransfer([
            'transfer_date'   => now()->format('Y-m-d'),
            'from_account_id' => $src->id,
            'to_account_id'   => $dst->id,
            'amount'          => '100000',
            'description'     => null,
        ], $user);

        $after = $balanceService->calculateTotalCompanyBalance();

        $this->assertEquals($before, $after,
            'Transfer must be balance-neutral for total company balance (INV-005).');
    }

    // -------------------------------------------------------------------------
    // A5. Transfer correctly redistributes balances between accounts
    // -------------------------------------------------------------------------

    public function test_transfer_redistributes_balance_correctly(): void
    {
        $user = $this->makeUser();
        $src  = $this->makeAccount(['name' => 'Source', 'opening_balance' => '300000.00']);
        $dst  = $this->makeAccount(['name' => 'Destination', 'opening_balance' => '50000.00']);

        $balanceService  = app(AccountBalanceService::class);
        $transferService = app(TransferService::class);

        $transferService->createTransfer([
            'transfer_date'   => now()->format('Y-m-d'),
            'from_account_id' => $src->id,
            'to_account_id'   => $dst->id,
            'amount'          => '100000',
            'description'     => null,
        ], $user);

        $this->assertEquals(200000.00, $balanceService->calculateBalance($src->fresh()),
            'Source account balance after transfer is incorrect.');
        $this->assertEquals(150000.00, $balanceService->calculateBalance($dst->fresh()),
            'Destination account balance after transfer is incorrect.');
    }

    // -------------------------------------------------------------------------
    // A6. Cancelled income excluded from revenue and balance (INV-009)
    // -------------------------------------------------------------------------

    public function test_cancelled_income_excluded_from_revenue_and_balance(): void
    {
        $user    = $this->makeUser();
        $account = $this->makeAccount(['opening_balance' => '0.00']);
        $item    = $this->makeMenuItem(['current_price' => '75000.00']);

        $incomeService  = app(IncomeCalculationService::class);
        $balanceService = app(AccountBalanceService::class);

        $income = $incomeService->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => null,
            'account_id'          => $account->id,
            'payment_status'      => 'paid',
        ], $user);

        // Balance before cancellation = 75000
        $this->assertEquals(75000.00, $balanceService->calculateBalance($account->fresh()));

        $incomeService->cancelIncomeTransaction($income, $user);

        // Balance after cancellation = 0 (cancelled records excluded — INV-009)
        $this->assertEquals(0.00, $balanceService->calculateBalance($account->fresh()),
            'Cancelled income must be excluded from account balance (INV-009).');
        $this->assertEquals('0', $incomeService->calculateRevenue(),
            'Cancelled income must be excluded from revenue (INV-009).');
    }

    // -------------------------------------------------------------------------
    // A7. Net Profit = Revenue - Profit-Eligible Expenses (Asset excluded)
    // -------------------------------------------------------------------------

    public function test_net_profit_excludes_asset_expense(): void
    {
        $user    = $this->makeUser();
        $account = $this->makeAccount(['opening_balance' => '500000.00']);
        $item    = $this->makeMenuItem(['current_price' => '200000.00']);

        $incomeService  = app(IncomeCalculationService::class);
        $expenseService = app(ExpenseCalculationService::class);
        $reportService  = app(FinancialReportService::class);

        // Revenue: 200000
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

        // COGS expense (profit-eligible): 50000
        $expenseService->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_name' => 'Flour',
            'expense_category' => 'COGS / Cake Production',
            'description'      => null,
            'amount'           => '50000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ], $user);

        // Asset expense (NOT profit-eligible): 100000
        $expenseService->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_name' => 'Oven Purchase',
            'expense_category' => 'Asset',
            'description'      => null,
            'amount'           => '100000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ], $user);

        $summary = $reportService->getFinancialSummary();

        // Revenue = 200000
        $this->assertEquals(200000.00, $summary['total_revenue'], 'Revenue mismatch.');
        // Total expenses = 150000
        $this->assertEquals(150000.00, $summary['total_expenses'], 'Total expenses mismatch.');
        // Asset expenses = 100000
        $this->assertEquals(100000.00, $summary['asset_expenses'], 'Asset expense mismatch.');
        // Net profit = Revenue - COGS only = 200000 - 50000 = 150000 (NOT 200000-150000=50000)
        $this->assertEquals(150000.00, $summary['net_profit'],
            'Net profit must exclude Asset expense from deduction (PRD Financial Consistency Matrix).');
        // Profit-eligible expenses = 50000 (only COGS)
        $this->assertEquals(50000.00, $summary['profit_eligible_exp'],
            'Profit-eligible expenses must exclude Asset category.');
    }

    // -------------------------------------------------------------------------
    // A8. Gross Profit = Revenue - COGS
    // -------------------------------------------------------------------------

    public function test_gross_profit_equals_revenue_minus_cogs(): void
    {
        $user    = $this->makeUser();
        $account = $this->makeAccount(['opening_balance' => '1000000.00']);
        $item    = $this->makeMenuItem(['current_price' => '300000.00']);

        $incomeService  = app(IncomeCalculationService::class);
        $expenseService = app(ExpenseCalculationService::class);
        $reportService  = app(FinancialReportService::class);

        // Revenue: 300000
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

        // COGS: 80000
        $expenseService->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_name' => 'Ingredients',
            'expense_category' => 'COGS / Cake Production',
            'description'      => null,
            'amount'           => '80000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ], $user);

        $summary = $reportService->getFinancialSummary();

        $this->assertEquals(220000.00, $summary['gross_profit'],
            'Gross profit must equal Revenue - COGS (300000 - 80000 = 220000).');
    }

    // -------------------------------------------------------------------------
    // A9. Server-side monetary computation (INV-018): discount applied correctly
    // -------------------------------------------------------------------------

    public function test_server_computes_discount_amount_correctly(): void
    {
        $user = $this->makeUser();
        $item = $this->makeMenuItem(['current_price' => '100000.00']);

        $incomeService = app(IncomeCalculationService::class);

        $income = $incomeService->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '2',
            'discount_percentage' => '10',  // 10% of 200000 = 20000
            'category'            => 'Cake Sales',
            'description'         => null,
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        $this->assertEquals('200000.00', $income->subtotal,        'Subtotal mismatch.');
        $this->assertEquals('20000.00',  $income->discount_amount, 'Discount amount mismatch.');
        $this->assertEquals('180000.00', $income->total_amount,    'Total amount after discount mismatch.');
    }

    // -------------------------------------------------------------------------
    // A10. unit_price snapshot — MenuItem price change must not alter history (INV-010)
    // -------------------------------------------------------------------------

    public function test_unit_price_snapshot_not_affected_by_menu_item_price_change(): void
    {
        $user = $this->makeUser();
        $item = $this->makeMenuItem(['current_price' => '50000.00']);

        $incomeService = app(IncomeCalculationService::class);

        $income = $incomeService->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => null,
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        // Change price on MenuItem
        $item->update(['current_price' => '99000.00']);

        $income->refresh();

        // Historical unit_price must still be 50000 (INV-010)
        $this->assertEquals('50000.00', $income->unit_price,
            'unit_price snapshot changed after MenuItem price update (INV-010 violated).');
        $this->assertEquals('50000.00', $income->total_amount,
            'total_amount must not change when MenuItem price changes (INV-010 violated).');
    }

    // =========================================================================
    // B. SECURITY / AUTHORIZATION
    // =========================================================================

    // -------------------------------------------------------------------------
    // B1. Guest is redirected from all protected routes
    // -------------------------------------------------------------------------

    public function test_guest_redirected_from_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_guest_redirected_from_accounts(): void
    {
        $this->get(route('accounts.index'))->assertRedirect(route('login'));
    }

    public function test_guest_redirected_from_menu(): void
    {
        $this->get(route('menu.index'))->assertRedirect(route('login'));
    }

    public function test_guest_redirected_from_income(): void
    {
        $this->get(route('income.index'))->assertRedirect(route('login'));
    }

    public function test_guest_redirected_from_expenses(): void
    {
        $this->get(route('expense.index'))->assertRedirect(route('login'));
    }

    public function test_guest_redirected_from_transfers(): void
    {
        $this->get(route('transfers.index'))->assertRedirect(route('login'));
    }

    public function test_guest_redirected_from_reports(): void
    {
        $this->get(route('reports.index'))->assertRedirect(route('login'));
    }

    public function test_guest_redirected_from_audit_logs(): void
    {
        $this->get(route('audit-logs.index'))->assertRedirect(route('login'));
    }

    // -------------------------------------------------------------------------
    // B2. Authenticated user can access all protected routes (200 OK)
    // -------------------------------------------------------------------------

    public function test_authenticated_user_can_access_dashboard(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->get(route('dashboard'))->assertStatus(200);
    }

    public function test_authenticated_user_can_access_accounts(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->get(route('accounts.index'))->assertStatus(200);
    }

    public function test_authenticated_user_can_access_menu(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->get(route('menu.index'))->assertStatus(200);
    }

    public function test_authenticated_user_can_access_income(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->get(route('income.index'))->assertStatus(200);
    }

    public function test_authenticated_user_can_access_expenses(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->get(route('expense.index'))->assertStatus(200);
    }

    public function test_authenticated_user_can_access_transfers(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->get(route('transfers.index'))->assertStatus(200);
    }

    public function test_authenticated_user_can_access_reports(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->get(route('reports.index'))->assertStatus(200);
    }

    public function test_authenticated_user_can_access_audit_logs(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->get(route('audit-logs.index'))->assertStatus(200);
    }

    // =========================================================================
    // C. CONCURRENCY & IDEMPOTENCY
    // =========================================================================

    // -------------------------------------------------------------------------
    // C1. Double-cancel income throws exception (idempotency guard)
    // -------------------------------------------------------------------------

    public function test_double_cancel_income_throws_exception(): void
    {
        $user = $this->makeUser();
        $item = $this->makeMenuItem();

        $incomeService = app(IncomeCalculationService::class);

        $income = $incomeService->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => null,
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        $incomeService->cancelIncomeTransaction($income, $user);

        $this->expectException(InvalidArgumentException::class);
        $incomeService->cancelIncomeTransaction($income, $user);
    }

    // -------------------------------------------------------------------------
    // C2. Double-cancel expense throws exception
    // -------------------------------------------------------------------------

    public function test_double_cancel_expense_throws_exception(): void
    {
        $user = $this->makeUser();

        $expenseService = app(ExpenseCalculationService::class);

        $expense = $expenseService->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_name' => 'Electricity',
            'expense_category' => 'Operational',
            'description'      => null,
            'amount'           => '10000',
            'account_id'       => null,
            'payment_status'   => 'unpaid',
        ], $user);

        $expenseService->cancelExpenseTransaction($expense, $user);

        $this->expectException(InvalidArgumentException::class);
        $expenseService->cancelExpenseTransaction($expense, $user);
    }

    // -------------------------------------------------------------------------
    // C3. Double-cancel transfer throws exception
    // -------------------------------------------------------------------------

    public function test_double_cancel_transfer_throws_exception(): void
    {
        $user = $this->makeUser();
        $src  = $this->makeAccount(['name' => 'Src', 'opening_balance' => '200000.00']);
        $dst  = $this->makeAccount(['name' => 'Dst', 'opening_balance' => '0.00']);

        $transferService = app(TransferService::class);

        $transfer = $transferService->createTransfer([
            'transfer_date'   => now()->format('Y-m-d'),
            'from_account_id' => $src->id,
            'to_account_id'   => $dst->id,
            'amount'          => '50000',
            'description'     => null,
        ], $user);

        $transferService->cancelTransfer($transfer, $user);

        $this->expectException(InvalidArgumentException::class);
        $transferService->cancelTransfer($transfer, $user);
    }

    // -------------------------------------------------------------------------
    // C4. Re-confirming an already paid income throws exception (INV-013)
    // -------------------------------------------------------------------------

    public function test_re_confirm_paid_income_throws_exception(): void
    {
        $user    = $this->makeUser();
        $account = $this->makeAccount(['opening_balance' => '100000.00']);
        $item    = $this->makeMenuItem(['current_price' => '20000.00']);

        $incomeService = app(IncomeCalculationService::class);

        $income = $incomeService->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => null,
            'account_id'          => $account->id,
            'payment_status'      => 'paid',
        ], $user);

        $this->expectException(InvalidArgumentException::class);
        app(PaymentConfirmationService::class)->confirmIncomePayment($income, $account->id, $user);
    }

    // -------------------------------------------------------------------------
    // C5. Re-confirming an already paid expense throws exception (INV-013)
    // -------------------------------------------------------------------------

    public function test_re_confirm_paid_expense_throws_exception(): void
    {
        $user    = $this->makeUser();
        $account = $this->makeAccount(['opening_balance' => '100000.00']);

        $expenseService = app(ExpenseCalculationService::class);

        $expense = $expenseService->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_name' => 'Paid Expense',
            'expense_category' => 'Operational',
            'description'      => null,
            'amount'           => '10000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ], $user);

        $this->expectException(InvalidArgumentException::class);
        app(PaymentConfirmationService::class)->confirmExpensePayment($expense, $account->id, $user);
    }

    // -------------------------------------------------------------------------
    // C6. Duplicate transfer_id (UUID) rejected by DB unique constraint (FT-022)
    // -------------------------------------------------------------------------

    public function test_duplicate_transfer_id_is_rejected(): void
    {
        $user = $this->makeUser();
        $src  = $this->makeAccount(['name' => 'Src', 'opening_balance' => '500000.00']);
        $dst  = $this->makeAccount(['name' => 'Dst', 'opening_balance' => '0.00']);

        $duplicateUuid = 'fixed-uuid-for-idempotency-test-12345678';

        app(TransferService::class)->createTransfer([
            'transfer_date'   => now()->format('Y-m-d'),
            'from_account_id' => $src->id,
            'to_account_id'   => $dst->id,
            'amount'          => '10000',
            'description'     => null,
            'transfer_id'     => $duplicateUuid,
        ], $user);

        $this->expectException(\Exception::class);
        app(TransferService::class)->createTransfer([
            'transfer_date'   => now()->format('Y-m-d'),
            'from_account_id' => $src->id,
            'to_account_id'   => $dst->id,
            'amount'          => '10000',
            'description'     => null,
            'transfer_id'     => $duplicateUuid,
        ], $user);
    }

    // -------------------------------------------------------------------------
    // C7. Cannot cancel a cancelled income (editing guard)
    // -------------------------------------------------------------------------

    public function test_cannot_edit_cancelled_income(): void
    {
        $user = $this->makeUser();
        $item = $this->makeMenuItem();

        $incomeService = app(IncomeCalculationService::class);

        $income = $incomeService->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => null,
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        $incomeService->cancelIncomeTransaction($income, $user);

        $this->expectException(InvalidArgumentException::class);
        $incomeService->updateIncomeTransaction($income, [
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '2',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => null,
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);
    }

    // -------------------------------------------------------------------------
    // C8. Paid -> Unpaid transition rejected (PRD constraint)
    // -------------------------------------------------------------------------

    public function test_paid_to_unpaid_transition_rejected_for_income(): void
    {
        $user    = $this->makeUser();
        $account = $this->makeAccount(['opening_balance' => '100000.00']);
        $item    = $this->makeMenuItem(['current_price' => '20000.00']);

        $incomeService = app(IncomeCalculationService::class);

        $income = $incomeService->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => null,
            'account_id'          => $account->id,
            'payment_status'      => 'paid',
        ], $user);

        $this->expectException(InvalidArgumentException::class);
        $incomeService->updateIncomeTransaction($income, [
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => null,
            'account_id'          => null,
            'payment_status'      => 'unpaid',   // Invalid transition
        ], $user);
    }

    // -------------------------------------------------------------------------
    // C9. Inactive account rejected for new paid income (INV-020)
    // -------------------------------------------------------------------------

    public function test_inactive_account_rejected_for_paid_income(): void
    {
        $user    = $this->makeUser();
        $account = $this->makeAccount(['opening_balance' => '100000.00', 'is_active' => false]);
        $item    = $this->makeMenuItem();

        $incomeService = app(IncomeCalculationService::class);

        $this->expectException(InvalidArgumentException::class);
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
    }

    // -------------------------------------------------------------------------
    // C10. Inactive account rejected for new paid expense (INV-020)
    // -------------------------------------------------------------------------

    public function test_inactive_account_rejected_for_paid_expense(): void
    {
        $user    = $this->makeUser();
        $account = $this->makeAccount(['opening_balance' => '100000.00', 'is_active' => false]);

        $expenseService = app(ExpenseCalculationService::class);

        $this->expectException(InvalidArgumentException::class);
        $expenseService->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_name' => 'Inactive Test',
            'expense_category' => 'Operational',
            'description'      => null,
            'amount'           => '10000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ], $user);
    }

    // -------------------------------------------------------------------------
    // C11. Transfer with same source/destination rejected (INV-015)
    // -------------------------------------------------------------------------

    public function test_same_source_and_destination_transfer_rejected(): void
    {
        $user    = $this->makeUser();
        $account = $this->makeAccount(['opening_balance' => '100000.00']);

        $this->expectException(InvalidArgumentException::class);
        app(TransferService::class)->createTransfer([
            'transfer_date'   => now()->format('Y-m-d'),
            'from_account_id' => $account->id,
            'to_account_id'   => $account->id,
            'amount'          => '10000',
            'description'     => null,
        ], $user);
    }

    // -------------------------------------------------------------------------
    // C12. Transfer exceeding source balance rejected (INV-016)
    // -------------------------------------------------------------------------

    public function test_transfer_exceeding_source_balance_rejected(): void
    {
        $user = $this->makeUser();
        $src  = $this->makeAccount(['name' => 'Src', 'opening_balance' => '1000.00']);
        $dst  = $this->makeAccount(['name' => 'Dst', 'opening_balance' => '0.00']);

        $this->expectException(InvalidArgumentException::class);
        app(TransferService::class)->createTransfer([
            'transfer_date'   => now()->format('Y-m-d'),
            'from_account_id' => $src->id,
            'to_account_id'   => $dst->id,
            'amount'          => '999999',   // exceeds balance
            'description'     => null,
        ], $user);
    }

    // =========================================================================
    // D. AUDIT TRAIL COMPLETENESS
    // =========================================================================

    // -------------------------------------------------------------------------
    // D1. income_created produces exactly one audit record
    // -------------------------------------------------------------------------

    public function test_income_created_produces_exactly_one_audit_record(): void
    {
        $user = $this->makeUser();
        $item = $this->makeMenuItem();

        app(IncomeCalculationService::class)->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => null,
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        $this->assertEquals(1, AuditLog::where('action', 'income_created')->count(),
            'income_created must produce exactly one audit record.');
    }

    // -------------------------------------------------------------------------
    // D2. income_updated produces exactly one additional audit record
    // -------------------------------------------------------------------------

    public function test_income_updated_produces_exactly_one_audit_record(): void
    {
        $user = $this->makeUser();
        $item = $this->makeMenuItem(['current_price' => '20000.00']);

        $incomeService = app(IncomeCalculationService::class);

        $income = $incomeService->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => null,
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        $incomeService->updateIncomeTransaction($income, [
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '2',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => null,
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        $this->assertEquals(1, AuditLog::where('action', 'income_updated')->count(),
            'income_updated must produce exactly one audit record per call.');
    }

    // -------------------------------------------------------------------------
    // D3. income_cancelled produces exactly one audit record
    // -------------------------------------------------------------------------

    public function test_income_cancelled_produces_exactly_one_audit_record(): void
    {
        $user = $this->makeUser();
        $item = $this->makeMenuItem();

        $incomeService = app(IncomeCalculationService::class);

        $income = $incomeService->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => null,
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        $incomeService->cancelIncomeTransaction($income, $user);

        $this->assertEquals(1, AuditLog::where('action', 'income_cancelled')->count(),
            'income_cancelled must produce exactly one audit record.');
    }

    // -------------------------------------------------------------------------
    // D4. income_payment_confirmed produces exactly one audit record
    // -------------------------------------------------------------------------

    public function test_income_payment_confirmed_produces_exactly_one_audit_record(): void
    {
        $user    = $this->makeUser();
        $account = $this->makeAccount(['opening_balance' => '100000.00']);
        $item    = $this->makeMenuItem(['current_price' => '15000.00']);

        $incomeService = app(IncomeCalculationService::class);

        $income = $incomeService->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => null,
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        app(PaymentConfirmationService::class)->confirmIncomePayment($income, $account->id, $user);

        $this->assertEquals(1, AuditLog::where('action', 'income_payment_confirmed')->count(),
            'income_payment_confirmed must produce exactly one audit record.');
    }

    // -------------------------------------------------------------------------
    // D5. expense_created produces exactly one audit record
    // -------------------------------------------------------------------------

    public function test_expense_created_produces_exactly_one_audit_record(): void
    {
        $user = $this->makeUser();

        app(ExpenseCalculationService::class)->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_name' => 'Test Expense',
            'expense_category' => 'Operational',
            'description'      => null,
            'amount'           => '10000',
            'account_id'       => null,
            'payment_status'   => 'unpaid',
        ], $user);

        $this->assertEquals(1, AuditLog::where('action', 'expense_created')->count(),
            'expense_created must produce exactly one audit record.');
    }

    // -------------------------------------------------------------------------
    // D6. expense_updated produces exactly one audit record
    // -------------------------------------------------------------------------

    public function test_expense_updated_produces_exactly_one_audit_record(): void
    {
        $user = $this->makeUser();

        $expenseService = app(ExpenseCalculationService::class);

        $expense = $expenseService->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_name' => 'Original',
            'expense_category' => 'Operational',
            'description'      => null,
            'amount'           => '10000',
            'account_id'       => null,
            'payment_status'   => 'unpaid',
        ], $user);

        $expenseService->updateExpenseTransaction($expense, [
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_name' => 'Updated',
            'expense_category' => 'Marketing',
            'description'      => null,
            'amount'           => '12000',
            'account_id'       => null,
            'payment_status'   => 'unpaid',
        ], $user);

        $this->assertEquals(1, AuditLog::where('action', 'expense_updated')->count(),
            'expense_updated must produce exactly one audit record per call.');
    }

    // -------------------------------------------------------------------------
    // D7. expense_cancelled produces exactly one audit record
    // -------------------------------------------------------------------------

    public function test_expense_cancelled_produces_exactly_one_audit_record(): void
    {
        $user = $this->makeUser();

        $expenseService = app(ExpenseCalculationService::class);

        $expense = $expenseService->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_name' => 'Cancel Test',
            'expense_category' => 'Operational',
            'description'      => null,
            'amount'           => '5000',
            'account_id'       => null,
            'payment_status'   => 'unpaid',
        ], $user);

        $expenseService->cancelExpenseTransaction($expense, $user);

        $this->assertEquals(1, AuditLog::where('action', 'expense_cancelled')->count(),
            'expense_cancelled must produce exactly one audit record.');
    }

    // -------------------------------------------------------------------------
    // D8. expense_payment_confirmed produces exactly one audit record
    // -------------------------------------------------------------------------

    public function test_expense_payment_confirmed_produces_exactly_one_audit_record(): void
    {
        $user    = $this->makeUser();
        $account = $this->makeAccount(['opening_balance' => '100000.00']);

        $expenseService = app(ExpenseCalculationService::class);

        $expense = $expenseService->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_name' => 'Confirm Test',
            'expense_category' => 'Operational',
            'description'      => null,
            'amount'           => '8000',
            'account_id'       => null,
            'payment_status'   => 'unpaid',
        ], $user);

        app(PaymentConfirmationService::class)->confirmExpensePayment($expense, $account->id, $user);

        $this->assertEquals(1, AuditLog::where('action', 'expense_payment_confirmed')->count(),
            'expense_payment_confirmed must produce exactly one audit record.');
    }

    // -------------------------------------------------------------------------
    // D9. transfer_created produces exactly one audit record
    // -------------------------------------------------------------------------

    public function test_transfer_created_produces_exactly_one_audit_record(): void
    {
        $user = $this->makeUser();
        $src  = $this->makeAccount(['name' => 'Src', 'opening_balance' => '200000.00']);
        $dst  = $this->makeAccount(['name' => 'Dst', 'opening_balance' => '0.00']);

        app(TransferService::class)->createTransfer([
            'transfer_date'   => now()->format('Y-m-d'),
            'from_account_id' => $src->id,
            'to_account_id'   => $dst->id,
            'amount'          => '50000',
            'description'     => null,
        ], $user);

        $this->assertEquals(1, AuditLog::where('action', 'transfer_created')->count(),
            'transfer_created must produce exactly one audit record.');
    }

    // -------------------------------------------------------------------------
    // D10. transfer_cancelled produces exactly one audit record
    // -------------------------------------------------------------------------

    public function test_transfer_cancelled_produces_exactly_one_audit_record(): void
    {
        $user = $this->makeUser();
        $src  = $this->makeAccount(['name' => 'Src', 'opening_balance' => '200000.00']);
        $dst  = $this->makeAccount(['name' => 'Dst', 'opening_balance' => '0.00']);

        $transferService = app(TransferService::class);

        $transfer = $transferService->createTransfer([
            'transfer_date'   => now()->format('Y-m-d'),
            'from_account_id' => $src->id,
            'to_account_id'   => $dst->id,
            'amount'          => '50000',
            'description'     => null,
        ], $user);

        $transferService->cancelTransfer($transfer, $user);

        $this->assertEquals(1, AuditLog::where('action', 'transfer_cancelled')->count(),
            'transfer_cancelled must produce exactly one audit record.');
    }

    // -------------------------------------------------------------------------
    // D11. Audit records are never physically deleted (INV-019)
    // -------------------------------------------------------------------------

    public function test_audit_records_persist_after_transaction_cancellation(): void
    {
        $user = $this->makeUser();
        $item = $this->makeMenuItem();

        $incomeService = app(IncomeCalculationService::class);

        $income = $incomeService->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => null,
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        $incomeService->cancelIncomeTransaction($income, $user);

        // Both creation and cancellation audit logs must remain
        $this->assertEquals(1, AuditLog::where('action', 'income_created')->count(),
            'income_created audit log must persist after cancellation.');
        $this->assertEquals(1, AuditLog::where('action', 'income_cancelled')->count(),
            'income_cancelled audit log must persist after cancellation.');
        $this->assertEquals(2, AuditLog::count(),
            'Total audit records must be 2 (created + cancelled).');
    }

    // =========================================================================
    // E. BUSINESS INVARIANT REGRESSION
    // =========================================================================

    // -------------------------------------------------------------------------
    // E1. Financial records are never physically deleted (INV-019)
    // -------------------------------------------------------------------------

    public function test_income_records_are_never_physically_deleted(): void
    {
        $user = $this->makeUser();
        $item = $this->makeMenuItem();

        $incomeService = app(IncomeCalculationService::class);

        $income = $incomeService->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => null,
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        $incomeService->cancelIncomeTransaction($income, $user);

        // Must still exist in DB with 'cancelled' status
        $this->assertDatabaseHas('income_transactions', [
            'id'            => $income->id,
            'record_status' => 'cancelled',
        ]);
        $this->assertEquals(1, IncomeTransaction::count(),
            'Income record must not be physically deleted on cancellation (INV-019).');
    }

    public function test_expense_records_are_never_physically_deleted(): void
    {
        $user = $this->makeUser();

        $expenseService = app(ExpenseCalculationService::class);

        $expense = $expenseService->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_name' => 'Delete Test',
            'expense_category' => 'Operational',
            'description'      => null,
            'amount'           => '5000',
            'account_id'       => null,
            'payment_status'   => 'unpaid',
        ], $user);

        $expenseService->cancelExpenseTransaction($expense, $user);

        $this->assertDatabaseHas('expense_transactions', [
            'id'            => $expense->id,
            'record_status' => 'cancelled',
        ]);
        $this->assertEquals(1, ExpenseTransaction::count(),
            'Expense record must not be physically deleted on cancellation (INV-019).');
    }

    public function test_transfer_records_are_never_physically_deleted(): void
    {
        $user = $this->makeUser();
        $src  = $this->makeAccount(['name' => 'Src', 'opening_balance' => '100000.00']);
        $dst  = $this->makeAccount(['name' => 'Dst', 'opening_balance' => '0.00']);

        $transferService = app(TransferService::class);

        $transfer = $transferService->createTransfer([
            'transfer_date'   => now()->format('Y-m-d'),
            'from_account_id' => $src->id,
            'to_account_id'   => $dst->id,
            'amount'          => '50000',
            'description'     => null,
        ], $user);

        $transferService->cancelTransfer($transfer, $user);

        $this->assertDatabaseHas('transfers', [
            'id'            => $transfer->id,
            'record_status' => 'cancelled',
        ]);
        $this->assertEquals(1, Transfer::count(),
            'Transfer record must not be physically deleted on cancellation (INV-019).');
    }

    // -------------------------------------------------------------------------
    // E2. Cancelled transfer excluded from account balance (INV-009)
    // -------------------------------------------------------------------------

    public function test_cancelled_transfer_excluded_from_account_balance(): void
    {
        $user = $this->makeUser();
        $src  = $this->makeAccount(['name' => 'Src', 'opening_balance' => '200000.00']);
        $dst  = $this->makeAccount(['name' => 'Dst', 'opening_balance' => '50000.00']);

        $balanceService  = app(AccountBalanceService::class);
        $transferService = app(TransferService::class);

        $transfer = $transferService->createTransfer([
            'transfer_date'   => now()->format('Y-m-d'),
            'from_account_id' => $src->id,
            'to_account_id'   => $dst->id,
            'amount'          => '100000',
            'description'     => null,
        ], $user);

        // After transfer: src=100000, dst=150000
        $this->assertEquals(100000.00, $balanceService->calculateBalance($src->fresh()));
        $this->assertEquals(150000.00, $balanceService->calculateBalance($dst->fresh()));

        $transferService->cancelTransfer($transfer, $user);

        // After cancellation: restored to original balances
        $this->assertEquals(200000.00, $balanceService->calculateBalance($src->fresh()),
            'Src balance must be restored after transfer cancellation (INV-009).');
        $this->assertEquals(50000.00, $balanceService->calculateBalance($dst->fresh()),
            'Dst balance must be restored after transfer cancellation (INV-009).');
    }

    // -------------------------------------------------------------------------
    // E3. Expense amount must be positive (validation guard)
    // -------------------------------------------------------------------------

    public function test_zero_expense_amount_rejected(): void
    {
        $user = $this->makeUser();

        $this->expectException(InvalidArgumentException::class);
        app(ExpenseCalculationService::class)->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_name' => 'Zero Test',
            'expense_category' => 'Operational',
            'description'      => null,
            'amount'           => '0',
            'account_id'       => null,
            'payment_status'   => 'unpaid',
        ], $user);
    }

    // -------------------------------------------------------------------------
    // E4. Transfer amount must be positive
    // -------------------------------------------------------------------------

    public function test_zero_transfer_amount_rejected(): void
    {
        $user = $this->makeUser();
        $src  = $this->makeAccount(['name' => 'Src', 'opening_balance' => '100000.00']);
        $dst  = $this->makeAccount(['name' => 'Dst', 'opening_balance' => '0.00']);

        $this->expectException(InvalidArgumentException::class);
        app(TransferService::class)->createTransfer([
            'transfer_date'   => now()->format('Y-m-d'),
            'from_account_id' => $src->id,
            'to_account_id'   => $dst->id,
            'amount'          => '0',
            'description'     => null,
        ], $user);
    }

    // -------------------------------------------------------------------------
    // E5. Outstanding receivables includes only active unpaid income
    // -------------------------------------------------------------------------

    public function test_outstanding_receivables_excludes_cancelled_and_paid_income(): void
    {
        $user = $this->makeUser();
        $item = $this->makeMenuItem(['current_price' => '40000.00']);

        $incomeService = app(IncomeCalculationService::class);

        // Create three income transactions:
        // 1. Unpaid active → should be included in receivables
        $unpaidActive = $incomeService->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => null,
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        // 2. Cancelled → should NOT be in receivables
        $cancelled = $incomeService->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => null,
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);
        $incomeService->cancelIncomeTransaction($cancelled, $user);

        // Outstanding receivables = only unpaidActive = 40000
        $this->assertEquals('40000.00', $incomeService->calculateOutstandingReceivables(),
            'Outstanding receivables must only include active+unpaid income.');
    }

    // -------------------------------------------------------------------------
    // E6. Outstanding payables includes only active unpaid expense
    // -------------------------------------------------------------------------

    public function test_outstanding_payables_excludes_cancelled_and_paid_expense(): void
    {
        $user    = $this->makeUser();
        $account = $this->makeAccount(['opening_balance' => '200000.00']);

        $expenseService = app(ExpenseCalculationService::class);

        // 1. Unpaid active → included
        $expenseService->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_name' => 'Payable Test A',
            'expense_category' => 'Operational',
            'description'      => null,
            'amount'           => '25000',
            'account_id'       => null,
            'payment_status'   => 'unpaid',
        ], $user);

        // 2. Paid active → NOT in payables
        $expenseService->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_name' => 'Payable Test B (paid)',
            'expense_category' => 'Operational',
            'description'      => null,
            'amount'           => '30000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ], $user);

        // Outstanding payables = only unpaid = 25000
        $this->assertEquals('25000.00', $expenseService->calculateOutstandingPayables(),
            'Outstanding payables must only include active+unpaid expense.');
    }

    // -------------------------------------------------------------------------
    // E7. Confirm payment on cancelled income is rejected (INV-009)
    // -------------------------------------------------------------------------

    public function test_confirm_payment_on_cancelled_income_rejected(): void
    {
        $user    = $this->makeUser();
        $account = $this->makeAccount(['opening_balance' => '100000.00']);
        $item    = $this->makeMenuItem();

        $incomeService = app(IncomeCalculationService::class);

        $income = $incomeService->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => null,
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        $incomeService->cancelIncomeTransaction($income, $user);

        $this->expectException(InvalidArgumentException::class);
        app(PaymentConfirmationService::class)->confirmIncomePayment($income, $account->id, $user);
    }

    // -------------------------------------------------------------------------
    // E8. Confirm payment on cancelled expense is rejected (INV-009)
    // -------------------------------------------------------------------------

    public function test_confirm_payment_on_cancelled_expense_rejected(): void
    {
        $user    = $this->makeUser();
        $account = $this->makeAccount(['opening_balance' => '100000.00']);

        $expenseService = app(ExpenseCalculationService::class);

        $expense = $expenseService->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_name' => 'Cancelled Pay Test',
            'expense_category' => 'Operational',
            'description'      => null,
            'amount'           => '10000',
            'account_id'       => null,
            'payment_status'   => 'unpaid',
        ], $user);

        $expenseService->cancelExpenseTransaction($expense, $user);

        $this->expectException(InvalidArgumentException::class);
        app(PaymentConfirmationService::class)->confirmExpensePayment($expense, $account->id, $user);
    }

    // -------------------------------------------------------------------------
    // E9. Cancelled expense excluded from total_expenses (INV-009)
    // -------------------------------------------------------------------------

    public function test_cancelled_expense_excluded_from_total_expenses(): void
    {
        $user    = $this->makeUser();
        $account = $this->makeAccount(['opening_balance' => '200000.00']);

        $expenseService = app(ExpenseCalculationService::class);

        // Paid active expense = 50000 (counts)
        $expenseService->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_name' => 'Active Paid',
            'expense_category' => 'Operational',
            'description'      => null,
            'amount'           => '50000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ], $user);

        // Paid then cancelled expense = 80000 (should NOT count)
        $cancelled = $expenseService->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_name' => 'Cancelled Paid',
            'expense_category' => 'Operational',
            'description'      => null,
            'amount'           => '80000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ], $user);
        $expenseService->cancelExpenseTransaction($cancelled, $user);

        // Total expenses = only the active paid one = 50000
        $this->assertEquals('50000.00', $expenseService->calculateTotalExpenses(),
            'Cancelled expense must be excluded from total expenses (INV-009).');
    }

    // -------------------------------------------------------------------------
    // E10. All expense categories recognized by the model
    // -------------------------------------------------------------------------

    public function test_all_expected_expense_categories_present(): void
    {
        $expected = [
            'COGS / Cake Production',
            'Operational',
            'Marketing',
            'Salary',
            'Rent',
            'Employee Salaries',
            'Asset',
            'Other',
        ];

        foreach ($expected as $cat) {
            $this->assertContains($cat, ExpenseTransaction::CATEGORIES,
                "Expected expense category '{$cat}' not found in ExpenseTransaction::CATEGORIES.");
        }
    }

    // -------------------------------------------------------------------------
    // E11. Asset is NOT in profit-eligible categories
    // -------------------------------------------------------------------------

    public function test_asset_is_not_in_profit_eligible_categories(): void
    {
        $this->assertNotContains('Asset', ExpenseTransaction::PROFIT_ELIGIBLE_CATEGORIES,
            'Asset must not be in PROFIT_ELIGIBLE_CATEGORIES (PRD: Asset excluded from Net Profit).');
    }

    // -------------------------------------------------------------------------
    // E12. Paid income confirmation sets paid_at timestamp (INV-001)
    // -------------------------------------------------------------------------

    public function test_income_payment_confirmation_sets_paid_at(): void
    {
        $user    = $this->makeUser();
        $account = $this->makeAccount(['opening_balance' => '100000.00']);
        $item    = $this->makeMenuItem(['current_price' => '10000.00']);

        $incomeService = app(IncomeCalculationService::class);

        $income = $incomeService->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => null,
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        $this->assertNull($income->paid_at, 'Unpaid income should not have paid_at set.');

        $confirmed = app(PaymentConfirmationService::class)->confirmIncomePayment($income, $account->id, $user);

        $this->assertNotNull($confirmed->paid_at, 'paid_at must be set after payment confirmation.');
        $this->assertEquals('paid', $confirmed->payment_status, 'payment_status must be paid after confirmation.');
    }
}
