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

        \Illuminate\Support\Facades\DB::transaction(function () {
            if ($this->editingMenuItemId) {
                $menuItem = MenuItem::findOrFail($this->editingMenuItemId);
                $beforeState = [
                    'name'          => $menuItem->name,
                    'category'      => $menuItem->category,
                    'current_price' => (string) $menuItem->current_price,
                    'is_active'     => (bool) $menuItem->is_active,
                ];

                $menuItem->update([
                    'name'          => $this->name,
                    'category'      => $this->category,
                    'current_price' => $this->current_price,
                    'is_active'     => $this->is_active,
                ]);

                $afterState = [
                    'name'          => $menuItem->name,
                    'category'      => $menuItem->category,
                    'current_price' => (string) $menuItem->current_price,
                    'is_active'     => (bool) $menuItem->is_active,
                ];

                app(\App\Services\AuditLogService::class)->record('menu_item_updated', $menuItem, [
                    'before' => $beforeState,
                    'after'  => $afterState,
                ], auth()->user());
            } else {
                $menuItem = MenuItem::create([
                    'name'          => $this->name,
                    'category'      => $this->category,
                    'current_price' => $this->current_price,
                    'is_active'     => $this->is_active,
                ]);

                app(\App\Services\AuditLogService::class)->record('menu_item_created', $menuItem, [
                    'new' => [
                        'name'          => $menuItem->name,
                        'category'      => $menuItem->category,
                        'current_price' => (string) $menuItem->current_price,
                        'is_active'     => (bool) $menuItem->is_active,
                    ],
                ], auth()->user());
            }
        });

        $this->isModalOpen = false;
        $this->resetForm();
    }

    public function toggleActiveStatus($id)
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($id) {
            $menuItem = MenuItem::findOrFail($id);
            $oldActive = (bool) $menuItem->is_active;
            $newActive = !$oldActive;
            $menuItem->update(['is_active' => $newActive]);

            $action = $newActive ? 'menu_item_activated' : 'menu_item_deactivated';
            app(\App\Services\AuditLogService::class)->record($action, $menuItem, [
                'before' => ['is_active' => $oldActive],
                'after'  => ['is_active' => $newActive],
            ], auth()->user());
        });
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
