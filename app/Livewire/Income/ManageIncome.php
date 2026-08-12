<?php

namespace App\Livewire\Income;

use App\Models\Account;
use App\Models\IncomeTransaction;
use App\Models\MenuItem;
use App\Services\IncomeCalculationService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ManageIncome extends Component
{
    use WithPagination;

    // -------------------------------------------------------------------------
    // Form fields
    // -------------------------------------------------------------------------
    public string $transaction_date = '';
    public string $menu_item_id = '';
    public string $quantity = '';
    public string $discount_percentage = '0';
    public string $category = '';
    public string $description = '';
    public string $account_id = '';
    public string $payment_status = 'unpaid';

    // -------------------------------------------------------------------------
    // UI state
    // -------------------------------------------------------------------------
    public bool $isModalOpen = false;
    public ?int $editingIncomeId = null;

    // -------------------------------------------------------------------------
    // URL-bound search/filter state
    // -------------------------------------------------------------------------
    #[Url(as: 'search')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = 'all';  // all | paid | unpaid

    #[Url(as: 'record')]
    public string $recordFilter = 'active';  // active | cancelled | all

    // -------------------------------------------------------------------------
    // Validation rules
    // -------------------------------------------------------------------------
    public function rules(): array
    {
        return [
            'transaction_date'    => 'required|date',
            'menu_item_id'        => 'required|exists:menu_items,id',
            'quantity'            => 'required|numeric|min:0.01',
            'discount_percentage' => 'required|numeric|min:0|max:100',
            'category'            => 'required|string|max:30',
            'description'         => 'nullable|string|max:5000',
            'account_id'          => [
                Rule::requiredIf($this->payment_status === 'paid'),
                'nullable',
                'exists:accounts,id',
            ],
            'payment_status'      => ['required', Rule::in(['unpaid', 'paid'])],
        ];
    }

    // -------------------------------------------------------------------------
    // Lifecycle
    // -------------------------------------------------------------------------
    public function mount(): void
    {
        $this->transaction_date = now()->format('Y-m-d');
    }

    // -------------------------------------------------------------------------
    // Reactive updates
    // -------------------------------------------------------------------------
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedRecordFilter(): void
    {
        $this->resetPage();
    }

    /**
     * When a menu item is selected, auto-populate category from the item.
     */
    public function updatedMenuItemId(): void
    {
        if ($this->menu_item_id) {
            $item = MenuItem::find((int) $this->menu_item_id);
            if ($item) {
                $this->category = $item->category;
            }
        }
    }

    // -------------------------------------------------------------------------
    // Modal control
    // -------------------------------------------------------------------------
    public function createIncome(): void
    {
        $this->resetForm();
        $this->isModalOpen = true;
    }

    public function closeModal(): void
    {
        $this->isModalOpen = false;
        $this->resetForm();
    }

    // -------------------------------------------------------------------------
    // Form save
    // -------------------------------------------------------------------------
    public function saveIncome(): void
    {
        $this->validate();

        // Additional business-rule validation: active account required for paid income.
        if ($this->payment_status === 'paid' && $this->account_id) {
            $account = Account::find((int) $this->account_id);
            if (! $account || ! $account->is_active) {
                $this->addError('account_id', 'The selected account must be active for paid income.');
                return;
            }
        }

        $service = app(IncomeCalculationService::class);

        $service->createIncomeTransaction([
            'transaction_date'    => $this->transaction_date,
            'menu_item_id'        => $this->menu_item_id,
            'quantity'            => $this->quantity,
            'discount_percentage' => $this->discount_percentage,
            'category'            => $this->category,
            'description'         => $this->description ?: null,
            'account_id'          => $this->account_id ?: null,
            'payment_status'      => $this->payment_status,
        ], auth()->user());

        $this->isModalOpen = false;
        $this->resetForm();
        session()->flash('success', 'Income transaction recorded successfully.');
    }

    // -------------------------------------------------------------------------
    // Cancellation
    // -------------------------------------------------------------------------
    public function cancelIncome(int $id): void
    {
        $income = IncomeTransaction::findOrFail($id);

        if ($income->isCancelled()) {
            session()->flash('error', 'This transaction is already cancelled.');
            return;
        }

        $service = app(IncomeCalculationService::class);
        $service->cancelIncomeTransaction($income);

        session()->flash('success', 'Income transaction cancelled. Record preserved for audit history.');
    }

    // -------------------------------------------------------------------------
    // Filter helpers
    // -------------------------------------------------------------------------
    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        $this->resetPage();
    }

    public function setRecordFilter(string $record): void
    {
        $this->recordFilter = $record;
        $this->resetPage();
    }

    // -------------------------------------------------------------------------
    // Form reset
    // -------------------------------------------------------------------------
    public function resetForm(): void
    {
        $this->editingIncomeId     = null;
        $this->transaction_date    = now()->format('Y-m-d');
        $this->menu_item_id        = '';
        $this->quantity            = '';
        $this->discount_percentage = '0';
        $this->category            = '';
        $this->description         = '';
        $this->account_id          = '';
        $this->payment_status      = 'unpaid';
        $this->resetValidation();
    }

    // -------------------------------------------------------------------------
    // Render
    // -------------------------------------------------------------------------
    public function render()
    {
        $query = IncomeTransaction::with(['menuItem', 'account']);

        // Search: match against transaction_id prefix, menu item name, category, description.
        if ($this->search !== '') {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('transaction_id', 'like', $search . '%')
                  ->orWhere('category', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%')
                  ->orWhereHas('menuItem', fn ($mq) => $mq->where('name', 'like', '%' . $search . '%'));
            });
        }

        // Payment status filter.
        if ($this->statusFilter === 'paid') {
            $query->where('payment_status', 'paid');
        } elseif ($this->statusFilter === 'unpaid') {
            $query->where('payment_status', 'unpaid');
        }

        // Record status filter.
        if ($this->recordFilter === 'active') {
            $query->where('record_status', 'active');
        } elseif ($this->recordFilter === 'cancelled') {
            $query->where('record_status', 'cancelled');
        }

        $incomeTransactions = $query->orderBy('transaction_date', 'desc')
                                    ->orderBy('id', 'desc')
                                    ->paginate(15);

        $activeMenuItems  = MenuItem::where('is_active', true)->orderBy('name')->get();
        $activeAccounts   = Account::where('is_active', true)->orderBy('name')->get();

        return view('livewire.income.manage-income', [
            'incomeTransactions' => $incomeTransactions,
            'activeMenuItems'    => $activeMenuItems,
            'activeAccounts'     => $activeAccounts,
        ]);
    }
}
