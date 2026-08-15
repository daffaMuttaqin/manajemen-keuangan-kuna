<?php

namespace Tests\Feature;

use App\Livewire\Accounts\ManageAccounts;
use App\Livewire\AuditLogs\ManageAuditLogs;
use App\Livewire\Menu\ManageMenu;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\ExpenseTransaction;
use App\Models\IncomeTransaction;
use App\Models\MenuItem;
use App\Models\Transfer;
use App\Models\User;
use App\Services\ExpenseCalculationService;
use App\Services\IncomeCalculationService;
use App\Services\PaymentConfirmationService;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makeAccount(array $overrides = []): Account
    {
        return Account::create(array_merge([
            'name'            => 'Bank Central',
            'account_type'    => 'bank',
            'opening_balance' => '100000.00',
            'is_active'       => true,
        ], $overrides));
    }

    private function makeMenuItem(array $overrides = []): MenuItem
    {
        return MenuItem::create(array_merge([
            'name'          => 'Croissant',
            'category'      => 'Pastry',
            'current_price' => '25000.00',
            'is_active'     => true,
        ], $overrides));
    }

    public function test_guest_is_redirected_from_audit_logs_page(): void
    {
        $response = $this->get(route('audit-logs.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_access_audit_logs_page(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->get(route('audit-logs.index'));
        $response->assertStatus(200);
        $response->assertSee('Audit Trail & Activity History', false);
    }

    public function test_account_mutations_create_exactly_one_audit_record_each(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        // 1. Account Created
        Livewire::test(ManageAccounts::class)
            ->set('name', 'Kas Utama')
            ->set('account_type', 'cash')
            ->set('opening_balance', '50000')
            ->set('is_active', true)
            ->call('saveAccount');

        $this->assertDatabaseHas('audit_logs', [
            'action'         => 'account_created',
            'auditable_type' => Account::class,
            'user_id'        => $user->id,
        ]);
        $this->assertEquals(1, AuditLog::where('action', 'account_created')->count());

        $account = Account::where('name', 'Kas Utama')->firstOrFail();
        $createLog = AuditLog::where('action', 'account_created')->where('auditable_id', $account->id)->first();
        $this->assertEquals('Kas Utama', $createLog->details['new']['name']);

        // 2. Account Updated
        Livewire::test(ManageAccounts::class)
            ->set('editingAccountId', $account->id)
            ->set('name', 'Kas Utama Updated')
            ->set('account_type', 'cash')
            ->set('opening_balance', '60000')
            ->set('is_active', true)
            ->call('saveAccount');

        $this->assertDatabaseHas('audit_logs', [
            'action'         => 'account_updated',
            'auditable_type' => Account::class,
            'auditable_id'   => $account->id,
            'user_id'        => $user->id,
        ]);
        $this->assertEquals(1, AuditLog::where('action', 'account_updated')->count());

        $updateLog = AuditLog::where('action', 'account_updated')->where('auditable_id', $account->id)->first();
        $this->assertEquals('Kas Utama', $updateLog->details['before']['name']);
        $this->assertEquals('Kas Utama Updated', $updateLog->details['after']['name']);

        // 3. Account Deactivated
        Livewire::test(ManageAccounts::class)
            ->call('toggleActiveStatus', $account->id);

        $this->assertDatabaseHas('audit_logs', [
            'action'         => 'account_deactivated',
            'auditable_type' => Account::class,
            'auditable_id'   => $account->id,
        ]);
        $this->assertEquals(1, AuditLog::where('action', 'account_deactivated')->count());

        // 4. Account Activated
        Livewire::test(ManageAccounts::class)
            ->call('toggleActiveStatus', $account->id);

        $this->assertDatabaseHas('audit_logs', [
            'action'         => 'account_activated',
            'auditable_type' => Account::class,
            'auditable_id'   => $account->id,
        ]);
        $this->assertEquals(1, AuditLog::where('action', 'account_activated')->count());
    }

    public function test_menu_item_mutations_create_exactly_one_audit_record_each(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        // 1. Menu Item Created
        Livewire::test(ManageMenu::class)
            ->set('name', 'Eclair')
            ->set('category', 'Pastry')
            ->set('current_price', '30000')
            ->set('is_active', true)
            ->call('saveMenuItem');

        $this->assertDatabaseHas('audit_logs', [
            'action'         => 'menu_item_created',
            'auditable_type' => MenuItem::class,
            'user_id'        => $user->id,
        ]);
        $this->assertEquals(1, AuditLog::where('action', 'menu_item_created')->count());

        $menuItem = MenuItem::where('name', 'Eclair')->firstOrFail();

        // 2. Menu Item Updated
        Livewire::test(ManageMenu::class)
            ->set('editingMenuItemId', $menuItem->id)
            ->set('name', 'Eclair Vanilla')
            ->set('category', 'Pastry')
            ->set('current_price', '35000')
            ->set('is_active', true)
            ->call('saveMenuItem');

        $this->assertDatabaseHas('audit_logs', [
            'action'         => 'menu_item_updated',
            'auditable_type' => MenuItem::class,
            'auditable_id'   => $menuItem->id,
        ]);
        $this->assertEquals(1, AuditLog::where('action', 'menu_item_updated')->count());

        // 3. Menu Item Deactivated
        Livewire::test(ManageMenu::class)
            ->call('toggleActiveStatus', $menuItem->id);

        $this->assertDatabaseHas('audit_logs', [
            'action'         => 'menu_item_deactivated',
            'auditable_type' => MenuItem::class,
            'auditable_id'   => $menuItem->id,
        ]);
        $this->assertEquals(1, AuditLog::where('action', 'menu_item_deactivated')->count());

        // 4. Menu Item Activated
        Livewire::test(ManageMenu::class)
            ->call('toggleActiveStatus', $menuItem->id);

        $this->assertDatabaseHas('audit_logs', [
            'action'         => 'menu_item_activated',
            'auditable_type' => MenuItem::class,
            'auditable_id'   => $menuItem->id,
        ]);
        $this->assertEquals(1, AuditLog::where('action', 'menu_item_activated')->count());
    }

    public function test_income_mutations_create_exactly_one_audit_record_each(): void
    {
        $user = $this->makeUser();
        $account = $this->makeAccount();
        $item = $this->makeMenuItem();
        $incomeService = app(IncomeCalculationService::class);
        $confirmService = app(PaymentConfirmationService::class);

        // 1. Income Created
        $income = $incomeService->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '2',
            'discount_percentage' => '0',
            'category'            => 'Pastry',
            'description'         => 'Test sale',
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        $this->assertDatabaseHas('audit_logs', [
            'action'         => 'income_created',
            'auditable_type' => IncomeTransaction::class,
            'auditable_id'   => $income->id,
            'user_id'        => $user->id,
        ]);
        $this->assertEquals(1, AuditLog::where('action', 'income_created')->where('auditable_id', $income->id)->count());

        // 2. Income Updated
        $incomeService->updateIncomeTransaction($income, [
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '3',
            'discount_percentage' => '0',
            'category'            => 'Pastry',
            'description'         => 'Updated sale',
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        $this->assertDatabaseHas('audit_logs', [
            'action'         => 'income_updated',
            'auditable_type' => IncomeTransaction::class,
            'auditable_id'   => $income->id,
        ]);
        $this->assertEquals(1, AuditLog::where('action', 'income_updated')->where('auditable_id', $income->id)->count());

        // 3. Income Payment Confirmed
        $confirmService->confirmIncomePayment($income, $account->id, $user);

        $this->assertDatabaseHas('audit_logs', [
            'action'         => 'income_payment_confirmed',
            'auditable_type' => IncomeTransaction::class,
            'auditable_id'   => $income->id,
            'user_id'        => $user->id,
        ]);
        $this->assertEquals(1, AuditLog::where('action', 'income_payment_confirmed')->where('auditable_id', $income->id)->count());

        // 4. Income Cancelled
        // Note: Create new income for cancellation test
        $incomeToCancel = $incomeService->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Pastry',
            'description'         => 'Cancel me',
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        $incomeService->cancelIncomeTransaction($incomeToCancel, $user);

        $this->assertDatabaseHas('audit_logs', [
            'action'         => 'income_cancelled',
            'auditable_type' => IncomeTransaction::class,
            'auditable_id'   => $incomeToCancel->id,
            'user_id'        => $user->id,
        ]);
        $this->assertEquals(1, AuditLog::where('action', 'income_cancelled')->where('auditable_id', $incomeToCancel->id)->count());
    }

    public function test_expense_mutations_create_exactly_one_audit_record_each(): void
    {
        $user = $this->makeUser();
        $account = $this->makeAccount();
        $expenseService = app(ExpenseCalculationService::class);
        $confirmService = app(PaymentConfirmationService::class);

        // 1. Expense Created
        $expense = $expenseService->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_name' => 'Raw Materials',
            'expense_category' => 'Ingredients',
            'description'      => 'Flour purchase',
            'amount'           => '15000',
            'account_id'       => null,
            'payment_status'   => 'unpaid',
        ], $user);

        $this->assertDatabaseHas('audit_logs', [
            'action'         => 'expense_created',
            'auditable_type' => ExpenseTransaction::class,
            'auditable_id'   => $expense->id,
            'user_id'        => $user->id,
        ]);
        $this->assertEquals(1, AuditLog::where('action', 'expense_created')->where('auditable_id', $expense->id)->count());

        // 2. Expense Updated
        $expenseService->updateExpenseTransaction($expense, [
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_name' => 'Raw Materials Bulk',
            'expense_category' => 'Ingredients',
            'description'      => 'Flour purchase bulk',
            'amount'           => '20000',
            'account_id'       => null,
            'payment_status'   => 'unpaid',
        ], $user);

        $this->assertDatabaseHas('audit_logs', [
            'action'         => 'expense_updated',
            'auditable_type' => ExpenseTransaction::class,
            'auditable_id'   => $expense->id,
        ]);
        $this->assertEquals(1, AuditLog::where('action', 'expense_updated')->where('auditable_id', $expense->id)->count());

        // 3. Expense Payment Confirmed
        $confirmService->confirmExpensePayment($expense, $account->id, $user);

        $this->assertDatabaseHas('audit_logs', [
            'action'         => 'expense_payment_confirmed',
            'auditable_type' => ExpenseTransaction::class,
            'auditable_id'   => $expense->id,
            'user_id'        => $user->id,
        ]);
        $this->assertEquals(1, AuditLog::where('action', 'expense_payment_confirmed')->where('auditable_id', $expense->id)->count());

        // 4. Expense Cancelled
        $expenseToCancel = $expenseService->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_name' => 'Utility',
            'expense_category' => 'Utilities',
            'description'      => 'Cancel me',
            'amount'           => '5000',
            'account_id'       => null,
            'payment_status'   => 'unpaid',
        ], $user);

        $expenseService->cancelExpenseTransaction($expenseToCancel, $user);

        $this->assertDatabaseHas('audit_logs', [
            'action'         => 'expense_cancelled',
            'auditable_type' => ExpenseTransaction::class,
            'auditable_id'   => $expenseToCancel->id,
            'user_id'        => $user->id,
        ]);
        $this->assertEquals(1, AuditLog::where('action', 'expense_cancelled')->where('auditable_id', $expenseToCancel->id)->count());
    }

    public function test_transfer_mutations_create_exactly_one_audit_record_each(): void
    {
        $user = $this->makeUser();
        $accountA = $this->makeAccount(['opening_balance' => 500000.00]);
        $accountB = $this->makeAccount(['opening_balance' => 100000.00]);
        $transferService = app(TransferService::class);

        // 1. Transfer Created
        $transfer = $transferService->createTransfer([
            'transfer_date'   => now()->format('Y-m-d'),
            'from_account_id' => $accountA->id,
            'to_account_id'   => $accountB->id,
            'amount'          => '50000',
            'description'     => 'Vault refill',
        ], $user);

        $this->assertDatabaseHas('audit_logs', [
            'action'         => 'transfer_created',
            'auditable_type' => Transfer::class,
            'auditable_id'   => $transfer->id,
            'user_id'        => $user->id,
        ]);
        $this->assertEquals(1, AuditLog::where('action', 'transfer_created')->where('auditable_id', $transfer->id)->count());

        // 2. Transfer Cancelled
        $transferService->cancelTransfer($transfer, $user);

        $this->assertDatabaseHas('audit_logs', [
            'action'         => 'transfer_cancelled',
            'auditable_type' => Transfer::class,
            'auditable_id'   => $transfer->id,
            'user_id'        => $user->id,
        ]);
        $this->assertEquals(1, AuditLog::where('action', 'transfer_cancelled')->where('auditable_id', $transfer->id)->count());
    }

    public function test_audit_log_filters_and_pagination_work(): void
    {
        $user1 = $this->makeUser();
        $user2 = $this->makeUser();
        $account = $this->makeAccount();
        $item = $this->makeMenuItem();
        $incomeService = app(IncomeCalculationService::class);

        // Create 20 income transactions to test pagination
        for ($i = 0; $i < 20; $i++) {
            $incomeService->createIncomeTransaction([
                'transaction_date'    => now()->format('Y-m-d'),
                'menu_item_id'        => $item->id,
                'quantity'            => '1',
                'discount_percentage' => '0',
                'category'            => 'Pastry',
                'description'         => 'Batch ' . $i,
                'account_id'          => null,
                'payment_status'      => 'unpaid',
            ], ($i % 2 === 0) ? $user1 : $user2);
        }

        $this->assertEquals(20, AuditLog::where('action', 'income_created')->count());

        // Test Livewire component filtering
        Livewire::actingAs($user1)
            ->test(ManageAuditLogs::class)
            ->set('user_id', (string) $user1->id)
            ->set('actionFilter', 'income_created')
            ->assertViewHas('auditLogs', function ($logs) {
                return $logs->total() === 10 && $logs->perPage() === 15;
            });
    }

    public function test_sensitive_authentication_fields_are_absent_from_audit_details(): void
    {
        $user = $this->makeUser();
        $account = $this->makeAccount();

        // Perform mutation
        Livewire::actingAs($user)
            ->test(ManageAccounts::class)
            ->set('editingAccountId', $account->id)
            ->set('name', 'Updated Account Name')
            ->set('account_type', 'bank')
            ->set('opening_balance', '100000')
            ->set('is_active', true)
            ->call('saveAccount');

        $logs = AuditLog::all();
        foreach ($logs as $log) {
            $json = json_encode($log->details);
            $this->assertStringNotContainsString('password', strtolower($json));
            $this->assertStringNotContainsString('remember_token', strtolower($json));
            $this->assertStringNotContainsString('secret', strtolower($json));
        }
    }
}
