<div class="space-y-6">

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="flex items-center gap-3 px-4 py-3 bg-emerald-500/10 border border-emerald-500/30 rounded text-emerald-400 text-sm">
            <span class="material-symbols-outlined text-[18px]">check_circle</span>
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="flex items-center gap-3 px-4 py-3 bg-rose-500/10 border border-rose-500/30 rounded text-rose-400 text-sm">
            <span class="material-symbols-outlined text-[18px]">error</span>
            {{ session('error') }}
        </div>
    @endif

    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-outline-variant/60">
        <div>
            <h2 class="text-2xl font-bold text-on-surface">Expense Transactions</h2>
            <p class="text-sm text-on-surface-variant mt-1">Record and manage all operational and production expense entries.</p>
        </div>
        <div>
            <button wire:click="createExpense"
                    id="btn-create-expense"
                    class="px-4 py-2 bg-rose-600 text-white font-semibold text-sm rounded hover:bg-rose-700 transition flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">add</span>
                Record Expense
            </button>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- Modal Form (Create) --}}
    {{-- ============================================================ --}}
    @if($isModalOpen)
        <div class="fixed inset-0 bg-black/75 flex items-center justify-center z-50 p-4"
             role="dialog"
             aria-modal="true"
             aria-labelledby="expense-modal-title">

            <div class="bg-surface-container-low border border-outline-variant rounded-lg w-full max-w-lg shadow-2xl max-h-[92vh] flex flex-col">

                {{-- Modal Header --}}
                <div class="flex items-center justify-between px-6 py-4 border-b border-outline-variant/50 flex-shrink-0">
                    <h3 id="expense-modal-title" class="text-base font-bold text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-[20px] text-rose-400">trending_down</span>
                        {{ $editingExpenseId ? 'Edit Expense Transaction' : 'Record Expense Transaction' }}
                    </h3>
                    <button type="button"
                            wire:click="closeModal"
                            class="text-on-surface-variant hover:text-on-surface p-1 rounded transition">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                {{-- Modal Body --}}
                <div class="overflow-y-auto flex-1 px-6 py-5">
                    <form id="expense-form" wire:submit.prevent="saveExpense" class="space-y-4">

                        {{-- Row: Date + Payment Status --}}
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="transaction_date" class="block text-xs font-semibold text-on-surface-variant mb-1.5 uppercase tracking-wider">
                                    Transaction Date <span class="text-error">*</span>
                                </label>
                                <input type="date"
                                       id="transaction_date"
                                       wire:model="transaction_date"
                                       class="w-full h-10 bg-background border @error('transaction_date') border-error @else border-outline-variant @enderror text-on-surface rounded px-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                                @error('transaction_date')
                                    <p class="text-xs text-error mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="payment_status" class="block text-xs font-semibold text-on-surface-variant mb-1.5 uppercase tracking-wider">
                                    Payment Status <span class="text-error">*</span>
                                </label>
                                 <select id="payment_status"
                                         wire:model.live="payment_status"
                                         @if($editingExpenseId && $payment_status === 'paid') disabled @endif
                                         class="w-full h-10 bg-background border @error('payment_status') border-error @else border-outline-variant @enderror text-on-surface rounded px-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none disabled:opacity-50 disabled:cursor-not-allowed">
                                     <option value="unpaid">Unpaid</option>
                                     <option value="paid">Paid</option>
                                 </select>
                                @error('payment_status')
                                    <p class="text-xs text-error mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Transaction Name --}}
                        <div>
                            <label for="transaction_name" class="block text-xs font-semibold text-on-surface-variant mb-1.5 uppercase tracking-wider">
                                Transaction Name <span class="text-error">*</span>
                            </label>
                            <input type="text"
                                   id="transaction_name"
                                   wire:model="transaction_name"
                                   placeholder="e.g. Flour and Sugar Supply Purchase"
                                   class="w-full h-10 bg-background border @error('transaction_name') border-error @else border-outline-variant @enderror text-on-surface rounded px-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                            @error('transaction_name')
                                <p class="text-xs text-error mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Expense Category --}}
                        <div>
                            <label for="expense_category" class="block text-xs font-semibold text-on-surface-variant mb-1.5 uppercase tracking-wider">
                                Expense Category <span class="text-error">*</span>
                            </label>
                            <select id="expense_category"
                                    wire:model="expense_category"
                                    class="w-full h-10 bg-background border @error('expense_category') border-error @else border-outline-variant @enderror text-on-surface rounded px-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                                <option value="">— Select a Category —</option>
                                @foreach($expenseCategories as $cat)
                                    <option value="{{ $cat }}">{{ $cat }}</option>
                                @endforeach
                            </select>
                            @error('expense_category')
                                <p class="text-xs text-error mt-1">{{ $message }}</p>
                            @enderror
                            <p class="text-xs text-on-surface-variant/60 mt-1.5 flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px] text-rose-400">info</span>
                                COGS / Cake Production affects Gross Profit. Other categories affect Operating Expense.
                            </p>
                        </div>

                        {{-- Amount --}}
                        <div>
                            <label for="amount" class="block text-xs font-semibold text-on-surface-variant mb-1.5 uppercase tracking-wider">
                                Amount (Rp) <span class="text-error">*</span>
                            </label>
                            <input type="number"
                                   id="amount"
                                   wire:model="amount"
                                   step="0.01"
                                   min="0.01"
                                   placeholder="e.g. 150000"
                                   class="w-full h-10 bg-background border @error('amount') border-error @else border-outline-variant @enderror text-on-surface rounded px-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                            @error('amount')
                                <p class="text-xs text-error mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Account (required for paid, optional for unpaid) --}}
                        <div>
                            <label for="account_id" class="block text-xs font-semibold text-on-surface-variant mb-1.5 uppercase tracking-wider">
                                Account
                                @if($payment_status === 'paid')
                                    <span class="text-error">*</span>
                                    <span class="text-on-surface-variant/50 font-normal normal-case ml-1">(required for paid)</span>
                                @else
                                    <span class="text-on-surface-variant/50 font-normal normal-case ml-1">(optional for unpaid)</span>
                                @endif
                            </label>
                            <select id="account_id"
                                    wire:model="account_id"
                                    class="w-full h-10 bg-background border @error('account_id') border-error @else border-outline-variant @enderror text-on-surface rounded px-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                                <option value="">— No Account —</option>
                                @foreach($activeAccounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->name }} ({{ ucfirst($account->account_type) }})</option>
                                @endforeach
                            </select>
                            @error('account_id')
                                <p class="text-xs text-error mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Description --}}
                        <div>
                            <label for="description" class="block text-xs font-semibold text-on-surface-variant mb-1.5 uppercase tracking-wider">
                                Notes / Description
                            </label>
                            <textarea id="description"
                                      wire:model="description"
                                      rows="2"
                                      maxlength="5000"
                                      placeholder="Optional notes about this expense..."
                                      class="w-full bg-background border @error('description') border-error @else border-outline-variant @enderror text-on-surface rounded px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none resize-none"></textarea>
                            @error('description')
                                <p class="text-xs text-error mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Server-side calculation note --}}
                        <div class="flex items-start gap-2 px-3 py-2.5 bg-surface-container border border-outline-variant/50 rounded text-xs text-on-surface-variant">
                            <span class="material-symbols-outlined text-[16px] text-rose-400 mt-0.5 flex-shrink-0">security</span>
                            <span>
                                <strong class="text-on-surface">Server validates:</strong>
                                Amount is parsed and validated server-side. Client-provided values are not trusted. (INV-018)
                            </span>
                        </div>

                    </form>
                </div>

                {{-- Modal Footer --}}
                <div class="flex justify-end gap-3 px-6 py-4 border-t border-outline-variant/50 flex-shrink-0">
                    <button type="button"
                            wire:click="closeModal"
                            class="px-4 py-2 border border-outline-variant rounded text-sm font-medium text-on-surface-variant hover:bg-surface-container transition">
                        Cancel
                    </button>
                    <button type="submit"
                            form="expense-form"
                            class="px-5 py-2 bg-rose-600 text-white rounded text-sm font-semibold hover:bg-rose-700 transition flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">save</span>
                        Save Expense
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ============================================================ --}}
    {{-- Payment Confirmation Modal (Phase 5) --}}
    {{-- ============================================================ --}}
    @if($isConfirmModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-background/80 backdrop-blur-sm">
            <div class="bg-surface-container-low border border-outline-variant rounded-xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col">
                <div class="flex items-center justify-between px-6 py-4 border-b border-outline-variant/50 flex-shrink-0">
                    <h3 class="text-base font-semibold text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-[20px] text-emerald-400">check_circle</span>
                        Confirm Expense Payment
                    </h3>
                    <button wire:click="closeConfirmModal" class="text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <p class="text-xs text-on-surface-variant">
                        Select an active account to debit this expense payment. Once confirmed, the account balance will update immediately.
                    </p>

                    <div>
                        <label for="confirm_account_id" class="block text-xs font-semibold text-on-surface-variant mb-1.5 uppercase tracking-wider">
                            Source Account <span class="text-error">*</span>
                        </label>
                        <select id="confirm_account_id"
                                wire:model="confirm_account_id"
                                class="w-full h-10 bg-background border @error('confirm_account_id') border-error @else border-outline-variant @enderror text-on-surface rounded px-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                            <option value="">— Select Active Account —</option>
                            @foreach($activeAccounts as $account)
                                <option value="{{ $account->id }}">{{ $account->name }} ({{ ucfirst($account->account_type) }})</option>
                            @endforeach
                        </select>
                        @error('confirm_account_id')
                            <p class="text-xs text-error mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                <div class="flex justify-end gap-3 px-6 py-4 border-t border-outline-variant/50 flex-shrink-0">
                    <button type="button" wire:click="closeConfirmModal" class="px-4 py-2 border border-outline-variant rounded text-sm font-medium text-on-surface-variant hover:bg-surface-container transition">
                        Cancel
                    </button>
                    <button type="button" wire:click="confirmPayment" class="px-5 py-2 bg-emerald-500 text-on-primary rounded text-sm font-semibold hover:bg-emerald-600 transition flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">check</span>
                        Confirm Payment
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ============================================================ --}}
    {{-- Toolbar: Search + Filters --}}
    {{-- ============================================================ --}}
    <div class="bg-surface-container-low border border-outline-variant rounded-lg p-4 flex flex-col md:flex-row md:items-center gap-4">

        {{-- Search --}}
        <div class="relative flex-1 max-w-md">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[19px] text-on-surface-variant">search</span>
            <input type="text"
                   id="expense-search"
                   wire:model.live.debounce.300ms="search"
                   placeholder="Search by category, description, or transaction ID..."
                   class="w-full h-10 bg-background border border-outline-variant text-on-surface rounded pl-10 pr-3 text-sm outline-none transition focus:border-primary focus:ring-1 focus:ring-primary placeholder:text-on-surface-variant/50">
        </div>

        {{-- Payment Status Filter --}}
        <div class="flex items-center gap-1 bg-background p-1 border border-outline-variant rounded">
            <button type="button"
                    wire:click="setStatusFilter('all')"
                    class="px-3 py-1.5 rounded text-xs font-semibold transition {{ $statusFilter === 'all' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}">
                All
            </button>
            <button type="button"
                    wire:click="setStatusFilter('paid')"
                    class="px-3 py-1.5 rounded text-xs font-semibold transition {{ $statusFilter === 'paid' ? 'bg-emerald-600 text-white shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}">
                Paid
            </button>
            <button type="button"
                    wire:click="setStatusFilter('unpaid')"
                    class="px-3 py-1.5 rounded text-xs font-semibold transition {{ $statusFilter === 'unpaid' ? 'bg-amber-600 text-white shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}">
                Unpaid
            </button>
        </div>

        {{-- Record Status Filter --}}
        <div class="flex items-center gap-1 bg-background p-1 border border-outline-variant rounded">
            <button type="button"
                    wire:click="setRecordFilter('active')"
                    class="px-3 py-1.5 rounded text-xs font-semibold transition {{ $recordFilter === 'active' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}">
                Active
            </button>
            <button type="button"
                    wire:click="setRecordFilter('cancelled')"
                    class="px-3 py-1.5 rounded text-xs font-semibold transition {{ $recordFilter === 'cancelled' ? 'bg-rose-600 text-white shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}">
                Cancelled
            </button>
            <button type="button"
                    wire:click="setRecordFilter('all')"
                    class="px-3 py-1.5 rounded text-xs font-semibold transition {{ $recordFilter === 'all' ? 'bg-surface-container-high text-on-surface shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}">
                All Records
            </button>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- Data Table --}}
    {{-- ============================================================ --}}
    <div class="bg-surface-container-low border border-outline-variant rounded-lg overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-surface-container border-b border-outline-variant">
                    <tr>
                        <th scope="col" class="py-3.5 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Date</th>
                        <th scope="col" class="py-3.5 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Transaction Name</th>
                        <th scope="col" class="py-3.5 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Category / Description</th>
                        <th scope="col" class="py-3.5 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Amount</th>
                        <th scope="col" class="py-3.5 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Account</th>
                        <th scope="col" class="py-3.5 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Status</th>
                        <th scope="col" class="py-3.5 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/40 bg-surface-container-low">
                    @forelse($expenseTransactions as $tx)
                        <tr class="hover:bg-surface-container/50 transition {{ $tx->isCancelled() ? 'opacity-50' : '' }}">

                            {{-- Date + Transaction ID --}}
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <p class="text-sm font-medium text-on-surface">{{ $tx->transaction_date->format('d M Y') }}</p>
                                <p class="text-[10px] font-mono text-on-surface-variant/60 mt-0.5">{{ substr($tx->transaction_id, 0, 8) }}...</p>
                            </td>

                            {{-- Transaction Name --}}
                            <td class="py-3.5 px-4">
                                <p class="text-sm font-semibold text-on-surface">{{ $tx->transaction_name }}</p>
                            </td>

                            {{-- Category + Description --}}
                            <td class="py-3.5 px-4">
                                <span class="px-2 py-0.5 rounded text-[11px] font-medium bg-surface-container-high text-on-surface border border-outline-variant/40">
                                    {{ $tx->expense_category }}
                                </span>
                                @if($tx->description)
                                    <p class="text-xs text-on-surface-variant/60 mt-1 truncate max-w-[200px]">{{ $tx->description }}</p>
                                @endif
                            </td>

                            {{-- Amount --}}
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <p class="text-sm font-bold text-rose-400 font-mono">Rp {{ number_format($tx->amount, 2, ',', '.') }}</p>
                            </td>

                            {{-- Account --}}
                            <td class="py-3.5 px-4">
                                @if($tx->account)
                                    <p class="text-xs text-on-surface">{{ $tx->account->name }}</p>
                                    <p class="text-[10px] text-on-surface-variant capitalize">{{ $tx->account->account_type }}</p>
                                @else
                                    <p class="text-xs text-on-surface-variant/40">—</p>
                                @endif
                            </td>

                            {{-- Status Badges --}}
                            <td class="py-3.5 px-4">
                                <div class="flex flex-col gap-1">
                                    @if($tx->isPaid())
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>Paid
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>Unpaid
                                        </span>
                                    @endif

                                    @if($tx->isCancelled())
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>Cancelled
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-surface-container-high text-on-surface-variant border border-outline-variant/30">
                                            <span class="w-1.5 h-1.5 rounded-full bg-on-surface-variant/40"></span>Active
                                        </span>
                                    @endif
                                </div>
                            </td>

                            {{-- Actions --}}
                            <td class="py-3.5 px-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    @if($tx->isActive() && $tx->isUnpaid())
                                        <button wire:click="openConfirmModal({{ $tx->id }})"
                                                class="text-xs text-emerald-400 hover:text-emerald-300 flex items-center gap-1 transition font-medium">
                                            <span class="material-symbols-outlined text-[14px]">check_circle</span>
                                            Confirm Payment
                                        </button>
                                    @endif

                                    @if($tx->isActive())
                                        <button wire:click="editExpense({{ $tx->id }})"
                                                class="text-xs text-primary hover:text-primary-container flex items-center gap-1 transition font-medium">
                                            <span class="material-symbols-outlined text-[14px]">edit</span>
                                            Edit
                                        </button>
                                    @endif

                                    @if($tx->isActive())
                                        <button wire:click="cancelExpense({{ $tx->id }})"
                                                wire:confirm="Cancel this expense transaction? The record will be preserved in history."
                                                class="text-xs text-rose-400 hover:text-rose-300 flex items-center gap-1 transition">
                                            <span class="material-symbols-outlined text-[14px]">cancel</span>
                                            Cancel
                                        </button>
                                    @else
                                        <span class="text-xs text-on-surface-variant/30 italic">Cancelled</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-14 px-4 text-center text-on-surface-variant">
                                <div class="flex flex-col items-center justify-center max-w-sm mx-auto">
                                    <span class="material-symbols-outlined text-[40px] text-on-surface-variant/30 mb-3">receipt_long</span>
                                    <p class="text-sm font-semibold text-on-surface">
                                        {{ ($search || $statusFilter !== 'all' || $recordFilter !== 'active') ? 'No transactions match your filters.' : 'No expense transactions recorded yet.' }}
                                    </p>
                                    <p class="text-xs text-on-surface-variant/60 mt-1">
                                        {{ ($search || $statusFilter !== 'all' || $recordFilter !== 'active') ? 'Adjust your search or filter criteria.' : 'Click "Record Expense" above to add the first transaction.' }}
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($expenseTransactions->hasPages())
            <div class="p-4 border-t border-outline-variant/60 bg-surface-container">
                {{ $expenseTransactions->links() }}
            </div>
        @endif
    </div>
</div>
