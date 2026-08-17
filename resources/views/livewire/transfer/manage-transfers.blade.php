<div class="p-6 max-w-7xl mx-auto space-y-6">

    <!-- Page Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-primary flex items-center gap-2">
                <span class="material-symbols-outlined text-[28px]">swap_horiz</span>
                Account Transfers
            </h1>
            <p class="text-sm text-on-surface-variant mt-1">
                Internal fund movements between active company accounts (Phase 7).
            </p>
        </div>

        <button wire:click="openModal"
                class="px-4 py-2.5 bg-primary text-on-primary font-semibold rounded-lg shadow hover:opacity-95 transition flex items-center justify-center gap-2 text-sm">
            <span class="material-symbols-outlined text-[20px]">add</span>
            New Transfer
        </button>
    </div>

    <!-- Alert Notifications -->
    @if (session()->has('success'))
        <div class="p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm flex items-center gap-2">
            <span class="material-symbols-outlined text-[20px]">check_circle</span>
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="p-4 rounded-lg bg-rose-500/10 border border-rose-500/30 text-rose-400 text-sm flex items-center gap-2">
            <span class="material-symbols-outlined text-[20px]">error</span>
            {{ session('error') }}
        </div>
    @endif

    <!-- Filters & Search Bar -->
    <div class="p-4 rounded-xl bg-surface-container-low border border-outline-variant/60 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-2 w-full md:w-96 bg-surface-container border border-outline-variant rounded-lg px-3 py-2">
            <span class="material-symbols-outlined text-on-surface-variant text-[20px]">search</span>
            <input type="text"
                   wire:model.live.debounce.300ms="search"
                   placeholder="Search transfers or account names..."
                   class="bg-transparent text-sm text-on-surface placeholder-on-surface-variant/60 focus:outline-none w-full">
        </div>

        <div class="flex items-center gap-2">
            <span class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Record Status:</span>
            <select wire:model.live="recordFilter"
                    class="bg-surface-container text-sm text-on-surface border border-outline-variant rounded-lg px-3 py-2 focus:outline-none focus:border-primary">
                <option value="active">Active Only</option>
                <option value="cancelled">Cancelled Only</option>
                <option value="all">All Records</option>
            </select>
        </div>
    </div>

    <!-- Data Table -->
    <div class="rounded-xl bg-surface-container-low border border-outline-variant/60 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-on-surface">
                <thead class="bg-surface-container text-xs uppercase font-semibold text-on-surface-variant border-b border-outline-variant/60">
                    <tr>
                        <th class="px-6 py-4">Date</th>
                        <th class="px-6 py-4">From Account</th>
                        <th class="px-6 py-4">To Account</th>
                        <th class="px-6 py-4 text-right">Amount</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Description</th>
                        <th class="px-6 py-4 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/40">
                    @forelse ($transfers as $transfer)
                        <tr class="hover:bg-surface-container-high/40 transition">
                            <td class="px-6 py-4 whitespace-nowrap font-medium text-on-surface">
                                {{ $transfer->transfer_date->format('Y-m-d') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2.5 py-1 rounded-md bg-rose-500/10 text-rose-400 font-medium text-xs border border-rose-500/20">
                                    {{ $transfer->fromAccount->name ?? 'Deleted' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2.5 py-1 rounded-md bg-emerald-500/10 text-emerald-400 font-medium text-xs border border-emerald-500/20">
                                    {{ $transfer->toAccount->name ?? 'Deleted' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right font-bold text-primary">
                                {{ \App\Support\Format::currency($transfer->amount) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if ($transfer->isActive())
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/30">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span> Cancelled
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-on-surface-variant max-w-xs truncate">
                                {{ $transfer->description ?: '-' }}
                            </td>
                            <td class="px-6 py-4 text-center whitespace-nowrap">
                                @if ($transfer->isActive())
                                    <button wire:click="openCancelModal({{ $transfer->id }})"
                                            class="px-3 py-1 bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 border border-rose-500/30 rounded text-xs font-medium transition">
                                        Cancel Transfer
                                    </button>
                                @else
                                    <span class="text-xs text-on-surface-variant/40 italic">No actions</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-on-surface-variant">
                                <span class="material-symbols-outlined text-[48px] text-on-surface-variant/40 mb-2">swap_horiz</span>
                                <p class="text-base font-medium">No transfer records found.</p>
                                <p class="text-xs text-on-surface-variant/70 mt-1">Click "New Transfer" above to record an internal account movement.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($transfers->hasPages())
            <div class="px-6 py-4 border-t border-outline-variant/60 bg-surface-container">
                {{ $transfers->links() }}
            </div>
        @endif
    </div>

    <!-- Create Transfer Modal -->
    @if ($isModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
            <div class="bg-surface-container-low border border-outline-variant rounded-2xl max-w-lg w-full shadow-2xl overflow-hidden">
                <div class="px-6 py-4 border-b border-outline-variant/60 flex items-center justify-between bg-surface-container">
                    <h3 class="text-lg font-bold text-primary flex items-center gap-2">
                        <span class="material-symbols-outlined text-[22px]">swap_horiz</span>
                        Record Internal Transfer
                    </h3>
                    <button wire:click="closeModal" class="text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <form wire:submit.prevent="saveTransfer" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1">Transfer Date *</label>
                        <input type="date" wire:model="transfer_date"
                               class="w-full bg-surface-container text-on-surface border border-outline-variant rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary">
                        @error('transfer_date') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1">From Account (Source) *</label>
                            <select wire:model="from_account_id"
                                    class="w-full bg-surface-container text-on-surface border border-outline-variant rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary">
                                <option value="">Select Source Account</option>
                                @foreach ($accounts as $acc)
                                    <option value="{{ $acc->id }}">{{ $acc->name }} ({{ \App\Support\Format::currency(app(\App\Services\AccountBalanceService::class)->calculateBalance($acc)) }})</option>
                                @endforeach
                            </select>
                            @error('from_account_id') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1">To Account (Destination) *</label>
                            <select wire:model="to_account_id"
                                    class="w-full bg-surface-container text-on-surface border border-outline-variant rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary">
                                <option value="">Select Destination Account</option>
                                @foreach ($accounts as $acc)
                                    <option value="{{ $acc->id }}">{{ $acc->name }} ({{ \App\Support\Format::currency(app(\App\Services\AccountBalanceService::class)->calculateBalance($acc)) }})</option>
                                @endforeach
                            </select>
                            @error('to_account_id') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1">Amount (Rp) *</label>
                        <input type="number" step="0.01" wire:model="amount" placeholder="e.g. 300000"
                               class="w-full bg-surface-container text-on-surface border border-outline-variant rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary">
                        @error('amount') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1">Description (Optional)</label>
                        <textarea wire:model="description" rows="2" placeholder="Transfer notes or reason..."
                                  class="w-full bg-surface-container text-on-surface border border-outline-variant rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary"></textarea>
                        @error('description') <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-outline-variant/60">
                        <button type="button" wire:click="closeModal"
                                class="px-4 py-2 bg-surface-container-high text-on-surface border border-outline-variant rounded-lg text-sm font-medium hover:bg-surface-container transition">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-5 py-2 bg-primary text-on-primary rounded-lg text-sm font-semibold hover:opacity-95 transition">
                            Execute Transfer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Cancel Transfer Confirmation Modal -->
    @if ($isCancelModalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
            <div class="bg-surface-container-low border border-outline-variant rounded-2xl max-w-md w-full shadow-2xl p-6 space-y-4">
                <div class="flex items-center gap-3 text-rose-400">
                    <span class="material-symbols-outlined text-[28px]">warning</span>
                    <h3 class="text-lg font-bold text-on-surface">Confirm Transfer Cancellation</h3>
                </div>

                <p class="text-sm text-on-surface-variant">
                    Are you sure you want to cancel this transfer?
                    Cancelling will reverse the money movement from the destination account back to the source account while keeping the transfer record in audit history.
                </p>

                <div class="flex justify-end gap-3 pt-4 border-t border-outline-variant/60">
                    <button wire:click="closeCancelModal"
                            class="px-4 py-2 bg-surface-container-high text-on-surface border border-outline-variant rounded-lg text-sm font-medium hover:bg-surface-container transition">
                        No, Keep Active
                    </button>
                    <button wire:click="cancelTransfer"
                            class="px-5 py-2 bg-rose-500 text-white rounded-lg text-sm font-semibold hover:bg-rose-600 transition">
                        Yes, Cancel Transfer
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
