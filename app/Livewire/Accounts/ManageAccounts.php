<?php

namespace App\Livewire\Accounts;

use App\Models\Account;
use App\Services\AccountBalanceService;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Attributes\Layout;

#[Layout('layouts.app')]
class ManageAccounts extends Component
{
    public $accounts = [];
    
    public $name = '';
    public $account_type = 'cash';
    public $opening_balance = '';
    public $is_active = true;
    
    public $editingAccountId = null;
    public $isModalOpen = false;

    public function mount()
    {
        $this->loadAccounts();
    }

    public function loadAccounts()
    {
        $this->accounts = Account::all();
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'account_type' => ['required', 'string', Rule::in(['cash', 'bank', 'other'])],
            'opening_balance' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ];
    }

    public function createAccount()
    {
        $this->resetForm();
        $this->isModalOpen = true;
    }

    public function editAccount($id)
    {
        $this->resetValidation();
        $account = Account::findOrFail($id);
        
        $this->editingAccountId = $account->id;
        $this->name = $account->name;
        $this->account_type = $account->account_type;
        $this->opening_balance = $account->opening_balance;
        $this->is_active = $account->is_active;
        
        $this->isModalOpen = true;
    }

    public function saveAccount()
    {
        $this->validate();

        if ($this->editingAccountId) {
            $account = Account::findOrFail($this->editingAccountId);
            $account->update([
                'name' => $this->name,
                'account_type' => $this->account_type,
                'opening_balance' => $this->opening_balance,
                'is_active' => $this->is_active,
            ]);
        } else {
            Account::create([
                'name' => $this->name,
                'account_type' => $this->account_type,
                'opening_balance' => $this->opening_balance,
                'is_active' => $this->is_active,
            ]);
        }

        $this->isModalOpen = false;
        $this->resetForm();
        $this->loadAccounts();
    }

    public function toggleActiveStatus($id)
    {
        $account = Account::findOrFail($id);
        $account->update(['is_active' => !$account->is_active]);
        $this->loadAccounts();
    }

    public function resetForm()
    {
        $this->editingAccountId = null;
        $this->name = '';
        $this->account_type = 'cash';
        $this->opening_balance = '';
        $this->is_active = true;
        $this->resetValidation();
    }

    public function render(AccountBalanceService $balanceService)
    {
        return view('livewire.accounts.manage-accounts', [
            'balanceService' => $balanceService
        ]);
    }
}
