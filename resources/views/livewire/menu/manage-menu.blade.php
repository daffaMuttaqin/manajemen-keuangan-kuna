<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-outline-variant/60">
        <div>
            <h2 class="text-2xl font-bold text-on-surface">Menu Management</h2>
            <p class="text-sm text-on-surface-variant mt-1">Manage your products and financial master data.</p>
        </div>
        <div>
            <button wire:click="createMenuItem" class="px-4 py-2 bg-primary text-on-primary font-semibold text-sm rounded hover:bg-primary-container transition flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">add</span>
                Create Menu Item
            </button>
        </div>
    </div>

    <!-- Modal Form (Create / Edit) -->
    @if($isModalOpen)
        <div class="fixed inset-0 bg-black/70 flex items-center justify-center z-50 p-4">
            <div class="bg-surface-container-low border border-outline-variant rounded-lg p-6 max-w-md w-full shadow-2xl">
                <div class="flex items-center justify-between mb-5 pb-3 border-b border-outline-variant/50">
                    <h3 class="text-lg font-bold text-on-surface">
                        {{ $editingMenuItemId ? 'Edit Menu Item' : 'Create Menu Item' }}
                    </h3>
                    <button type="button" wire:click="closeModal" class="text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <form wire:submit.prevent="saveMenuItem" class="space-y-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-on-surface mb-1">Item Name</label>
                        <input type="text" id="name" wire:model="name" placeholder="e.g. Artisanal Croissant"
                               class="w-full h-10 bg-background border @error('name') border-error @else border-outline-variant @enderror text-on-surface rounded px-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                        @error('name') <span class="text-xs text-error mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="category" class="block text-sm font-medium text-on-surface mb-1">Category</label>
                        <input type="text" id="category" wire:model="category" placeholder="e.g. Pastry, Cake, Beverage"
                               class="w-full h-10 bg-background border @error('category') border-error @else border-outline-variant @enderror text-on-surface rounded px-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                        @error('category') <span class="text-xs text-error mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="current_price" class="block text-sm font-medium text-on-surface mb-1">Current Price (IDR)</label>
                        <input type="number" step="0.01" id="current_price" wire:model="current_price" placeholder="e.g. 25000"
                               class="w-full h-10 bg-background border @error('current_price') border-error @else border-outline-variant @enderror text-on-surface rounded px-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                        @error('current_price') <span class="text-xs text-error mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex items-center pt-2">
                        <input type="checkbox" id="is_active" wire:model="is_active" class="h-4 w-4 text-primary border-outline-variant bg-background rounded focus:ring-primary">
                        <label for="is_active" class="ml-2.5 text-sm text-on-surface font-medium">Active Menu Item</label>
                        @error('is_active') <span class="text-xs text-error ml-2">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-outline-variant/50 mt-6">
                        <button type="button" wire:click="closeModal" class="px-4 py-2 border border-outline-variant rounded text-sm font-medium text-on-surface-variant hover:bg-surface-container transition">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-primary text-on-primary rounded text-sm font-semibold hover:bg-primary-container transition">
                            Save Item
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Toolbar Section: Search & Status Filters -->
    <div class="bg-surface-container-low border border-outline-variant rounded-lg p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <!-- Search Input -->
        <div class="relative flex-1 max-w-md">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[19px] text-on-surface-variant">
                search
            </span>
            <input type="text" 
                   wire:model.live.debounce.300ms="search" 
                   placeholder="Search item names or IDs..." 
                   class="w-full h-10 bg-background border border-outline-variant text-on-surface rounded pl-10 pr-3 text-sm outline-none transition focus:border-primary focus:ring-1 focus:ring-primary placeholder:text-on-surface-variant/50">
        </div>

        <!-- Status Filter Pills -->
        <div class="flex items-center gap-1 bg-background p-1 border border-outline-variant rounded">
            <button type="button" 
                    wire:click="setStatusFilter('all')" 
                    class="px-3 py-1.5 rounded text-xs font-semibold transition {{ $statusFilter === 'all' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}">
                All Items
            </button>
            <button type="button" 
                    wire:click="setStatusFilter('active')" 
                    class="px-3 py-1.5 rounded text-xs font-semibold transition {{ $statusFilter === 'active' ? 'bg-emerald-600 text-white shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}">
                Active
            </button>
            <button type="button" 
                    wire:click="setStatusFilter('inactive')" 
                    class="px-3 py-1.5 rounded text-xs font-semibold transition {{ $statusFilter === 'inactive' ? 'bg-rose-600 text-white shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}">
                Inactive
            </button>
        </div>
    </div>

    <!-- Data Table Section -->
    <div class="bg-surface-container-low border border-outline-variant rounded-lg overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="bg-surface-container border-b border-outline-variant">
                    <tr>
                        <th scope="col" class="py-3.5 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">ID</th>
                        <th scope="col" class="py-3.5 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Item Name</th>
                        <th scope="col" class="py-3.5 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Category</th>
                        <th scope="col" class="py-3.5 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Current Price (IDR)</th>
                        <th scope="col" class="py-3.5 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Status</th>
                        <th scope="col" class="py-3.5 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/40 bg-surface-container-low">
                    @forelse($menuItems as $item)
                        <tr class="hover:bg-surface-container/50 transition">
                            <td class="py-3.5 px-4 text-xs font-mono text-on-surface-variant">
                                #{{ $item->id }}
                            </td>
                            <td class="py-3.5 px-4 text-sm font-semibold text-on-surface">
                                {{ $item->name }}
                            </td>
                            <td class="py-3.5 px-4 text-sm text-on-surface-variant">
                                {{ $item->category }}
                            </td>
                            <td class="py-3.5 px-4 text-sm font-bold text-primary font-mono">
                                Rp {{ number_format($item->current_price, 2, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-4 text-xs">
                                @if($item->is_active)
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
                                    <button wire:click="editMenuItem({{ $item->id }})" class="text-primary hover:underline flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[16px]">edit</span>
                                        Edit
                                    </button>
                                    <button wire:click="toggleActiveStatus({{ $item->id }})" 
                                            class="{{ $item->is_active ? 'text-amber-400 hover:text-amber-300' : 'text-emerald-400 hover:text-emerald-300' }} flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[16px]">
                                            {{ $item->is_active ? 'block' : 'check_circle' }}
                                        </span>
                                        {{ $item->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 px-4 text-center text-on-surface-variant">
                                <div class="flex flex-col items-center justify-center max-w-sm mx-auto">
                                    <span class="material-symbols-outlined text-[36px] text-on-surface-variant/40 mb-2">restaurant_menu</span>
                                    <p class="text-sm font-semibold text-on-surface">
                                        {{ $search !== '' || $statusFilter !== 'all' ? 'No menu items match your current filters.' : 'No menu items found.' }}
                                    </p>
                                    <p class="text-xs text-on-surface-variant/70 mt-1">
                                        {{ $search !== '' || $statusFilter !== 'all' ? 'Try adjusting your search criteria or status filter.' : 'Click "Create Menu Item" above to add your first product.' }}
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination Footer -->
        @if($menuItems->hasPages())
            <div class="p-4 border-t border-outline-variant/60 bg-surface-container">
                {{ $menuItems->links() }}
            </div>
        @endif
    </div>
</div>
