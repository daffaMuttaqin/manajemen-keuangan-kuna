<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ExpenseTransaction;
use App\Models\IncomeTransaction;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PeriodFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_income_period_filter_updates_listing_and_total_card(): void
    {
        $user = User::factory()->create();
        $account = Account::create(['name' => 'Bank Account', 'account_type' => 'bank', 'opening_balance' => 0, 'is_active' => true]);
        $item = MenuItem::create(['name' => 'Cake', 'category' => 'Bakery', 'current_price' => 50000, 'is_active' => true]);

        // Income 1: Today (This Month & This Year) - 100,000
        IncomeTransaction::create([
            'transaction_id'      => 'INC-THIS-MONTH',
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => 2,
            'unit_price'          => 50000,
            'gross_amount'        => 100000,
            'subtotal'            => 100000,
            'discount_percentage' => 0,
            'discount_amount'     => 0,
            'total_amount'        => 100000,
            'category'            => 'Bakery',
            'account_id'          => $account->id,
            'payment_status'      => 'paid',
            'record_status'       => 'active',
            'created_by'          => $user->id,
        ]);

        // Income 2: Last Month - 200,000
        IncomeTransaction::create([
            'transaction_id'      => 'INC-LAST-MONTH',
            'transaction_date'    => now()->subMonth()->startOfMonth()->format('Y-m-d'),
            'menu_item_id'        => $item->id,
            'quantity'            => 4,
            'unit_price'          => 50000,
            'gross_amount'        => 200000,
            'subtotal'            => 200000,
            'discount_percentage' => 0,
            'discount_amount'     => 0,
            'total_amount'        => 200000,
            'category'            => 'Bakery',
            'account_id'          => $account->id,
            'payment_status'      => 'paid',
            'record_status'       => 'active',
            'created_by'          => $user->id,
        ]);

        $this->actingAs($user);

        // Test All-Time: Total = 300,000
        Livewire::test(\App\Livewire\Income\ManageIncome::class)
            ->call('setPresetPeriod', 'all_time')
            ->assertSee('Rp 300.000')
            ->assertSee('INC-THIS')
            ->assertSee('INC-LAST');

        // Test This Month: Total = 100,000
        Livewire::test(\App\Livewire\Income\ManageIncome::class)
            ->call('setPresetPeriod', 'this_month')
            ->assertSee('Rp 100.000')
            ->assertSee('INC-THIS')
            ->assertDontSee('INC-LAST');

        // Test Last Month: Total = 200,000
        Livewire::test(\App\Livewire\Income\ManageIncome::class)
            ->call('setPresetPeriod', 'last_month')
            ->assertSee('Rp 200.000')
            ->assertSee('INC-LAST')
            ->assertDontSee('INC-THIS');
    }

    public function test_expense_period_filter_updates_listing_and_total_card(): void
    {
        $user = User::factory()->create();
        $account = Account::create(['name' => 'Bank Account', 'account_type' => 'bank', 'opening_balance' => 0, 'is_active' => true]);

        // Expense 1: Today - 50,000
        ExpenseTransaction::create([
            'transaction_id'   => 'EXP-THIS-MONTH',
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_name' => 'Raw Materials',
            'expense_category' => 'COGS / Cake Production',
            'amount'           => 50000,
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
            'record_status'    => 'active',
            'created_by'       => $user->id,
        ]);

        // Expense 2: Last Month - 150,000
        ExpenseTransaction::create([
            'transaction_id'   => 'EXP-LAST-MONTH',
            'transaction_date' => now()->subMonth()->startOfMonth()->format('Y-m-d'),
            'transaction_name' => 'Rent Payment',
            'expense_category' => 'Rent',
            'amount'           => 150000,
            'account_id'       => $account->id,
            'payment_status'   => 'paid',
            'record_status'    => 'active',
            'created_by'       => $user->id,
        ]);

        $this->actingAs($user);

        // All-Time: Total = 200,000
        Livewire::test(\App\Livewire\Expense\ManageExpense::class)
            ->call('setPresetPeriod', 'all_time')
            ->assertSee('Rp 200.000')
            ->assertSee('Raw Materials')
            ->assertSee('Rent Payment');

        // This Month: Total = 50,000
        Livewire::test(\App\Livewire\Expense\ManageExpense::class)
            ->call('setPresetPeriod', 'this_month')
            ->assertSee('Rp 50.000')
            ->assertSee('Raw Materials')
            ->assertDontSee('Rent Payment');
    }
}
