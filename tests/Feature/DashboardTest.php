<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_dashboard(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_access_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Financial Overview');
    }

    public function test_dashboard_displays_real_active_account_balances(): void
    {
        $user = User::factory()->create();

        Account::create([
            'name' => 'Main Operational Account',
            'account_type' => 'bank',
            'opening_balance' => 15000000.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Main Operational Account');
        $response->assertSee('15.000.000,00');
    }

    public function test_dashboard_displays_active_menu_items_count(): void
    {
        $user = User::factory()->create();

        MenuItem::create([
            'name' => 'Croissant',
            'category' => 'Pastry',
            'current_price' => 25000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Active Menu Items');
    }

    public function test_dashboard_displays_net_profit_kpi(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'name' => 'Main Account',
            'account_type' => 'bank',
            'opening_balance' => 1000000.00,
            'is_active' => true,
        ]);

        $item = MenuItem::create([
            'name' => 'Opera Cake',
            'category' => 'Cake Sales',
            'current_price' => 1000000.00,
            'is_active' => true,
        ]);

        app(\App\Services\IncomeCalculationService::class)->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'description'         => 'Test Revenue',
            'account_id'          => $account->id,
            'payment_status'      => 'paid',
        ], $user);

        app(\App\Services\ExpenseCalculationService::class)->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_name' => 'Operational Expense',
            'expense_category' => 'Operational',
            'amount'           => '200000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ], $user);

        // Asset expense of 500,000 does NOT reduce Net Profit
        app(\App\Services\ExpenseCalculationService::class)->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_name' => 'Asset Purchase',
            'expense_category' => 'Asset',
            'amount'           => '500000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ], $user);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Net Profit');
    }

    public function test_dashboard_filters_kpis_by_date_range(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'name' => 'Bank Account',
            'account_type' => 'bank',
            'opening_balance' => 1000000,
            'is_active' => true,
        ]);

        $item = MenuItem::create([
            'name' => 'Pastry',
            'category' => 'Pastry Sales',
            'current_price' => 100000,
            'is_active' => true,
        ]);

        // Income last month (2026-07-15)
        app(\App\Services\IncomeCalculationService::class)->createIncomeTransaction([
            'transaction_date'    => '2026-07-15',
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Pastry Sales',
            'description'         => 'July Sale',
            'account_id'          => $account->id,
            'payment_status'      => 'paid',
        ], $user);

        // Expense this month (2026-08-10)
        app(\App\Services\ExpenseCalculationService::class)->createExpenseTransaction([
            'transaction_date' => '2026-08-10',
            'transaction_name' => 'August Flour Purchase',
            'expense_category' => 'Operational',
            'amount'           => '30000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ], $user);

        // Custom filter for July 2026
        $response = $this->actingAs($user)->get('/dashboard?period=custom&from=2026-07-01&to=2026-07-31');

        $response->assertStatus(200);
        $response->assertViewHas('totalRevenue', 100000.0);
        $response->assertViewHas('totalExpenses', 0.0);
        $response->assertViewHas('netProfit', 100000.0);
    }

    public function test_dashboard_validates_custom_date_range_safely(): void
    {
        $user = User::factory()->create();

        // Invalid range: from > to
        $response = $this->actingAs($user)->get('/dashboard?period=custom&from=2026-08-20&to=2026-08-10');

        $response->assertStatus(200);
        $response->assertViewHas('period', 'this_month');
        $response->assertViewHas('dateValidationError');
        $response->assertSee('The &quot;from&quot; date must be earlier than or equal to the &quot;to&quot; date.', false);
    }

    public function test_dashboard_calculates_chart_net_profit_excluding_asset_expenses(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'name' => 'Main Account',
            'account_type' => 'bank',
            'opening_balance' => 5000000,
            'is_active' => true,
        ]);

        $item = MenuItem::create([
            'name' => 'Cake',
            'category' => 'Cake Sales',
            'current_price' => 500000,
            'is_active' => true,
        ]);

        // Revenue: 500,000
        app(\App\Services\IncomeCalculationService::class)->createIncomeTransaction([
            'transaction_date'    => '2026-08-05',
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'account_id'          => $account->id,
            'payment_status'      => 'paid',
        ], $user);

        // OpEx (Profit-Eligible): 100,000
        app(\App\Services\ExpenseCalculationService::class)->createExpenseTransaction([
            'transaction_date' => '2026-08-06',
            'transaction_name' => 'Packaging Material',
            'expense_category' => 'Operational',
            'amount'           => '100000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ], $user);

        // Asset Expense: 300,000 (Included in Total Expenses, EXCLUDED from Net Profit)
        app(\App\Services\ExpenseCalculationService::class)->createExpenseTransaction([
            'transaction_date' => '2026-08-07',
            'transaction_name' => 'Mixer Asset',
            'expense_category' => 'Asset',
            'amount'           => '300000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ], $user);

        $response = $this->actingAs($user)->get('/dashboard?period=custom&from=2026-08-01&to=2026-08-31');

        $response->assertStatus(200);
        $chartData = $response->viewData('chartData');

        // Sum of Revenue across the 31-day chart buckets = 500,000
        $this->assertEquals(500000.0, array_sum($chartData['revenue']));

        // Sum of Total Expenses across chart buckets = 400,000 (100k OpEx + 300k Asset)
        $this->assertEquals(400000.0, array_sum($chartData['total_expenses']));

        // Sum of Net Profit across chart buckets = 400,000 (500k Rev - 100k OpEx = 400k; Asset expense is excluded!)
        $this->assertEquals(400000.0, array_sum($chartData['net_profit']));
    }

    public function test_dashboard_displays_recent_transactions_feed_with_native_statuses(): void
    {
        $user = User::factory()->create();
        $account1 = Account::create(['name' => 'BCA Account', 'account_type' => 'bank', 'opening_balance' => 2000000, 'is_active' => true]);
        $account2 = Account::create(['name' => 'Cash Account', 'account_type' => 'cash', 'opening_balance' => 500000, 'is_active' => true]);

        $item = MenuItem::create(['name' => 'Eclair', 'category' => 'Pastry', 'current_price' => 50000, 'is_active' => true]);

        // Unpaid Income -> Status: Unpaid
        app(\App\Services\IncomeCalculationService::class)->createIncomeTransaction([
            'transaction_date'    => '2026-08-10',
            'menu_item_id'        => $item->id,
            'quantity'            => '2',
            'discount_percentage' => '0',
            'category'            => 'Pastry',
            'account_id'          => $account1->id,
            'payment_status'      => 'unpaid',
        ], $user);

        // Transfer -> Status: Completed
        app(\App\Services\TransferService::class)->createTransfer([
            'transfer_date'   => '2026-08-11',
            'from_account_id' => $account1->id,
            'to_account_id'   => $account2->id,
            'amount'          => '100000',
            'description'     => 'Cash replenishment',
        ], $user);

        // Paid Expense -> Status: Paid
        app(\App\Services\ExpenseCalculationService::class)->createExpenseTransaction([
            'transaction_date' => '2026-08-12',
            'transaction_name' => 'Electricity Bill',
            'expense_category' => 'Operational',
            'amount'           => '150000',
            'account_id'       => $account1->id,
            'payment_status'   => 'paid',
        ], $user);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $recent = $response->viewData('recentTransactions');

        $this->assertCount(3, $recent);
        $this->assertEquals('Electricity Bill', $recent[0]['title']);
        $this->assertEquals('Paid', $recent[0]['status_display']);

        $this->assertEquals('Transfer: BCA Account → Cash Account', $recent[1]['title']);
        $this->assertEquals('Completed', $recent[1]['status_display']);

        $this->assertEquals('Sales: Eclair', $recent[2]['title']);
        $this->assertEquals('Unpaid', $recent[2]['status_display']);
    }

    public function test_dashboard_empty_period_state(): void
    {
        $user = User::factory()->create();

        // Query date range in the far future where no transactions exist (FT-027)
        $response = $this->actingAs($user)->get('/dashboard?period=custom&from=2030-01-01&to=2030-01-31');

        $response->assertStatus(200);
        $response->assertViewHas('totalRevenue', 0.0);
        $response->assertViewHas('totalExpenses', 0.0);
        $response->assertViewHas('netProfit', 0.0);
        $response->assertSee('No transactions recorded for this period');
    }
}

