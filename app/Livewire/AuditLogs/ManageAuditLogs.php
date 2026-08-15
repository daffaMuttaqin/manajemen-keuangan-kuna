<?php

namespace App\Livewire\AuditLogs;

use App\Models\AuditLog;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ManageAuditLogs extends Component
{
    use WithPagination;

    #[Url(as: 'date_from')]
    public string $date_from = '';

    #[Url(as: 'date_to')]
    public string $date_to = '';

    #[Url(as: 'user')]
    public string $user_id = '';

    #[Url(as: 'action')]
    public string $actionFilter = '';

    #[Url(as: 'entity')]
    public string $auditable_type = '';

    public ?int $selectedLogId = null;
    public bool $isDetailModalOpen = false;

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedUserId(): void
    {
        $this->resetPage();
    }

    public function updatedActionFilter(): void
    {
        $this->resetPage();
    }

    public function updatedAuditableType(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->date_from      = '';
        $this->date_to        = '';
        $this->user_id        = '';
        $this->actionFilter   = '';
        $this->auditable_type = '';
        $this->resetPage();
    }

    public function viewDetails(int $id): void
    {
        $this->selectedLogId = $id;
        $this->isDetailModalOpen = true;
    }

    public function closeDetailModal(): void
    {
        $this->isDetailModalOpen = false;
        $this->selectedLogId = null;
    }

    public function render()
    {
        $query = AuditLog::with(['user', 'auditable']);

        if ($this->date_from !== '') {
            $query->whereDate('created_at', '>=', $this->date_from);
        }

        if ($this->date_to !== '') {
            $query->whereDate('created_at', '<=', $this->date_to);
        }

        if ($this->user_id !== '') {
            $query->where('user_id', (int) $this->user_id);
        }

        if ($this->actionFilter !== '') {
            $query->where('action', $this->actionFilter);
        }

        if ($this->auditable_type !== '') {
            $query->where('auditable_type', $this->auditable_type);
        }

        $auditLogs = $query->orderBy('id', 'desc')->paginate(15);
        $users = User::orderBy('name')->get();
        $selectedLog = $this->selectedLogId ? AuditLog::with(['user', 'auditable'])->find($this->selectedLogId) : null;

        $availableActions = [
            'account_created',
            'account_updated',
            'account_activated',
            'account_deactivated',
            'menu_item_created',
            'menu_item_updated',
            'menu_item_activated',
            'menu_item_deactivated',
            'income_created',
            'income_updated',
            'income_cancelled',
            'income_payment_confirmed',
            'expense_created',
            'expense_updated',
            'expense_cancelled',
            'expense_payment_confirmed',
            'transfer_created',
            'transfer_cancelled',
        ];

        $availableEntityTypes = [
            'App\Models\Account'            => 'Account',
            'App\Models\MenuItem'           => 'Menu Item',
            'App\Models\IncomeTransaction'  => 'Income Transaction',
            'App\Models\ExpenseTransaction' => 'Expense Transaction',
            'App\Models\Transfer'           => 'Transfer',
        ];

        return view('livewire.audit-logs.manage-audit-logs', [
            'auditLogs'            => $auditLogs,
            'users'                => $users,
            'selectedLog'          => $selectedLog,
            'availableActions'     => $availableActions,
            'availableEntityTypes' => $availableEntityTypes,
        ]);
    }
}
