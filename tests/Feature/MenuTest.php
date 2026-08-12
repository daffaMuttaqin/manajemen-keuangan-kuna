<?php

namespace Tests\Feature;

use App\Livewire\Menu\ManageMenu;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_access_menu_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/menu');

        $response->assertStatus(200);
        $response->assertSeeLivewire(ManageMenu::class);
    }

    public function test_menu_item_can_be_created(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(ManageMenu::class)
            ->set('name', 'Butter Croissant')
            ->set('category', 'Pastry')
            ->set('current_price', '25000')
            ->set('is_active', true)
            ->call('saveMenuItem');

        $this->assertDatabaseHas('menu_items', [
            'name' => 'Butter Croissant',
            'category' => 'Pastry',
            'current_price' => '25000.00',
            'is_active' => 1,
        ]);
    }

    public function test_required_fields_are_validated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(ManageMenu::class)
            ->set('name', '')
            ->set('category', '')
            ->set('current_price', '')
            ->call('saveMenuItem')
            ->assertHasErrors(['name', 'category', 'current_price']);
    }

    public function test_price_validation_rejects_invalid_values(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(ManageMenu::class)
            ->set('name', 'Butter Croissant')
            ->set('category', 'Pastry')
            ->set('current_price', '-5000')
            ->call('saveMenuItem')
            ->assertHasErrors(['current_price']);

        Livewire::test(ManageMenu::class)
            ->set('name', 'Butter Croissant')
            ->set('category', 'Pastry')
            ->set('current_price', 'invalid_price')
            ->call('saveMenuItem')
            ->assertHasErrors(['current_price']);
    }

    public function test_menu_item_can_be_updated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $item = MenuItem::create([
            'name' => 'Butter Croissant',
            'category' => 'Pastry',
            'current_price' => 25000,
            'is_active' => true,
        ]);

        Livewire::test(ManageMenu::class)
            ->call('editMenuItem', $item->id)
            ->set('name', 'Almond Croissant')
            ->set('current_price', '30000')
            ->call('saveMenuItem');

        $this->assertDatabaseHas('menu_items', [
            'id' => $item->id,
            'name' => 'Almond Croissant',
            'current_price' => '30000.00',
        ]);
    }

    public function test_menu_item_can_be_activated_or_deactivated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $item = MenuItem::create([
            'name' => 'Chocolate Cake',
            'category' => 'Cake',
            'current_price' => 50000,
            'is_active' => true,
        ]);

        Livewire::test(ManageMenu::class)
            ->call('toggleActiveStatus', $item->id);

        $this->assertDatabaseHas('menu_items', [
            'id' => $item->id,
            'is_active' => 0,
        ]);

        Livewire::test(ManageMenu::class)
            ->call('toggleActiveStatus', $item->id);

        $this->assertDatabaseHas('menu_items', [
            'id' => $item->id,
            'is_active' => 1,
        ]);
    }

    public function test_inactive_menu_item_remains_stored(): void
    {
        $item = MenuItem::create([
            'name' => 'Discontinued Tart',
            'category' => 'Pastry',
            'current_price' => 35000,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('menu_items', [
            'id' => $item->id,
            'is_active' => 0,
        ]);

        $this->assertCount(1, MenuItem::all());
    }

    public function test_inactive_menu_item_retains_its_current_price(): void
    {
        $item = MenuItem::create([
            'name' => 'Old Recipe Cake',
            'category' => 'Cake',
            'current_price' => 45000.50,
            'is_active' => false,
        ]);

        $fresh = MenuItem::find($item->id);
        $this->assertEquals(45000.50, $fresh->current_price);
        $this->assertFalse($fresh->is_active);
    }

    public function test_search_can_find_menu_items_by_name(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        MenuItem::create([
            'name' => 'Chocolate Mousse',
            'category' => 'Cake',
            'current_price' => 45000,
            'is_active' => true,
        ]);

        MenuItem::create([
            'name' => 'Butter Croissant',
            'category' => 'Pastry',
            'current_price' => 20000,
            'is_active' => true,
        ]);

        Livewire::test(ManageMenu::class)
            ->set('search', 'Mousse')
            ->assertSee('Chocolate Mousse')
            ->assertDontSee('Butter Croissant');
    }

    public function test_search_can_find_menu_items_by_category(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        MenuItem::create([
            'name' => 'Eclair',
            'category' => 'Pastry',
            'current_price' => 25000,
            'is_active' => true,
        ]);

        MenuItem::create([
            'name' => 'Espresso',
            'category' => 'Beverage',
            'current_price' => 18000,
            'is_active' => true,
        ]);

        Livewire::test(ManageMenu::class)
            ->set('search', 'Pastry')
            ->assertSee('Eclair')
            ->assertDontSee('Espresso');
    }
}
