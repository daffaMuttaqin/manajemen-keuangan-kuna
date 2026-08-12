<?php

namespace App\Livewire\Menu;

use App\Models\MenuItem;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;

#[Layout('layouts.app')]
class ManageMenu extends Component
{
    use WithPagination;

    public $name = '';
    public $category = '';
    public $current_price = '';
    public $is_active = true;

    #[Url(as: 'search')]
    public $search = '';

    #[Url(as: 'status')]
    public $statusFilter = 'all';

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

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
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
        $this->is_active = (bool) $menuItem->is_active;

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

    public function setStatusFilter($status)
    {
        $this->statusFilter = $status;
        $this->resetPage();
    }

    public function render()
    {
        $query = MenuItem::query();

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('category', 'like', '%' . $this->search . '%')
                  ->orWhere('id', '=', $this->search);
            });
        }

        if ($this->statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($this->statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        $menuItems = $query->orderBy('id', 'desc')->paginate(10);

        return view('livewire.menu.manage-menu', [
            'menuItems' => $menuItems,
        ]);
    }
}
