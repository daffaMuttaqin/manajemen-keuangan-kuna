<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\IncomeTransaction;
use App\Models\MenuItem;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\IncomeCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class IncomeSalesChannelTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Account $account;
    private MenuItem $menuItem;
    private IncomeCalculationService $incomeService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->account = Account::create([
            'name'             => 'Main Cash Account',
            'account_type'     => 'cash',
            'opening_balance'  => '1000000.00',
            'current_balance'  => '1000000.00',
            'is_active'        => true,
        ]);

        $this->menuItem = MenuItem::create([
            'name'          => 'Croissant',
            'category'      => 'Pastry',
            'current_price' => '50000.00',
            'is_active'     => true,
        ]);

        $this->incomeService = app(IncomeCalculationService::class);
    }

    /** Test 1: Income accepts all 4 sales channels */
    public function test_income_accepts_all_four_sales_channels(): void
    {
        $channels = ['Cafe', 'Online', 'Reseller', 'Other'];

        foreach ($channels as $channel) {
            $income = $this->incomeService->createIncomeTransaction([
                'transaction_date'    => '2026-08-19',
                'menu_item_id'        => $this->menuItem->id,
                'quantity'            => 1,
                'discount_percentage' => 0,
                'category'            => 'Sales',
                'sales_channel'       => $channel,
                'payment_status'      => 'unpaid',
            ], $this->user);

            $this->assertEquals($channel, $income->sales_channel);
        }
    }

    /** Test 2: Invalid sales channel is rejected */
    public function test_invalid_sales_channel_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->incomeService->createIncomeTransaction([
            'transaction_date'    => '2026-08-19',
            'menu_item_id'        => $this->menuItem->id,
            'quantity'            => 1,
            'discount_percentage' => 0,
            'category'            => 'Sales',
            'sales_channel'       => 'InvalidChannel',
            'payment_status'      => 'unpaid',
        ], $this->user);
    }

    /** Test 3: Sales channel is persisted correctly */
    public function test_sales_channel_is_persisted_correctly(): void
    {
        $income = $this->incomeService->createIncomeTransaction([
            'transaction_date'    => '2026-08-19',
            'menu_item_id'        => $this->menuItem->id,
            'quantity'            => 2,
            'discount_percentage' => 10,
            'category'            => 'Pastry',
            'sales_channel'       => 'Online',
            'account_id'          => $this->account->id,
            'payment_status'      => 'paid',
        ], $this->user);

        $this->assertDatabaseHas('income_transactions', [
            'id'            => $income->id,
            'sales_channel' => 'Online',
            'total_amount'  => '90000.00',
        ]);
    }

    /** Test 4: Existing income creation/edit/payment/cancellation behavior remains correct */
    public function test_existing_income_lifecycle_remains_correct(): void
    {
        // Creation
        $income = $this->incomeService->createIncomeTransaction([
            'transaction_date'    => '2026-08-19',
            'menu_item_id'        => $this->menuItem->id,
            'quantity'            => 1,
            'discount_percentage' => 0,
            'category'            => 'Pastry',
            'sales_channel'       => 'Cafe',
            'payment_status'      => 'unpaid',
        ], $this->user);

        $this->assertTrue($income->isUnpaid());
        $this->assertTrue($income->isActive());

        // Edit
        $updated = $this->incomeService->updateIncomeTransaction($income, [
            'transaction_date'    => '2026-08-19',
            'menu_item_id'        => $this->menuItem->id,
            'quantity'            => 2,
            'discount_percentage' => 0,
            'category'            => 'Pastry',
            'sales_channel'       => 'Reseller',
            'account_id'          => $this->account->id,
            'payment_status'      => 'paid',
        ], $this->user);

        $this->assertEquals('Reseller', $updated->sales_channel);
        $this->assertEquals('100000.00', (string) $updated->total_amount);
        $this->assertTrue($updated->isPaid());

        // Cancellation
        $this->incomeService->cancelIncomeTransaction($updated, $this->user);
        $updated->refresh();
        $this->assertTrue($updated->isCancelled());
    }

    /** Test 5: income_created and income_updated audit details contain sales_channel */
    public function test_audit_details_contain_sales_channel(): void
    {
        // Created audit
        $income = $this->incomeService->createIncomeTransaction([
            'transaction_date'    => '2026-08-19',
            'menu_item_id'        => $this->menuItem->id,
            'quantity'            => 1,
            'discount_percentage' => 0,
            'category'            => 'Pastry',
            'sales_channel'       => 'Online',
            'payment_status'      => 'unpaid',
        ], $this->user);

        $createdLog = AuditLog::where('action', 'income_created')
            ->where('auditable_id', $income->id)
            ->firstOrFail();

        $this->assertArrayHasKey('sales_channel', $createdLog->details['new']);
        $this->assertEquals('Online', $createdLog->details['new']['sales_channel']);

        // Updated audit
        $this->incomeService->updateIncomeTransaction($income, [
            'transaction_date'    => '2026-08-19',
            'menu_item_id'        => $this->menuItem->id,
            'quantity'            => 2,
            'discount_percentage' => 0,
            'category'            => 'Pastry',
            'sales_channel'       => 'Reseller',
            'payment_status'      => 'unpaid',
        ], $this->user);

        $updatedLog = AuditLog::where('action', 'income_updated')
            ->where('auditable_id', $income->id)
            ->firstOrFail();

        $this->assertArrayHasKey('sales_channel', $updatedLog->details['before']);
        $this->assertArrayHasKey('sales_channel', $updatedLog->details['after']);
        $this->assertEquals('Online', $updatedLog->details['before']['sales_channel']);
        $this->assertEquals('Reseller', $updatedLog->details['after']['sales_channel']);
    }

    /** Test 6: Dashboard chart includes active paid + unpaid income */
    public function test_dashboard_chart_includes_active_paid_and_unpaid_income(): void
    {
        // Paid income
        $this->incomeService->createIncomeTransaction([
            'transaction_date'    => '2026-08-19',
            'menu_item_id'        => $this->menuItem->id,
            'quantity'            => 1,
            'discount_percentage' => 0,
            'category'            => 'Sales',
            'sales_channel'       => 'Cafe',
            'account_id'          => $this->account->id,
            'payment_status'      => 'paid',
        ], $this->user);

        // Unpaid income
        $this->incomeService->createIncomeTransaction([
            'transaction_date'    => '2026-08-19',
            'menu_item_id'        => $this->menuItem->id,
            'quantity'            => 2,
            'discount_percentage' => 0,
            'category'            => 'Sales',
            'sales_channel'       => 'Cafe',
            'payment_status'      => 'unpaid',
        ], $this->user);

        $channelData = $this->incomeService->calculateRevenueBySalesChannel('2026-08-19', '2026-08-19');

        // Total should be 50,000 + 100,000 = 150,000
        $this->assertEquals(150000.0, $channelData['Cafe']);
    }

    /** Test 7: Cancelled income is excluded */
    public function test_cancelled_income_is_excluded_from_chart(): void
    {
        $income = $this->incomeService->createIncomeTransaction([
            'transaction_date'    => '2026-08-19',
            'menu_item_id'        => $this->menuItem->id,
            'quantity'            => 1,
            'discount_percentage' => 0,
            'category'            => 'Sales',
            'sales_channel'       => 'Online',
            'payment_status'      => 'unpaid',
        ], $this->user);

        $this->incomeService->cancelIncomeTransaction($income, $this->user);

        $channelData = $this->incomeService->calculateRevenueBySalesChannel('2026-08-19', '2026-08-19');
        $this->assertEquals(0.0, $channelData['Online']);
    }

    /** Test 8: Revenue is grouped correctly by all 4 channels */
    public function test_revenue_is_grouped_correctly_by_all_four_channels(): void
    {
        $this->incomeService->createIncomeTransaction([
            'transaction_date' => '2026-08-19', 'menu_item_id' => $this->menuItem->id,
            'quantity' => 1, 'discount_percentage' => 0, 'category' => 'Sales', 'sales_channel' => 'Cafe', 'payment_status' => 'unpaid',
        ], $this->user); // 50,000

        $this->incomeService->createIncomeTransaction([
            'transaction_date' => '2026-08-19', 'menu_item_id' => $this->menuItem->id,
            'quantity' => 2, 'discount_percentage' => 0, 'category' => 'Sales', 'sales_channel' => 'Online', 'payment_status' => 'unpaid',
        ], $this->user); // 100,000

        $this->incomeService->createIncomeTransaction([
            'transaction_date' => '2026-08-19', 'menu_item_id' => $this->menuItem->id,
            'quantity' => 3, 'discount_percentage' => 0, 'category' => 'Sales', 'sales_channel' => 'Reseller', 'payment_status' => 'unpaid',
        ], $this->user); // 150,000

        $this->incomeService->createIncomeTransaction([
            'transaction_date' => '2026-08-19', 'menu_item_id' => $this->menuItem->id,
            'quantity' => 4, 'discount_percentage' => 0, 'category' => 'Sales', 'sales_channel' => 'Other', 'payment_status' => 'unpaid',
        ], $this->user); // 200,000

        $channelData = $this->incomeService->calculateRevenueBySalesChannel('2026-08-19', '2026-08-19');

        $this->assertEquals(50000.0, $channelData['Cafe']);
        $this->assertEquals(100000.0, $channelData['Online']);
        $this->assertEquals(150000.0, $channelData['Reseller']);
        $this->assertEquals(200000.0, $channelData['Other']);
    }

    /** Test 9: Sum of channel revenue equals Dashboard Total Revenue */
    public function test_sum_of_channel_revenue_equals_dashboard_total_revenue(): void
    {
        $this->incomeService->createIncomeTransaction([
            'transaction_date' => '2026-08-19', 'menu_item_id' => $this->menuItem->id,
            'quantity' => 2, 'discount_percentage' => 0, 'category' => 'Sales', 'sales_channel' => 'Cafe', 'payment_status' => 'unpaid',
        ], $this->user);

        $this->incomeService->createIncomeTransaction([
            'transaction_date' => '2026-08-19', 'menu_item_id' => $this->menuItem->id,
            'quantity' => 5, 'discount_percentage' => 0, 'category' => 'Sales', 'sales_channel' => 'Online', 'account_id' => $this->account->id, 'payment_status' => 'paid',
        ], $this->user);

        $channelData  = $this->incomeService->calculateRevenueBySalesChannel('2026-08-19', '2026-08-19');
        $sumChannels  = array_sum($channelData);
        $totalOmset   = (float) $this->incomeService->calculateTotalOmset('2026-08-19', '2026-08-19');

        $this->assertEquals($totalOmset, $sumChannels);
    }

    /** Test 10: Dashboard period filtering applies correctly */
    public function test_dashboard_period_filtering_applies_correctly(): void
    {
        // Past month
        $this->incomeService->createIncomeTransaction([
            'transaction_date' => '2026-07-15', 'menu_item_id' => $this->menuItem->id,
            'quantity' => 1, 'discount_percentage' => 0, 'category' => 'Sales', 'sales_channel' => 'Cafe', 'payment_status' => 'unpaid',
        ], $this->user);

        // Current date
        $this->incomeService->createIncomeTransaction([
            'transaction_date' => '2026-08-19', 'menu_item_id' => $this->menuItem->id,
            'quantity' => 2, 'discount_percentage' => 0, 'category' => 'Sales', 'sales_channel' => 'Cafe', 'payment_status' => 'unpaid',
        ], $this->user);

        $julyData = $this->incomeService->calculateRevenueBySalesChannel('2026-07-01', '2026-07-31');
        $augustData = $this->incomeService->calculateRevenueBySalesChannel('2026-08-01', '2026-08-31');

        $this->assertEquals(50000.0, $julyData['Cafe']);
        $this->assertEquals(100000.0, $augustData['Cafe']);
    }

    /** Test 11: Unpaid income does not affect Account Balance or Net Profit */
    public function test_unpaid_income_does_not_affect_account_balance_or_net_profit(): void
    {
        $initialBalance = app(AccountBalanceService::class)->calculateBalance($this->account);

        $this->incomeService->createIncomeTransaction([
            'transaction_date'    => '2026-08-19',
            'menu_item_id'        => $this->menuItem->id,
            'quantity'            => 10,
            'discount_percentage' => 0,
            'category'            => 'Sales',
            'sales_channel'       => 'Online',
            'payment_status'      => 'unpaid',
        ], $this->user);

        $finalBalance = app(AccountBalanceService::class)->calculateBalance($this->account);
        $paidRevenue  = (float) $this->incomeService->calculateRevenue('2026-08-19', '2026-08-19');

        $this->assertEquals($initialBalance, $finalBalance);
        $this->assertEquals(0.0, $paidRevenue);
    }
}
