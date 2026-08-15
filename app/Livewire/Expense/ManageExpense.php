<?php

namespace App\Livewire\Expense;

use App\Models\Account;
use App\Models\ExpenseTransaction;
use App\Services\ExpenseCalculationService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ManageExpense extends Component
{
    use WithPagination;

    // -------------------------------------------------------------------------
    // Form fields
    // -------------------------------------------------------------------------
    public string $transaction_date = '';
    public string $transaction_name = '';
    public string $expense_category = '';
    public string $description = '';
    public string $amount = '';
    public string $account_id = '';
    public string $payment_status = 'unpaid';

    // -------------------------------------------------------------------------
    // UI state
    // -------------------------------------------------------------------------
    public bool $isModalOpen = false;
    public ?int $editingExpenseId = null;

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
            'transaction_date'  => 'required|date',
            'transaction_name'  => 'required|string|max:150',
            'expense_category'  => ['required', 'string', Rule::in(ExpenseTransaction::CATEGORIES)],
            'description'       => 'nullable|string|max:5000',
            'amount'            => 'required|numeric|min:0.01',
            'account_id'        => [
                Rule::requiredIf($this->payment_status === 'paid'),
                'nullable',
                'exists:accounts,id',
            ],
            'payment_status'    => ['required', Rule::in(['unpaid', 'paid'])],
        ];
    }

    // -------------------------------------------------------------------------
    // Lifecycle
    // -------------------------------------------------------------------------
    public function mount(): void
    {
        $this->transaction_date = now()->format('Y-m-d');
        $this->transaction_name = '';
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

    // -------------------------------------------------------------------------
    // Modal control
    // -------------------------------------------------------------------------
    public function createExpense(): void
    {
        $this->resetForm();
        $this->isModalOpen = true;
    }

    public function editExpense(int $id): void
    {
        $this->resetForm();
        $expense = ExpenseTransaction::findOrFail($id);

        if ($expense->isCancelled()) {
            session()->flash('error', 'Cannot edit a cancelled expense transaction.');
            return;
        }

        $this->editingExpenseId = $expense->id;
        $this->transaction_date = $expense->transaction_date->format('Y-m-d');
        $this->transaction_name = $expense->transaction_name ?? '';
        $this->expense_category = $expense->expense_category;
        $this->description      = $expense->description ?? '';
        $this->amount           = (string) $expense->amount;
        $this->account_id       = $expense->account_id ? (string) $expense->account_id : '';
        $this->payment_status   = $expense->payment_status;

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
    public function saveExpense(): void
    {
        $this->validate();

        // Additional business-rule validation: active account required for paid expense.
        if ($this->payment_status === 'paid' && $this->account_id) {
            $account = Account::find((int) $this->account_id);
            if (! $account || ! $account->is_active) {
                $this->addError('account_id', 'The selected account must be active for paid expense.');
                return;
            }
        }

        $service = app(ExpenseCalculationService::class);

        try {
            if ($this->editingExpenseId) {
                $expense = ExpenseTransaction::findOrFail($this->editingExpenseId);
                $service->updateExpenseTransaction($expense, [
                    'transaction_date' => $this->transaction_date,
                    'transaction_name' => $this->transaction_name,
                    'expense_category' => $this->expense_category,
                    'description'      => $this->description ?: null,
                    'amount'           => $this->amount,
                    'account_id'       => $this->account_id ?: null,
                    'payment_status'   => $this->payment_status,
                ]);
                session()->flash('success', 'Expense transaction updated successfully.');
            } else {
                $service->createExpenseTransaction([
                    'transaction_date' => $this->transaction_date,
                    'transaction_name' => $this->transaction_name,
                    'expense_category' => $this->expense_category,
                    'description'      => $this->description ?: null,
                    'amount'           => $this->amount,
                    'account_id'       => $this->account_id ?: null,
                    'payment_status'   => $this->payment_status,
                ], auth()->user());
                session()->flash('success', 'Expense transaction recorded successfully.');
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
    public function cancelExpense(int $id): void
    {
        $expense = ExpenseTransaction::findOrFail($id);

        if ($expense->isCancelled()) {
            session()->flash('error', 'This transaction is already cancelled.');
            return;
        }

        $service = app(ExpenseCalculationService::class);
        $service->cancelExpenseTransaction($expense);

        session()->flash('success', 'Expense transaction cancelled. Record preserved for audit history.');
    }

    // -------------------------------------------------------------------------
    // Payment Confirmation (Phase 5)
    // -------------------------------------------------------------------------
    public bool $isConfirmModalOpen = false;
    public ?int $confirmingExpenseId = null;
    public string $confirm_account_id = '';

    public function openConfirmModal(int $id): void
    {
        $expense = ExpenseTransaction::findOrFail($id);

        if ($expense->isCancelled()) {
            session()->flash('error', 'Cannot confirm payment for a cancelled transaction.');
            return;
        }

        if ($expense->isPaid()) {
            session()->flash('error', 'This transaction is already paid.');
            return;
        }

        $this->confirmingExpenseId = $expense->id;
        $this->confirm_account_id  = $expense->account_id ? (string) $expense->account_id : '';
        $this->isConfirmModalOpen  = true;
    }

    public function closeConfirmModal(): void
    {
        $this->isConfirmModalOpen = false;
        $this->confirmingExpenseId = null;
        $this->confirm_account_id  = '';
        $this->resetValidation();
    }

    public function confirmPayment(): void
    {
        if (! $this->confirmingExpenseId) {
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

        $expense = ExpenseTransaction::findOrFail($this->confirmingExpenseId);

        $service = app(\App\Services\PaymentConfirmationService::class);
        $service->confirmExpensePayment($expense, (int) $this->confirm_account_id, auth()->user());

        $this->closeConfirmModal();
        session()->flash('success', 'Expense payment confirmed successfully. Account balance updated.');
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
        $this->editingExpenseId  = null;
        $this->transaction_date  = now()->format('Y-m-d');
        $this->transaction_name  = '';
        $this->expense_category  = '';
        $this->description       = '';
        $this->amount            = '';
        $this->account_id        = '';
        $this->payment_status    = 'unpaid';
        $this->resetValidation();
    }

    // -------------------------------------------------------------------------
    // Render
    // -------------------------------------------------------------------------
    public function render()
    {
        $query = ExpenseTransaction::with(['account']);

        // Search: match against transaction_id prefix, transaction_name, category, description.
        if ($this->search !== '') {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('transaction_id', 'like', $search . '%')
                  ->orWhere('transaction_name', 'like', '%' . $search . '%')
                  ->orWhere('expense_category', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%');
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

        $expenseTransactions = $query->orderBy('transaction_date', 'desc')
                                     ->orderBy('id', 'desc')
                                     ->paginate(15);

        $activeAccounts = Account::where('is_active', true)->orderBy('name')->get();
        $totalExpenses  = (float) app(ExpenseCalculationService::class)->calculateTotalExpenses();

        return view('livewire.expense.manage-expense', [
            'expenseTransactions'  => $expenseTransactions,
            'activeAccounts'       => $activeAccounts,
            'expenseCategories'    => ExpenseTransaction::CATEGORIES,
            'totalExpenses'        => $totalExpenses,
        ]);
    }
}
