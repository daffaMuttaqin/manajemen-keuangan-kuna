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
}
