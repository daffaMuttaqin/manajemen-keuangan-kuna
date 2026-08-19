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
    public string $sales_channel = 'Cafe';
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

    public string $menuItemSearch = '';

    #[Url(as: 'period')]
    public string $period = 'this_month';

    #[Url(as: 'from')]
    public ?string $from = null;

    #[Url(as: 'to')]
    public ?string $to = null;

    public function setPresetPeriod(string $preset): void
    {
        $this->period = $preset;
        $this->from   = null;
        $this->to     = null;
        $this->resetPage();
    }

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
            'sales_channel'       => ['required', Rule::in(['Cafe', 'Online', 'Reseller', 'Other'])],
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

    public function updatedMenuItemSearch(): void
    {
        // No pagination reset needed for modal menu search
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

    public function editIncome(int $id): void
    {
        $this->resetForm();
        $income = IncomeTransaction::findOrFail($id);

        if ($income->isCancelled()) {
            session()->flash('error', 'Cannot edit a cancelled income transaction.');
            return;
        }

        $this->editingIncomeId     = $income->id;
        $this->transaction_date    = $income->transaction_date->format('Y-m-d');
        $this->menu_item_id        = (string) $income->menu_item_id;
        $this->quantity            = (string) $income->quantity;
        $this->discount_percentage = (string) $income->discount_percentage;
        $this->category            = $income->category;
        $this->sales_channel       = $income->sales_channel ?? 'Cafe';
        $this->description         = $income->description ?? '';
        $this->account_id          = $income->account_id ? (string) $income->account_id : '';
        $this->payment_status      = $income->payment_status;

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

        try {
            if ($this->editingIncomeId) {
                $income = IncomeTransaction::findOrFail($this->editingIncomeId);
                $service->updateIncomeTransaction($income, [
                    'transaction_date'    => $this->transaction_date,
                    'menu_item_id'        => $this->menu_item_id,
                    'quantity'            => $this->quantity,
                    'discount_percentage' => $this->discount_percentage,
                    'category'            => $this->category,
                    'sales_channel'       => $this->sales_channel,
                    'description'         => $this->description ?: null,
                    'account_id'          => $this->account_id ?: null,
                    'payment_status'      => $this->payment_status,
                ]);
                session()->flash('success', 'Income transaction updated successfully.');
            } else {
                $service->createIncomeTransaction([
                    'transaction_date'    => $this->transaction_date,
                    'menu_item_id'        => $this->menu_item_id,
                    'quantity'            => $this->quantity,
                    'discount_percentage' => $this->discount_percentage,
                    'category'            => $this->category,
                    'sales_channel'       => $this->sales_channel,
                    'description'         => $this->description ?: null,
                    'account_id'          => $this->account_id ?: null,
                    'payment_status'      => $this->payment_status,
                ], auth()->user());
                session()->flash('success', 'Income transaction recorded successfully.');
            }
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
            return;
        }

        $this->isModalOpen = false;
        $this->resetForm();
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
    // Payment Confirmation (Phase 5)
    // -------------------------------------------------------------------------
    public bool $isConfirmModalOpen = false;
    public ?int $confirmingIncomeId = null;
    public string $confirm_account_id = '';

    public function openConfirmModal(int $id): void
    {
        $income = IncomeTransaction::findOrFail($id);

        if ($income->isCancelled()) {
            session()->flash('error', 'Cannot confirm payment for a cancelled transaction.');
            return;
        }

        if ($income->isPaid()) {
            session()->flash('error', 'This transaction is already paid.');
            return;
        }

        $this->confirmingIncomeId = $income->id;
        $this->confirm_account_id = $income->account_id ? (string) $income->account_id : '';
        $this->isConfirmModalOpen = true;
    }

    public function closeConfirmModal(): void
    {
        $this->isConfirmModalOpen = false;
        $this->confirmingIncomeId = null;
        $this->confirm_account_id = '';
        $this->resetValidation();
    }

    public function confirmPayment(): void
    {
        if (! $this->confirmingIncomeId) {
            return;
        }

        $this->validate([
            'confirm_account_id' => 'required|exists:accounts,id',
        ]);

        $account = Account::find((int) $this->confirm_account_id);
        if (! $account || ! $account->is_active) {
            $this->addError('confirm_account_id', 'The selected account must be active for payment confirmation.');
            return;
        }

        $income = IncomeTransaction::findOrFail($this->confirmingIncomeId);

        $service = app(\App\Services\PaymentConfirmationService::class);
        $service->confirmIncomePayment($income, (int) $this->confirm_account_id, auth()->user());

        $this->closeConfirmModal();
        session()->flash('success', 'Payment confirmed successfully. Account balance updated.');
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
        $this->sales_channel       = 'Cafe';
        $this->description         = '';
        $this->account_id          = '';
        $this->payment_status      = 'unpaid';
        $this->menuItemSearch      = '';
        $this->resetValidation();
    }

    // -------------------------------------------------------------------------
    // Render
    // -------------------------------------------------------------------------
    public function render()
    {
        $dateRange    = app(\App\Services\FinancialReportService::class)->resolveDateRange($this->period, $this->from, $this->to);
        $fromDate     = $dateRange['fromDate'];
        $toDate       = $dateRange['toDate'];
        $activePeriod = $dateRange['period'];

        $query = IncomeTransaction::with(['menuItem', 'account']);

        // Date period filter
        if ($fromDate !== null) {
            $query->where('transaction_date', '>=', $fromDate);
        }
        if ($toDate !== null) {
            $query->where('transaction_date', '<=', $toDate);
        }

        // Search: match against transaction_id prefix, menu item name, category, sales channel, description.
        if ($this->search !== '') {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('transaction_id', 'like', $search . '%')
                  ->orWhere('category', 'like', '%' . $search . '%')
                  ->orWhere('sales_channel', 'like', '%' . $search . '%')
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
                                    ->paginate(8);

        $activeMenuItems = MenuItem::where('is_active', true)->orderBy('name')->get();
        $filteredMenuItems = $activeMenuItems;
        if (trim($this->menuItemSearch) !== '') {
            $searchTerm = strtolower(trim($this->menuItemSearch));
            $filteredMenuItems = $activeMenuItems->filter(function ($item) use ($searchTerm) {
                if ($this->menu_item_id && (int) $item->id === (int) $this->menu_item_id) {
                    return true;
                }
                return str_contains(strtolower($item->name), $searchTerm) || str_contains(strtolower($item->category), $searchTerm);
            })->values();
        }

        $activeAccounts = Account::where('is_active', true)->orderBy('name')->get();
        // Total Incomes includes all active income (paid + unpaid) matching calculateTotalOmset
        $totalIncome  = (float) app(IncomeCalculationService::class)->calculateTotalOmset($fromDate, $toDate);
        // Unpaid Incomes includes active unpaid income
        $unpaidIncome = (float) app(IncomeCalculationService::class)->calculateUnpaidRevenue($fromDate, $toDate);

        return view('livewire.income.manage-income', [
            'incomeTransactions' => $incomeTransactions,
            'activeMenuItems'    => $activeMenuItems,
            'filteredMenuItems'  => $filteredMenuItems,
            'activeAccounts'     => $activeAccounts,
            'totalIncome'        => $totalIncome,
            'unpaidIncome'       => $unpaidIncome,
            'activePeriod'       => $activePeriod,
            'fromDate'           => $fromDate,
            'toDate'             => $toDate,
        ]);
    }
}
