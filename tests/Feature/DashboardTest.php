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
        $response->assertSee('15.000.000');
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
        $response->assertViewHas('unpaidRevenue', 0.0);
        $response->assertViewHas('totalExpenses', 0.0);
        $response->assertViewHas('netProfit', 0.0);
        $response->assertSee('No transactions recorded for this period');
    }

    // -------------------------------------------------------------------------
    // Focused tests for Dashboard Revenue & Unpaid Revenue revision
    // -------------------------------------------------------------------------

    /**
     * Requirement 1: Paid active income is included in Total Revenue (totalRevenue).
     */
    public function test_dashboard_total_revenue_includes_paid_active_income(): void
    {
        $user    = User::factory()->create();
        $account = Account::create(['name' => 'Cash', 'account_type' => 'cash', 'opening_balance' => 0, 'is_active' => true]);
        $item    = MenuItem::create(['name' => 'Tart', 'category' => 'Pastry', 'current_price' => 150000, 'is_active' => true]);

        app(\App\Services\IncomeCalculationService::class)->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '2',
            'discount_percentage' => '0',
            'category'            => 'Pastry',
            'account_id'          => $account->id,
            'payment_status'      => 'paid',
        ], $user);

        $response = $this->actingAs($user)->get('/dashboard?period=all_time');

        $response->assertStatus(200);
        // 2 × 150,000 = 300,000 — paid income contributes to totalRevenue
        $response->assertViewHas('totalRevenue', 300000.0);
    }

    /**
     * Requirement 2: Unpaid active income is included in Total Revenue (totalRevenue).
     */
    public function test_dashboard_total_revenue_includes_unpaid_active_income(): void
    {
        $user = User::factory()->create();
        $item = MenuItem::create(['name' => 'Scone', 'category' => 'Pastry', 'current_price' => 50000, 'is_active' => true]);

        // Unpaid income — no account required
        app(\App\Services\IncomeCalculationService::class)->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Pastry',
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        $response = $this->actingAs($user)->get('/dashboard?period=all_time');

        $response->assertStatus(200);
        // Unpaid income = 50,000 should appear in totalRevenue
        $response->assertViewHas('totalRevenue', 50000.0);
    }

    /**
     * Requirement 3: Cancelled income is excluded from Total Revenue.
     */
    public function test_dashboard_total_revenue_excludes_cancelled_income(): void
    {
        $user    = User::factory()->create();
        $account = Account::create(['name' => 'Bank', 'account_type' => 'bank', 'opening_balance' => 0, 'is_active' => true]);
        $item    = MenuItem::create(['name' => 'Muffin', 'category' => 'Pastry', 'current_price' => 80000, 'is_active' => true]);

        $incomeService = app(\App\Services\IncomeCalculationService::class);

        // Create and immediately cancel a paid income
        $cancelled = $incomeService->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '3',
            'discount_percentage' => '0',
            'category'            => 'Pastry',
            'account_id'          => $account->id,
            'payment_status'      => 'paid',
        ], $user);
        $incomeService->cancelIncomeTransaction($cancelled);

        $response = $this->actingAs($user)->get('/dashboard?period=all_time');

        $response->assertStatus(200);
        // Cancelled income must not contribute to totalRevenue
        $response->assertViewHas('totalRevenue', 0.0);
    }

    /**
     * Requirement 4: Unpaid Revenue card contains only active + unpaid income.
     */
    public function test_dashboard_unpaid_revenue_contains_only_active_unpaid_income(): void
    {
        $user    = User::factory()->create();
        $account = Account::create(['name' => 'Bank', 'account_type' => 'bank', 'opening_balance' => 0, 'is_active' => true]);
        $item    = MenuItem::create(['name' => 'Brownie', 'category' => 'Pastry', 'current_price' => 60000, 'is_active' => true]);

        $incomeService = app(\App\Services\IncomeCalculationService::class);

        // Paid income — should NOT appear in unpaidRevenue
        $incomeService->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '2',
            'discount_percentage' => '0',
            'category'            => 'Pastry',
            'account_id'          => $account->id,
            'payment_status'      => 'paid',
        ], $user);

        // Unpaid income — should appear in unpaidRevenue
        $incomeService->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Pastry',
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        // Cancelled unpaid income — should NOT appear in unpaidRevenue
        $cancelledUnpaid = $incomeService->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '5',
            'discount_percentage' => '0',
            'category'            => 'Pastry',
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);
        $incomeService->cancelIncomeTransaction($cancelledUnpaid);

        $response = $this->actingAs($user)->get('/dashboard?period=all_time');

        $response->assertStatus(200);
        // totalRevenue = paid (120,000) + unpaid (60,000) = 180,000
        $response->assertViewHas('totalRevenue', 180000.0);
        // unpaidRevenue = only active unpaid = 60,000
        $response->assertViewHas('unpaidRevenue', 60000.0);
        // UI shows the Unpaid Revenue card
        $response->assertSee('Unpaid Revenue');
    }

    /**
     * Requirement 5: Period/date filtering applies correctly to both metrics.
     */
    public function test_dashboard_revenue_metrics_respect_period_filter(): void
    {
        $user    = User::factory()->create();
        $account = Account::create(['name' => 'Bank', 'account_type' => 'bank', 'opening_balance' => 0, 'is_active' => true]);
        $item    = MenuItem::create(['name' => 'Pie', 'category' => 'Pastry', 'current_price' => 100000, 'is_active' => true]);

        $incomeService = app(\App\Services\IncomeCalculationService::class);

        // Paid income — this month
        $incomeService->createIncomeTransaction([
            'transaction_date'    => '2026-08-10',
            'menu_item_id'        => $item->id,
            'quantity'            => '2',
            'discount_percentage' => '0',
            'category'            => 'Pastry',
            'account_id'          => $account->id,
            'payment_status'      => 'paid',
        ], $user);

        // Unpaid income — this month
        $incomeService->createIncomeTransaction([
            'transaction_date'    => '2026-08-12',
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Pastry',
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        // Paid income — outside the query window (July)
        $incomeService->createIncomeTransaction([
            'transaction_date'    => '2026-07-01',
            'menu_item_id'        => $item->id,
            'quantity'            => '10',
            'discount_percentage' => '0',
            'category'            => 'Pastry',
            'account_id'          => $account->id,
            'payment_status'      => 'paid',
        ], $user);

        // Custom filter: August only
        $response = $this->actingAs($user)->get('/dashboard?period=custom&from=2026-08-01&to=2026-08-31');

        $response->assertStatus(200);
        // totalRevenue Aug: paid(200,000) + unpaid(100,000) = 300,000
        $response->assertViewHas('totalRevenue', 300000.0);
        // unpaidRevenue Aug: 100,000
        $response->assertViewHas('unpaidRevenue', 100000.0);
        // netProfit is based on paid-only revenue; no expenses = 200,000
        $response->assertViewHas('netProfit', 200000.0);
    }

    /**
     * Requirement 6: Unpaid income does NOT affect Account Balance or Net Profit.
     */
    public function test_unpaid_income_does_not_affect_account_balance_or_net_profit(): void
    {
        $user    = User::factory()->create();
        $account = Account::create([
            'name'            => 'Main Bank',
            'account_type'    => 'bank',
            'opening_balance' => 1000000,
            'is_active'       => true,
        ]);
        $item = MenuItem::create(['name' => 'Waffle', 'category' => 'Pastry', 'current_price' => 200000, 'is_active' => true]);

        $incomeService  = app(\App\Services\IncomeCalculationService::class);
        $balanceService = app(\App\Services\AccountBalanceService::class);

        // Create unpaid income (should NOT credit the account balance)
        $incomeService->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '3',
            'discount_percentage' => '0',
            'category'            => 'Pastry',
            'account_id'          => null,
            'payment_status'      => 'unpaid',
        ], $user);

        $response = $this->actingAs($user)->get('/dashboard?period=all_time');
        $response->assertStatus(200);

        // Account balance unchanged — only the opening balance
        $this->assertEquals(1000000.0, (float) $balanceService->calculateBalance($account));

        // totalRevenue includes unpaid: 600,000
        $response->assertViewHas('totalRevenue', 600000.0);

        // Net Profit is based on PAID revenue only: 0 (no paid income) - 0 expenses = 0
        $response->assertViewHas('netProfit', 0.0);

        // unpaidRevenue = 600,000
        $response->assertViewHas('unpaidRevenue', 600000.0);
    }
}
