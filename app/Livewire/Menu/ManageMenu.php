<?php

namespace App\Livewire\Menu;

use App\Models\MenuItem;
use Livewire\Component;
use Livewire\Attributes\Layout;

#[Layout('layouts.app')]
class ManageMenu extends Component
{
    public $name = '';
    public $category = '';
    public $current_price = '';
    public $is_active = true;

    public $search = '';
    public $editingMenuItemId = null;
    public $isModalOpen = false;

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'current_price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ];
    }

    public function createMenuItem()
    {
        $this->resetForm();
        $this->isModalOpen = true;
    }

    public function editMenuItem($id)
    {
        $this->resetValidation();
        $menuItem = MenuItem::findOrFail($id);

        $this->editingMenuItemId = $menuItem->id;
        $this->name = $menuItem->name;
        $this->category = $menuItem->category;
        $this->current_price = $menuItem->current_price;
        $this->is_active = $menuItem->is_active;

        $this->isModalOpen = true;
    }

    public function saveMenuItem()
    {
        $this->validate();

        if ($this->editingMenuItemId) {
            $menuItem = MenuItem::findOrFail($this->editingMenuItemId);
            $menuItem->update([
                'name' => $this->name,
                'category' => $this->category,
                'current_price' => $this->current_price,
                'is_active' => $this->is_active,
            ]);
        } else {
            MenuItem::create([
                'name' => $this->name,
                'category' => $this->category,
                'current_price' => $this->current_price,
                'is_active' => $this->is_active,
            ]);
        }

        $this->isModalOpen = false;
        $this->resetForm();
    }

    public function toggleActiveStatus($id)
    {
        $menuItem = MenuItem::findOrFail($id);
        $menuItem->update(['is_active' => !$menuItem->is_active]);
    }

    public function resetForm()
    {
        $this->editingMenuItemId = null;
        $this->name = '';
        $this->category = '';
        $this->current_price = '';
        $this->is_active = true;
        $this->resetValidation();
    }

    public function render()
    {
        $menuItems = MenuItem::query()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('category', 'like', '%' . $this->search . '%');
                });
            })
            ->get();

        return view('livewire.menu.manage-menu', [
            'menuItems' => $menuItems,
        ]);
    }
}
