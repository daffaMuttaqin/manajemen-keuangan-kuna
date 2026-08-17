<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\IncomeTransaction;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MenuSalesPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_items_display_sales_performance_metrics(): void
    {
        $user = User::factory()->create();
        $account = Account::create(['name' => 'Cash Account', 'account_type' => 'cash', 'opening_balance' => 1000000, 'is_active' => true]);
        
        $itemA = MenuItem::create(['name' => 'Croissant', 'category' => 'Pastry', 'current_price' => 25000, 'is_active' => true]);
        $itemB = MenuItem::create(['name' => 'Eclair', 'category' => 'Pastry', 'current_price' => 30000, 'is_active' => true]);

        // Income 1: Paid Active for Item A (Qty: 4, Total: 100,000)
        IncomeTransaction::create([
            'transaction_id'      => 'INC-2026-001',
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $itemA->id,
            'quantity'            => 4,
            'unit_price'          => 25000,
            'gross_amount'        => 100000,
            'subtotal'            => 100000,
            'discount_percentage' => 0,
            'discount_amount'     => 0,
            'total_amount'        => 100000,
            'category'            => 'Pastry',
            'account_id'          => $account->id,
            'payment_status'      => 'paid',
            'record_status'       => 'active',
            'created_by'          => $user->id,
        ]);

        // Income 2: Unpaid Active for Item A (Should NOT count)
        IncomeTransaction::create([
            'transaction_id'      => 'INC-2026-002',
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $itemA->id,
            'quantity'            => 10,
            'unit_price'          => 25000,
            'gross_amount'        => 250000,
            'subtotal'            => 250000,
            'discount_percentage' => 0,
            'discount_amount'     => 0,
            'total_amount'        => 250000,
            'category'            => 'Pastry',
            'account_id'          => null,
            'payment_status'      => 'unpaid',
            'record_status'       => 'active',
            'created_by'          => $user->id,
        ]);

        // Income 3: Paid Cancelled for Item B (Should NOT count)
        IncomeTransaction::create([
            'transaction_id'      => 'INC-2026-003',
            'transaction_date'    => now()->format('Y-m-d'),
            'menu_item_id'        => $itemB->id,
            'quantity'            => 5,
            'unit_price'          => 30000,
            'gross_amount'        => 150000,
            'subtotal'            => 150000,
            'discount_percentage' => 0,
            'discount_amount'     => 0,
            'total_amount'        => 150000,
            'category'            => 'Pastry',
            'account_id'          => $account->id,
            'payment_status'      => 'paid',
            'record_status'       => 'cancelled',
            'created_by'          => $user->id,
        ]);

        $this->actingAs($user);

        Livewire::test(\App\Livewire\Menu\ManageMenu::class)
            ->assertSee('Croissant')
            ->assertSee('Rp 25.000')
            ->assertSee('4') // Total Sold for Croissant
            ->assertSee('Rp 100.000') // Total Revenue for Croissant
            ->assertSee('0'); // Total Sold for Eclair (since item B transaction is cancelled)
    }
}
