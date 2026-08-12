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
            <h2 class="text-2xl font-bold text-on-surface">Income Transactions</h2>
            <p class="text-sm text-on-surface-variant mt-1">Record and manage all income entries and sales transactions.</p>
        </div>
        <div>
            <button wire:click="createIncome"
                    id="btn-create-income"
                    class="px-4 py-2 bg-primary text-on-primary font-semibold text-sm rounded hover:bg-primary-container transition flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">add</span>
                Record Income
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
             aria-labelledby="income-modal-title">

            <div class="bg-surface-container-low border border-outline-variant rounded-lg w-full max-w-lg shadow-2xl max-h-[92vh] flex flex-col">

                {{-- Modal Header --}}
                <div class="flex items-center justify-between px-6 py-4 border-b border-outline-variant/50 flex-shrink-0">
                    <h3 id="income-modal-title" class="text-base font-bold text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-[20px] text-primary">trending_up</span>
                        Record Income Transaction
                    </h3>
                    <button type="button"
                            wire:click="closeModal"
                            class="text-on-surface-variant hover:text-on-surface p-1 rounded transition">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                {{-- Modal Body --}}
                <div class="overflow-y-auto flex-1 px-6 py-5">
                    <form id="income-form" wire:submit.prevent="saveIncome" class="space-y-4">

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
                                        class="w-full h-10 bg-background border @error('payment_status') border-error @else border-outline-variant @enderror text-on-surface rounded px-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                                    <option value="unpaid">Unpaid</option>
                                    <option value="paid">Paid</option>
                                </select>
                                @error('payment_status')
                                    <p class="text-xs text-error mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Menu Item --}}
                        <div>
                            <label for="menu_item_id" class="block text-xs font-semibold text-on-surface-variant mb-1.5 uppercase tracking-wider">
                                Menu Item <span class="text-error">*</span>
                            </label>
                            <select id="menu_item_id"
                                    wire:model.live="menu_item_id"
                                    class="w-full h-10 bg-background border @error('menu_item_id') border-error @else border-outline-variant @enderror text-on-surface rounded px-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                                <option value="">— Select a Menu Item —</option>
                                @foreach($activeMenuItems as $item)
                                    <option value="{{ $item->id }}">
                                        {{ $item->name }} ({{ $item->category }}) — Rp {{ number_format($item->current_price, 0, ',', '.') }}
                                    </option>
                                @endforeach
                            </select>
                            @error('menu_item_id')
                                <p class="text-xs text-error mt-1">{{ $message }}</p>
                            @enderror
                            @if($menu_item_id)
                                @php $selectedItem = $activeMenuItems->firstWhere('id', (int)$menu_item_id); @endphp
                                @if($selectedItem)
                                    <p class="text-xs text-on-surface-variant/70 mt-1.5 flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[14px] text-primary">info</span>
                                        Current unit price: <strong class="text-primary ml-1">Rp {{ number_format($selectedItem->current_price, 2, ',', '.') }}</strong>
                                        — will be locked at creation.
                                    </p>
                                @endif
                            @endif
                        </div>

                        {{-- Row: Quantity + Discount % --}}
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="quantity" class="block text-xs font-semibold text-on-surface-variant mb-1.5 uppercase tracking-wider">
                                    Quantity <span class="text-error">*</span>
                                </label>
                                <input type="number"
                                       id="quantity"
                                       wire:model="quantity"
                                       step="0.01"
                                       min="0.01"
                                       placeholder="e.g. 2"
                                       class="w-full h-10 bg-background border @error('quantity') border-error @else border-outline-variant @enderror text-on-surface rounded px-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                                @error('quantity')
                                    <p class="text-xs text-error mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="discount_percentage" class="block text-xs font-semibold text-on-surface-variant mb-1.5 uppercase tracking-wider">
                                    Discount (%)
                                </label>
                                <input type="number"
                                       id="discount_percentage"
                                       wire:model="discount_percentage"
                                       step="0.01"
                                       min="0"
                                       max="100"
                                       placeholder="0"
                                       class="w-full h-10 bg-background border @error('discount_percentage') border-error @else border-outline-variant @enderror text-on-surface rounded px-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                                @error('discount_percentage')
                                    <p class="text-xs text-error mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Category --}}
                        <div>
                            <label for="category" class="block text-xs font-semibold text-on-surface-variant mb-1.5 uppercase tracking-wider">
                                Category <span class="text-error">*</span>
                            </label>
                            <input type="text"
                                   id="category"
                                   wire:model="category"
                                   maxlength="30"
                                   placeholder="e.g. Cake Sales, Pastry, Beverage"
                                   class="w-full h-10 bg-background border @error('category') border-error @else border-outline-variant @enderror text-on-surface rounded px-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                            @error('category')
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
                                      placeholder="Optional notes about this transaction..."
                                      class="w-full bg-background border @error('description') border-error @else border-outline-variant @enderror text-on-surface rounded px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none resize-none"></textarea>
                            @error('description')
                                <p class="text-xs text-error mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Server-side calculation note --}}
                        <div class="flex items-start gap-2 px-3 py-2.5 bg-surface-container border border-outline-variant/50 rounded text-xs text-on-surface-variant">
                            <span class="material-symbols-outlined text-[16px] text-primary mt-0.5 flex-shrink-0">calculate</span>
                            <span>
                                <strong class="text-on-surface">Server calculates:</strong>
                                subtotal = qty × unit price, discount = subtotal × discount%, total = subtotal − discount.
                                Client values are not trusted.
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
                            form="income-form"
                            class="px-5 py-2 bg-primary text-on-primary rounded text-sm font-semibold hover:bg-primary-container transition flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">save</span>
                        Save Transaction
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
                   id="income-search"
                   wire:model.live.debounce.300ms="search"
                   placeholder="Search by item, category, or transaction ID..."
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
                        <th scope="col" class="py-3.5 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Item / Category</th>
                        <th scope="col" class="py-3.5 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Qty × Unit Price</th>
                        <th scope="col" class="py-3.5 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Discount</th>
                        <th scope="col" class="py-3.5 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Total Amount</th>
                        <th scope="col" class="py-3.5 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Account</th>
                        <th scope="col" class="py-3.5 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Status</th>
                        <th scope="col" class="py-3.5 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/40 bg-surface-container-low">
                    @forelse($incomeTransactions as $tx)
                        <tr class="hover:bg-surface-container/50 transition {{ $tx->isCancelled() ? 'opacity-50' : '' }}">

                            {{-- Date + Transaction ID --}}
                            <td class="py-3.5 px-4">
                                <p class="text-sm font-medium text-on-surface">{{ $tx->transaction_date->format('d M Y') }}</p>
                                <p class="text-[10px] font-mono text-on-surface-variant/60 mt-0.5">{{ substr($tx->transaction_id, 0, 8) }}...</p>
                            </td>

                            {{-- Item + Category --}}
                            <td class="py-3.5 px-4">
                                <p class="text-sm font-semibold text-on-surface">{{ $tx->menuItem?->name ?? '—' }}</p>
                                <p class="text-xs text-on-surface-variant">{{ $tx->category }}</p>
                                @if($tx->description)
                                    <p class="text-xs text-on-surface-variant/60 mt-0.5 truncate max-w-[180px]">{{ $tx->description }}</p>
                                @endif
                            </td>

                            {{-- Qty × Unit Price --}}
                            <td class="py-3.5 px-4">
                                <p class="text-sm text-on-surface font-mono">{{ number_format($tx->quantity, 2, ',', '.') }}</p>
                                <p class="text-xs text-on-surface-variant/70 font-mono">× Rp {{ number_format($tx->unit_price, 0, ',', '.') }}</p>
                            </td>

                            {{-- Discount --}}
                            <td class="py-3.5 px-4">
                                @if((float)$tx->discount_percentage > 0)
                                    <p class="text-sm text-amber-400 font-mono">{{ number_format($tx->discount_percentage, 2, ',', '.') }}%</p>
                                    <p class="text-xs text-on-surface-variant/70 font-mono">−Rp {{ number_format($tx->discount_amount, 0, ',', '.') }}</p>
                                @else
                                    <p class="text-sm text-on-surface-variant/40">—</p>
                                @endif
                            </td>

                            {{-- Total Amount --}}
                            <td class="py-3.5 px-4">
                                <p class="text-sm font-bold text-primary font-mono">Rp {{ number_format($tx->total_amount, 2, ',', '.') }}</p>
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
                                @if($tx->isActive())
                                    <button wire:click="cancelIncome({{ $tx->id }})"
                                            wire:confirm="Cancel this income transaction? The record will be preserved in history."
                                            class="text-xs text-rose-400 hover:text-rose-300 flex items-center gap-1 ml-auto transition">
                                        <span class="material-symbols-outlined text-[14px]">cancel</span>
                                        Cancel
                                    </button>
                                @else
                                    <span class="text-xs text-on-surface-variant/30 italic">Cancelled</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-14 px-4 text-center text-on-surface-variant">
                                <div class="flex flex-col items-center justify-center max-w-sm mx-auto">
                                    <span class="material-symbols-outlined text-[40px] text-on-surface-variant/30 mb-3">receipt_long</span>
                                    <p class="text-sm font-semibold text-on-surface">
                                        {{ ($search || $statusFilter !== 'all' || $recordFilter !== 'active') ? 'No transactions match your filters.' : 'No income transactions recorded yet.' }}
                                    </p>
                                    <p class="text-xs text-on-surface-variant/60 mt-1">
                                        {{ ($search || $statusFilter !== 'all' || $recordFilter !== 'active') ? 'Adjust your search or filter criteria.' : 'Click "Record Income" above to add the first transaction.' }}
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($incomeTransactions->hasPages())
            <div class="p-4 border-t border-outline-variant/60 bg-surface-container">
                {{ $incomeTransactions->links() }}
            </div>
        @endif
    </div>
</div>
