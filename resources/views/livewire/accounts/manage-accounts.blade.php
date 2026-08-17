<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-outline-variant/60">
        <div>
            <h2 class="text-2xl font-bold text-on-surface">Account Management</h2>
            <p class="text-sm text-on-surface-variant mt-1">Manage cash, bank, and other financial accounts.</p>
        </div>
        <div>
            <button wire:click="createAccount" 
                    id="btn-create-account"
                    class="px-4 py-2 bg-primary text-on-primary font-semibold text-sm rounded hover:bg-primary-container transition flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">add</span>
                Add Account
            </button>
        </div>
    </div>

    <!-- Modal Form (Add / Edit Account) -->
    @if($isModalOpen)
        <div class="fixed inset-0 bg-black/75 flex items-center justify-center z-50 p-4"
             role="dialog"
             aria-modal="true"
             aria-labelledby="account-modal-title">
            <div class="bg-surface-container-low border border-outline-variant rounded-lg w-full max-w-md shadow-2xl flex flex-col">
                <!-- Modal Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-outline-variant/50 flex-shrink-0">
                    <h3 id="account-modal-title" class="text-base font-bold text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-[20px] text-primary">account_balance_wallet</span>
                        {{ $editingAccountId ? 'Edit Account' : 'Add New Account' }}
                    </h3>
                    <button type="button" wire:click="closeModal" class="text-on-surface-variant hover:text-on-surface p-1 rounded transition">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-6">
                    <form id="account-form" wire:submit.prevent="saveAccount" class="space-y-4">
                        <div>
                            <label for="account_name" class="block text-xs font-semibold text-on-surface-variant mb-1.5 uppercase tracking-wider">Account Name <span class="text-error">*</span></label>
                            <input type="text" id="account_name" wire:model="name" placeholder="e.g. Bank BCA Operasional"
                                   class="w-full h-10 bg-background border @error('name') border-error @else border-outline-variant @enderror text-on-surface rounded px-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                            @error('name') <p class="text-xs text-error mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="account_type" class="block text-xs font-semibold text-on-surface-variant mb-1.5 uppercase tracking-wider">Account Type <span class="text-error">*</span></label>
                            <select id="account_type" wire:model="account_type"
                                    class="w-full h-10 bg-background border @error('account_type') border-error @else border-outline-variant @enderror text-on-surface rounded px-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                                <option value="cash">Cash</option>
                                <option value="bank">Bank</option>
                                <option value="other">Other</option>
                            </select>
                            @error('account_type') <p class="text-xs text-error mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="opening_balance" class="block text-xs font-semibold text-on-surface-variant mb-1.5 uppercase tracking-wider">Opening Balance (Rp) <span class="text-error">*</span></label>
                            <input type="number" step="0.01" min="0" id="opening_balance" wire:model="opening_balance" placeholder="e.g. 5000000"
                                   class="w-full h-10 bg-background border @error('opening_balance') border-error @else border-outline-variant @enderror text-on-surface rounded px-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                            @error('opening_balance') <p class="text-xs text-error mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="flex items-center pt-2">
                            <input type="checkbox" id="is_active" wire:model="is_active" class="h-4 w-4 text-primary border-outline-variant bg-background rounded focus:ring-primary">
                            <label for="is_active" class="ml-2.5 text-sm text-on-surface font-medium">Active Account</label>
                            @error('is_active') <span class="text-xs text-error ml-2">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex justify-end gap-3 pt-4 border-t border-outline-variant/50 mt-6">
                            <button type="button" wire:click="closeModal" class="px-4 py-2 border border-outline-variant rounded text-sm font-medium text-on-surface-variant hover:bg-surface-container transition">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 bg-primary text-on-primary rounded text-sm font-semibold hover:bg-primary-container transition">
                                Save Account
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Data Table Section -->
    <div class="bg-surface-container-low border border-outline-variant rounded-lg overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-surface-container border-b border-outline-variant">
                    <tr>
                        <th scope="col" class="py-3.5 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Account Name</th>
                        <th scope="col" class="py-3.5 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Type</th>
                        <th scope="col" class="py-3.5 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Opening Balance</th>
                        <th scope="col" class="py-3.5 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Current Balance</th>
                        <th scope="col" class="py-3.5 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Status</th>
                        <th scope="col" class="py-3.5 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/40 bg-surface-container-low">
                    @forelse($accounts as $account)
                        <tr class="hover:bg-surface-container/50 transition">
                            <td class="py-3.5 px-4 text-sm font-semibold text-on-surface">
                                {{ $account->name }}
                            </td>
                            <td class="py-3.5 px-4 text-sm text-on-surface-variant capitalize">
                                {{ $account->account_type }}
                            </td>
                            <td class="py-3.5 px-4 text-sm text-on-surface-variant font-mono">
                                {{ \App\Support\Format::currency($account->opening_balance) }}
                            </td>
                            <td class="py-3.5 px-4 text-sm font-bold text-primary font-mono">
                                {{ \App\Support\Format::currency($balanceService->calculateBalance($account)) }}
                            </td>
                            <td class="py-3.5 px-4 text-xs">
                                @if($account->is_active)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                        Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                                        Inactive
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-xs text-right font-medium">
                                <div class="flex items-center justify-end gap-3">
                                    <button wire:click="editAccount({{ $account->id }})" class="text-primary hover:underline flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[16px]">edit</span>
                                        Edit
                                    </button>
                                    <button wire:click="toggleActiveStatus({{ $account->id }})" 
                                            class="{{ $account->is_active ? 'text-amber-400 hover:text-amber-300' : 'text-emerald-400 hover:text-emerald-300' }} flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[16px]">
                                            {{ $account->is_active ? 'block' : 'check_circle' }}
                                        </span>
                                        {{ $account->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 px-4 text-center text-on-surface-variant">
                                <div class="flex flex-col items-center justify-center max-w-sm mx-auto">
                                    <span class="material-symbols-outlined text-[36px] text-on-surface-variant/40 mb-2">account_balance_wallet</span>
                                    <p class="text-sm font-semibold text-on-surface">No accounts found.</p>
                                    <p class="text-xs text-on-surface-variant/70 mt-1">Click "Add Account" above to create your first account.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Company Total Summary Card -->
    <div class="bg-surface-container-low border border-outline-variant rounded-lg p-4 flex justify-between items-center">
        <span class="text-sm font-medium text-on-surface-variant">Total Active Company Balance:</span>
        <span class="text-lg font-bold text-on-surface font-mono">{{ \App\Support\Format::currency($balanceService->calculateTotalCompanyBalance()) }}</span>
    </div>
</div>
