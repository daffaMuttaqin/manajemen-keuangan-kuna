<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ExpenseTransaction;
use App\Models\MenuItem;
use App\Models\User;
use App\Services\ExpenseCalculationService;
use App\Services\IncomeCalculationService;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // 1. Access Control
    // -------------------------------------------------------------------------

    public function test_guest_cannot_access_reports_or_exports(): void
    {
        $this->get('/reports')->assertRedirect(route('login'));
        $this->get('/reports/export/summary')->assertRedirect(route('login'));
        $this->get('/reports/export/income')->assertRedirect(route('login'));
        $this->get('/reports/export/expense')->assertRedirect(route('login'));
        $this->get('/reports/export/transfers')->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_access_reports_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/reports');

        $response->assertStatus(200);
        $response->assertSee('Financial Reports');
    }

    // -------------------------------------------------------------------------
    // 2. Mandatory Financial Summary & Net Profit Calculation Test
    // -------------------------------------------------------------------------

    public function test_reports_financial_summary_calculates_mandatory_net_profit_excluding_asset_expenses(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'name'            => 'Main Bank',
            'account_type'    => 'bank',
            'opening_balance' => 10000000.00,
            'is_active'       => true,
        ]);

        $item = MenuItem::create([
            'name'          => 'Whole Cake',
            'category'      => 'Cake Sales',
            'current_price' => 1000000.00,
            'is_active'     => true,
        ]);

        // Revenue: 1,000,000
        app(IncomeCalculationService::class)->createIncomeTransaction([
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Cake Sales',
            'account_id'          => $account->id,
            'payment_status'      => 'paid',
        ], $user);

        // OpEx (Profit-Eligible): 200,000
        app(ExpenseCalculationService::class)->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_name' => 'Operational Expenses',
            'expense_category' => 'Operational',
            'amount'           => '200000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ], $user);

        // Asset Expense: 500,000
        app(ExpenseCalculationService::class)->createExpenseTransaction([
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_name' => 'Equipment Asset Purchase',
            'expense_category' => 'Asset',
            'amount'           => '500000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ], $user);

        $reportService = app(\App\Services\FinancialReportService::class);
        $summary = $reportService->getFinancialSummary();

        // Exact mandatory assertions:
        $this->assertEquals(1000000.00, $summary['total_revenue']);
        $this->assertEquals(0.00, $summary['cogs']);
        $this->assertEquals(200000.00, $summary['profit_eligible_opex']);
        $this->assertEquals(200000.00, $summary['profit_eligible_exp']);
        $this->assertEquals(700000.00, $summary['total_expenses']); // 200k + 500k
        $this->assertEquals(500000.00, $summary['asset_expenses']);
        $this->assertEquals(800000.00, $summary['net_profit']);    // 1,000,000 - 200,000 = 800,000
    }

    // -------------------------------------------------------------------------
    // 3. Gross Profit & COGS Test
    // -------------------------------------------------------------------------

    public function test_reports_gross_profit_subtracts_cogs_and_ignores_asset_expenses(): void
    {
        $user = User::factory()->create();
        $account = Account::create(['name' => 'Bank', 'account_type' => 'bank', 'opening_balance' => 5000000, 'is_active' => true]);
        $item = MenuItem::create(['name' => 'Croissant', 'category' => 'Pastry', 'current_price' => 500000, 'is_active' => true]);

        // Revenue: 500,000
        app(IncomeCalculationService::class)->createIncomeTransaction([
            'transaction_date'    => '2026-08-01',
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Pastry',
            'account_id'          => $account->id,
            'payment_status'      => 'paid',
        ], $user);

        // COGS: 150,000
        app(ExpenseCalculationService::class)->createExpenseTransaction([
            'transaction_date' => '2026-08-01',
            'transaction_name' => 'Flour & Butter COGS',
            'expense_category' => 'COGS / Cake Production',
            'amount'           => '150000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ], $user);

        // Asset Expense: 200,000
        app(ExpenseCalculationService::class)->createExpenseTransaction([
            'transaction_date' => '2026-08-01',
            'transaction_name' => 'Oven Asset',
            'expense_category' => 'Asset',
            'amount'           => '200000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ], $user);

        $summary = app(\App\Services\FinancialReportService::class)->getFinancialSummary('2026-08-01', '2026-08-31');

        $this->assertEquals(500000.00, $summary['total_revenue']);
        $this->assertEquals(150000.00, $summary['cogs']);
        $this->assertEquals(350000.00, $summary['gross_profit']); // 500k - 150k = 350k (Asset ignored!)
        $this->assertEquals(350000.00, $summary['net_profit']);    // 500k - 150k = 350k
    }

    // -------------------------------------------------------------------------
    // 4. Income Filters
    // -------------------------------------------------------------------------

    public function test_reports_income_filters_by_date_account_category_and_status(): void
    {
        $user = User::factory()->create();
        $acc1 = Account::create(['name' => 'BCA', 'account_type' => 'bank', 'opening_balance' => 1000000, 'is_active' => true]);
        $acc2 = Account::create(['name' => 'Cash', 'account_type' => 'cash', 'opening_balance' => 500000, 'is_active' => true]);
        $item = MenuItem::create(['name' => 'Tart', 'category' => 'Pastry', 'current_price' => 100000, 'is_active' => true]);

        // Income 1: Paid BCA (2026-07-15)
        app(IncomeCalculationService::class)->createIncomeTransaction([
            'transaction_date'    => '2026-07-15',
            'menu_item_id'        => $item->id,
            'quantity'            => '1',
            'discount_percentage' => '0',
            'category'            => 'Pastry',
            'account_id'          => $acc1->id,
            'payment_status'      => 'paid',
        ], $user);

        // Income 2: Unpaid Cash (2026-08-10)
        app(IncomeCalculationService::class)->createIncomeTransaction([
            'transaction_date'    => '2026-08-10',
            'menu_item_id'        => $item->id,
            'quantity'            => '2',
            'discount_percentage' => '0',
            'category'            => 'Pastry',
            'account_id'          => $acc2->id,
            'payment_status'      => 'unpaid',
        ], $user);

        $reportService = app(\App\Services\FinancialReportService::class);

        // Filter by Date (July)
        $julyResults = $reportService->getIncomeQuery(['from' => '2026-07-01', 'to' => '2026-07-31'])->get();
        $this->assertCount(1, $julyResults);
        $this->assertEquals(100000.00, (float) $julyResults[0]->total_amount);

        // Filter by Payment Status (Unpaid)
        $unpaidResults = $reportService->getIncomeQuery(['payment_status' => 'unpaid'])->get();
        $this->assertCount(1, $unpaidResults);
        $this->assertEquals('unpaid', $unpaidResults[0]->payment_status);

        // Filter by Account (BCA)
        $bcaResults = $reportService->getIncomeQuery(['account_id' => $acc1->id])->get();
        $this->assertCount(1, $bcaResults);
        $this->assertEquals($acc1->id, $bcaResults[0]->account_id);
    }

    // -------------------------------------------------------------------------
    // 5. Expense Filters & Asset Category
    // -------------------------------------------------------------------------

    public function test_reports_expense_filters_by_category_including_asset_and_statuses(): void
    {
        $user = User::factory()->create();
        $account = Account::create(['name' => 'Bank', 'account_type' => 'bank', 'opening_balance' => 2000000, 'is_active' => true]);

        // Operational Paid Expense
        app(ExpenseCalculationService::class)->createExpenseTransaction([
            'transaction_date' => '2026-08-01',
            'transaction_name' => 'Paper Bags',
            'expense_category' => 'Operational',
            'amount'           => '50000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ], $user);

        // Asset Paid Expense
        app(ExpenseCalculationService::class)->createExpenseTransaction([
            'transaction_date' => '2026-08-02',
            'transaction_name' => 'Refrigerator',
            'expense_category' => 'Asset',
            'amount'           => '3000000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ], $user);

        // Unpaid Operational Expense
        app(ExpenseCalculationService::class)->createExpenseTransaction([
            'transaction_date' => '2026-08-03',
            'transaction_name' => 'Pending Rent',
            'expense_category' => 'Rent',
            'amount'           => '1000000',
            'account_id'       => $account->id,
            'payment_status'   => 'unpaid',
        ], $user);

        $reportService = app(\App\Services\FinancialReportService::class);

        // Filter Category = Asset
        $assetResults = $reportService->getExpenseQuery(['category' => 'Asset'])->get();
        $this->assertCount(1, $assetResults);
        $this->assertEquals('Refrigerator', $assetResults[0]->transaction_name);
        $this->assertEquals('Asset', $assetResults[0]->expense_category);

        // Filter Payment Status = Paid
        $paidResults = $reportService->getExpenseQuery(['payment_status' => 'paid'])->get();
        $this->assertCount(2, $paidResults);

        // Filter Payment Status = Unpaid
        $unpaidResults = $reportService->getExpenseQuery(['payment_status' => 'unpaid'])->get();
        $this->assertCount(1, $unpaidResults);
        $this->assertEquals('Pending Rent', $unpaidResults[0]->transaction_name);
    }

    // -------------------------------------------------------------------------
    // 6. Transfer Filters
    // -------------------------------------------------------------------------

    public function test_reports_transfer_filters_by_from_to_accounts_and_statuses(): void
    {
        $user = User::factory()->create();
        $acc1 = Account::create(['name' => 'BCA', 'account_type' => 'bank', 'opening_balance' => 5000000, 'is_active' => true]);
        $acc2 = Account::create(['name' => 'Cash', 'account_type' => 'cash', 'opening_balance' => 1000000, 'is_active' => true]);

        $transfer = app(TransferService::class)->createTransfer([
            'transfer_date'   => '2026-08-05',
            'from_account_id' => $acc1->id,
            'to_account_id'   => $acc2->id,
            'amount'          => '500000',
            'description'     => 'Vault to register',
        ], $user);

        $reportService = app(\App\Services\FinancialReportService::class);

        $results = $reportService->getTransferQuery(['from_account_id' => $acc1->id])->get();
        $this->assertCount(1, $results);
        $this->assertEquals('500000.00', $results[0]->amount);
        $this->assertEquals($acc1->id, $results[0]->from_account_id);
        $this->assertEquals($acc2->id, $results[0]->to_account_id);

        // Cancel transfer and verify filter by status
        app(TransferService::class)->cancelTransfer($transfer, $user);

        $activeResults = $reportService->getTransferQuery(['record_status' => 'active'])->get();
        $this->assertCount(0, $activeResults);

        $cancelledResults = $reportService->getTransferQuery(['record_status' => 'cancelled'])->get();
        $this->assertCount(1, $cancelledResults);
    }

    // -------------------------------------------------------------------------
    // 7. CSV Export Verification
    // -------------------------------------------------------------------------

    public function test_csv_export_summary_streams_correct_bom_headers_and_metrics(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/reports/export/summary');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('summary_report_', $response->headers->get('Content-Disposition'));

        $content = $response->streamedContent();

        // Check UTF-8 BOM
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Kuna Patisserie - Financial Summary Report', $content);
        $this->assertStringContainsString('Total Revenue', $content);
        $this->assertStringContainsString('Net Profit', $content);
        $this->assertStringContainsString('Excluded (Asset)', $content);
    }

    public function test_csv_export_expense_streams_profit_eligible_asset_formatting(): void
    {
        $user = User::factory()->create();
        $account = Account::create(['name' => 'Bank', 'account_type' => 'bank', 'opening_balance' => 2000000, 'is_active' => true]);

        app(ExpenseCalculationService::class)->createExpenseTransaction([
            'transaction_date' => '2026-08-01',
            'transaction_name' => 'Display Cabinet',
            'expense_category' => 'Asset',
            'amount'           => '1200000',
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
        ], $user);

        $response = $this->actingAs($user)->get('/reports/export/expense');

        $response->assertStatus(200);
        $content = $response->streamedContent();

        $this->assertStringContainsString('Transaction ID', $content);
        $this->assertStringContainsString('Display Cabinet', $content);
        $this->assertStringContainsString('Asset', $content);
        $this->assertStringContainsString('1200000.00', $content);
        $this->assertStringContainsString('No (Asset)', $content);
    }

    public function test_csv_export_empty_period_produces_valid_headers(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/reports/export/income?period=custom&from=2030-01-01&to=2030-01-31');

        $response->assertStatus(200);
        $content = $response->streamedContent();

        $this->assertStringContainsString('Transaction ID', $content);
        $this->assertStringContainsString('Transaction Date', $content);
        $this->assertStringContainsString('Menu Item', $content);
    }

    public function test_csv_export_rejects_unsupported_export_type_with_404(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/reports/export/unsupported_type');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // 8. Custom Date Range Validation Safety
    // -------------------------------------------------------------------------

    public function test_custom_date_range_validation_from_greater_than_to_does_not_cause_500(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/reports?period=custom&from=2026-08-25&to=2026-08-10');

        $response->assertStatus(200);
        $response->assertSee('The &quot;from&quot; date must be earlier than or equal to the &quot;to&quot; date.', false);
    }
}
