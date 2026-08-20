<div class="space-y-6">
    <!-- Page Header & Global Period Bar -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 pb-4 border-b border-outline-variant/60">
        <div>
            <h2 class="text-2xl font-bold text-on-surface">Financial Reports & Analytics</h2>
            <p class="text-sm text-on-surface-variant mt-1">Exportable financial reports, P&L statement, detailed transaction logs, and audit records.</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <!-- Period Selector -->
            <div class="inline-flex items-center rounded-lg bg-surface-container border border-outline-variant p-1 text-xs">
                <button wire:click="setPresetPeriod('all_time')"
                        class="px-2.5 py-1 rounded font-medium transition {{ $period === 'all_time' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}">
                    All-Time
                </button>
                <button wire:click="setPresetPeriod('this_month')"
                        class="px-2.5 py-1 rounded font-medium transition {{ $period === 'this_month' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}">
                    This Month
                </button>
                <button wire:click="setPresetPeriod('last_month')"
                        class="px-2.5 py-1 rounded font-medium transition {{ $period === 'last_month' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}">
                    Last Month
                </button>
                <button wire:click="setPresetPeriod('this_year')"
                        class="px-2.5 py-1 rounded font-medium transition {{ $period === 'this_year' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}">
                    This Year
                </button>
            </div>

            <!-- Custom Range Inputs -->
            <form wire:submit.prevent="applyCustomDate" class="flex items-center gap-1.5 bg-surface-container border border-outline-variant rounded px-2 py-1 text-xs">
                <span class="material-symbols-outlined text-[16px] text-primary">calendar_today</span>
                <input type="date"
                       wire:model="customFrom"
                       class="bg-transparent text-on-surface text-xs border-none p-0 focus:ring-0 outline-none w-28">
                <span class="text-on-surface-variant/60">to</span>
                <input type="date"
                       wire:model="customTo"
                       class="bg-transparent text-on-surface text-xs border-none p-0 focus:ring-0 outline-none w-28">
                <button type="submit" class="ml-1 px-2 py-0.5 bg-primary-container text-white font-semibold rounded hover:bg-primary/20 text-[11px]">
                    Apply
                </button>
            </form>

            <!-- Export CSV Action Button -->
            <a href="{{ $exportUrl }}"
               target="_blank"
               class="px-3.5 py-1.5 bg-emerald-600 text-white font-semibold text-xs rounded hover:bg-emerald-500 transition flex items-center gap-1.5 shadow-sm">
                <span class="material-symbols-outlined text-[16px]">download</span>
                Export CSV
            </a>
        </div>
    </div>

    @if($dateValidationError)
        <div class="p-3.5 bg-rose-500/10 border border-rose-500/30 rounded-lg text-xs text-rose-400 flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px]">warning</span>
            <span>{{ $dateValidationError }}</span>
        </div>
    @endif

    <!-- Period Indicator Badge -->
    <div class="text-xs text-on-surface-variant flex items-center gap-2">
        <span class="px-2 py-0.5 rounded bg-surface-container border border-outline-variant font-medium text-primary uppercase text-[10px] tracking-wider">
            Period: {{ str_replace('_', ' ', strtoupper($activePeriod)) }}
        </span>
        @if($fromDate && $toDate)
            <span>Showing records from <strong class="text-on-surface">{{ \Carbon\Carbon::parse($fromDate)->format('d M Y') }}</strong> to <strong class="text-on-surface">{{ \Carbon\Carbon::parse($toDate)->format('d M Y') }}</strong></span>
        @else
            <span>Showing all-time recorded financial history</span>
        @endif
    </div>

    <!-- Navigation Tabs -->
    <div class="border-b border-outline-variant/60 flex items-center gap-1 text-sm font-medium">
        <button wire:click="setTab('summary')"
                class="px-4 py-2.5 border-b-2 transition flex items-center gap-2 {{ $activeTab === 'summary' ? 'border-primary text-primary font-semibold' : 'border-transparent text-on-surface-variant hover:text-on-surface' }}">
            <span class="material-symbols-outlined text-[18px]">analytics</span>
            Financial Summary (P&L)
        </button>
        <button wire:click="setTab('income')"
                class="px-4 py-2.5 border-b-2 transition flex items-center gap-2 {{ $activeTab === 'income' ? 'border-primary text-primary font-semibold' : 'border-transparent text-on-surface-variant hover:text-on-surface' }}">
            <span class="material-symbols-outlined text-[18px]">trending_up</span>
            Income Detailed Report
        </button>
        <button wire:click="setTab('expense')"
                class="px-4 py-2.5 border-b-2 transition flex items-center gap-2 {{ $activeTab === 'expense' ? 'border-primary text-primary font-semibold' : 'border-transparent text-on-surface-variant hover:text-on-surface' }}">
            <span class="material-symbols-outlined text-[18px]">trending_down</span>
            Expense Detailed Report
        </button>
        <button wire:click="setTab('transfers')"
                class="px-4 py-2.5 border-b-2 transition flex items-center gap-2 {{ $activeTab === 'transfers' ? 'border-primary text-primary font-semibold' : 'border-transparent text-on-surface-variant hover:text-on-surface' }}">
            <span class="material-symbols-outlined text-[18px]">swap_horiz</span>
            Transfer Detailed Report
        </button>
    </div>

    <!-- TAB 1: FINANCIAL SUMMARY (P&L STATEMENT) -->
    @if($activeTab === 'summary' && $summaryData)
        <div class="space-y-6">
            <!-- Summary Metric Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Revenue -->
                <div class="bg-surface-container-low border border-outline-variant rounded-lg p-5">
                    <span class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Total Revenue</span>
                    <div class="text-xl font-bold text-emerald-400 mt-2">
                        {{ \App\Support\Format::currency($summaryData['total_revenue']) }}
                    </div>
                    <p class="text-xs text-on-surface-variant/70 mt-1">Active paid sales & income</p>
                </div>

                <!-- COGS -->
                <div class="bg-surface-container-low border border-outline-variant rounded-lg p-5">
                    <span class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">COGS</span>
                    <div class="text-xl font-bold text-rose-400 mt-2">
                        {{ \App\Support\Format::currency($summaryData['cogs']) }}
                    </div>
                    <p class="text-xs text-on-surface-variant/70 mt-1">Cost of Goods Sold / Production</p>
                </div>

                <!-- Gross Profit -->
                <div class="bg-surface-container-low border border-outline-variant rounded-lg p-5">
                    <span class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Gross Profit</span>
                    <div class="text-xl font-bold {{ $summaryData['gross_profit'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }} mt-2">
                        {{ \App\Support\Format::currency($summaryData['gross_profit']) }}
                    </div>
                    <p class="text-xs text-on-surface-variant/70 mt-1">Revenue minus COGS</p>
                </div>

                <!-- Net Profit -->
                <div class="bg-surface-container-low border border-outline-variant rounded-lg p-5">
                    <span class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Net Profit</span>
                    <div class="text-xl font-bold {{ $summaryData['net_profit'] >= 0 ? 'text-amber-400' : 'text-rose-400' }} mt-2">
                        {{ \App\Support\Format::currency($summaryData['net_profit']) }}
                    </div>
                    <p class="text-xs text-on-surface-variant/70 mt-1">Revenue minus Profit-Eligible Expenses</p>
                </div>
            </div>

            <!-- Secondary Metrics Bar -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-surface-container border border-outline-variant/60 rounded-lg p-4 flex items-center justify-between">
                    <div>
                        <p class="text-xs text-on-surface-variant">Profit-Eligible OpEx</p>
                        <p class="text-base font-bold text-on-surface">{{ \App\Support\Format::currency($summaryData['profit_eligible_opex']) }}</p>
                    </div>
                    <span class="text-[10px] px-2 py-0.5 rounded bg-surface-container-high text-on-surface-variant">Operational, Marketing, Salary, Rent</span>
                </div>

                <div class="bg-surface-container border border-outline-variant/60 rounded-lg p-4 flex items-center justify-between">
                    <div>
                        <p class="text-xs text-on-surface-variant">Total Expenses (includes Asset)</p>
                        <p class="text-base font-bold text-rose-400">{{ \App\Support\Format::currency($summaryData['total_expenses']) }}</p>
                    </div>
                    <span class="text-[10px] px-2 py-0.5 rounded bg-rose-500/10 text-rose-400">All paid outflows</span>
                </div>

                <div class="bg-surface-container border border-outline-variant/60 rounded-lg p-4 flex items-center justify-between">
                    <div>
                        <p class="text-xs text-on-surface-variant">Asset Expenses</p>
                        <p class="text-base font-bold text-primary">{{ \App\Support\Format::currency($summaryData['asset_expenses']) }}</p>
                    </div>
                    <span class="text-[10px] px-2 py-0.5 rounded bg-primary-container/20 text-primary">Excluded from Net Profit</span>
                </div>
            </div>

            <!-- Expense Category Breakdown Table -->
            <div class="bg-surface-container-low border border-outline-variant rounded-lg p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-on-surface">Expense Category Breakdown</h3>
                        <p class="text-xs text-on-surface-variant mt-0.5">Categorized breakdown of all paid active expenses and Net Profit eligibility</p>
                    </div>
                </div>

                <div class="border border-outline-variant/60 rounded-lg overflow-hidden">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-surface-container border-b border-outline-variant/60">
                            <tr>
                                <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Expense Category</th>
                                <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider text-center">Paid Transaction Count</th>
                                <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Total Amount</th>
                                <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider text-right">Net Profit Impact</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/40 bg-surface-container-low">
                            @foreach($summaryData['category_breakdown'] as $cat)
                                <tr class="hover:bg-surface-container/50 transition">
                                    <td class="py-3.5 px-4">
                                        <p class="text-xs font-semibold text-on-surface">{{ $cat['category'] }}</p>
                                    </td>
                                    <td class="py-3.5 px-4 text-center">
                                        <span class="px-2 py-0.5 rounded text-xs bg-surface-container font-mono text-on-surface">
                                            {{ $cat['count'] }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <p class="text-xs font-bold font-mono text-on-surface">
                                            {{ \App\Support\Format::currency($cat['total_amount']) }}
                                        </p>
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        @if($cat['net_profit_impact'] === 'Included')
                                            <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                                                Reduces Net Profit
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-primary-container/20 text-primary border border-primary/30">
                                                {{ $cat['net_profit_impact'] }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 2: INCOME DETAILED REPORT -->
    @if($activeTab === 'income' && $incomeData)
        <div class="space-y-4">
            <!-- Filter Bar -->
            <div class="bg-surface-container border border-outline-variant rounded-lg p-4 flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[200px]">
                    <input type="text"
                           wire:model.live.debounce.300ms="search"
                           placeholder="Search by ID, Description, Menu Item..."
                           class="w-full bg-surface-container-high text-on-surface text-xs rounded border border-outline-variant/60 px-3 py-1.5 focus:outline-none focus:border-primary">
                </div>

                <div>
                    <select wire:model.change="account_id" class="bg-surface-container-high text-on-surface text-xs rounded border border-outline-variant/60 px-3 py-1.5 focus:outline-none">
                        <option value="">All Accounts</option>
                        @foreach($accounts as $acc)
                            <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <select wire:model.change="category" class="bg-surface-container-high text-on-surface text-xs rounded border border-outline-variant/60 px-3 py-1.5 focus:outline-none">
                        <option value="">All Categories</option>
                        @foreach($incomeCategories as $cat)
                            <option value="{{ $cat }}">{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <select wire:model.change="payment_status" class="bg-surface-container-high text-on-surface text-xs rounded border border-outline-variant/60 px-3 py-1.5 focus:outline-none">
                        <option value="all">Payment: All</option>
                        <option value="paid">Paid</option>
                        <option value="unpaid">Unpaid</option>
                    </select>
                </div>

                <div>
                    <select wire:model.change="record_status" class="bg-surface-container-high text-on-surface text-xs rounded border border-outline-variant/60 px-3 py-1.5 focus:outline-none">
                        <option value="all">Record: All</option>
                        <option value="active">Active</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>

            <!-- Table -->
            <div class="border border-outline-variant/60 rounded-lg overflow-hidden bg-surface-container-low">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-surface-container border-b border-outline-variant/60">
                            <tr>
                                <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase">Date</th>
                                <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase">Transaction ID</th>
                                <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase">Menu Item</th>
                                <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase">Category</th>
                                <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase text-center">Qty</th>
                                <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase">Total Amount</th>
                                <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase">Account</th>
                                <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase">Payment</th>
                                <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase text-right">Record Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/40">
                            @forelse($incomeData as $tx)
                                <tr class="hover:bg-surface-container/50 transition {{ $tx->isCancelled() ? 'opacity-50' : '' }}">
                                    <td class="py-3 px-4 text-xs font-medium text-on-surface whitespace-nowrap">{{ $tx->transaction_date->format('d M Y') }}</td>
                                    <td class="py-3 px-4 text-xs font-mono text-on-surface-variant whitespace-nowrap">{{ Str::limit($tx->transaction_id, 13) }}</td>
                                    <td class="py-3 px-4 text-xs font-semibold text-on-surface">{{ $tx->menuItem->name ?? 'N/A' }}</td>
                                    <td class="py-3 px-4 text-xs text-on-surface-variant">{{ $tx->category }}</td>
                                    <td class="py-3 px-4 text-xs text-center font-mono">{{ \App\Support\Format::quantity($tx->quantity) }}</td>
                                    <td class="py-3 px-4 text-xs font-bold font-mono text-emerald-400 whitespace-nowrap">{{ \App\Support\Format::currency($tx->total_amount) }}</td>
                                    <td class="py-3 px-4 text-xs text-on-surface-variant whitespace-nowrap">{{ $tx->account->name ?? 'N/A' }}</td>
                                    <td class="py-3 px-4 text-xs whitespace-nowrap">
                                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold {{ $tx->payment_status === 'paid' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-amber-500/10 text-amber-400' }}">
                                            {{ ucfirst($tx->payment_status) }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-xs text-right whitespace-nowrap">
                                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold {{ $tx->record_status === 'active' ? 'bg-surface-container-high text-on-surface' : 'bg-rose-500/10 text-rose-400' }}">
                                            {{ ucfirst($tx->record_status) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="py-10 text-center text-xs text-on-surface-variant">No income records found for the selected filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="p-4 border-t border-outline-variant/60">
                    {{ $incomeData->links() }}
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 3: EXPENSE DETAILED REPORT -->
    @if($activeTab === 'expense' && $expenseData)
        <div class="space-y-4">
            <!-- Filter Bar -->
            <div class="bg-surface-container border border-outline-variant rounded-lg p-4 flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[200px]">
                    <input type="text"
                           wire:model.live.debounce.300ms="search"
                           placeholder="Search by ID, Name, Description..."
                           class="w-full bg-surface-container-high text-on-surface text-xs rounded border border-outline-variant/60 px-3 py-1.5 focus:outline-none focus:border-primary">
                </div>

                <div>
                    <select wire:model.change="account_id" class="bg-surface-container-high text-on-surface text-xs rounded border border-outline-variant/60 px-3 py-1.5 focus:outline-none">
                        <option value="">All Accounts</option>
                        @foreach($accounts as $acc)
                            <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <select wire:model.change="category" class="bg-surface-container-high text-on-surface text-xs rounded border border-outline-variant/60 px-3 py-1.5 focus:outline-none">
                        <option value="">All Expense Categories</option>
                        @foreach($expenseCategories as $cat)
                            <option value="{{ $cat }}">{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <select wire:model.change="payment_status" class="bg-surface-container-high text-on-surface text-xs rounded border border-outline-variant/60 px-3 py-1.5 focus:outline-none">
                        <option value="all">Payment: All</option>
                        <option value="paid">Paid</option>
                        <option value="unpaid">Unpaid</option>
                    </select>
                </div>

                <div>
                    <select wire:model.change="record_status" class="bg-surface-container-high text-on-surface text-xs rounded border border-outline-variant/60 px-3 py-1.5 focus:outline-none">
                        <option value="all">Record: All</option>
                        <option value="active">Active</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>

            <!-- Table -->
            <div class="border border-outline-variant/60 rounded-lg overflow-hidden bg-surface-container-low">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-surface-container border-b border-outline-variant/60">
                            <tr>
                                <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase">Date</th>
                                <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase">Transaction Name</th>
                                <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase">Category</th>
                                <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase">Amount</th>
                                <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase">Account</th>
                                <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase">Profit Eligible</th>
                                <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase">Payment</th>
                                <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase text-right">Record Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/40">
                            @forelse($expenseData as $tx)
                                <tr class="hover:bg-surface-container/50 transition {{ $tx->isCancelled() ? 'opacity-50' : '' }}">
                                    <td class="py-3 px-4 text-xs font-medium text-on-surface whitespace-nowrap">{{ $tx->transaction_date->format('d M Y') }}</td>
                                    <td class="py-3 px-4 text-xs font-semibold text-on-surface">{{ $tx->transaction_name }}</td>
                                    <td class="py-3 px-4 text-xs text-on-surface-variant">{{ $tx->expense_category }}</td>
                                    <td class="py-3 px-4 text-xs font-bold font-mono text-rose-400 whitespace-nowrap">{{ \App\Support\Format::currency($tx->amount) }}</td>
                                    <td class="py-3 px-4 text-xs text-on-surface-variant whitespace-nowrap">{{ $tx->account->name ?? 'N/A' }}</td>
                                    <td class="py-3 px-4 text-xs whitespace-nowrap">
                                        @if($tx->expense_category === 'Asset')
                                            <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-primary-container/20 text-primary border border-primary/30">
                                                No (Asset)
                                            </span>
                                        @elseif(in_array($tx->expense_category, \App\Models\ExpenseTransaction::PROFIT_ELIGIBLE_CATEGORIES, true))
                                            <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                                                Yes
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-surface-container-high text-on-surface-variant">
                                                No
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-xs whitespace-nowrap">
                                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold {{ $tx->payment_status === 'paid' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-amber-500/10 text-amber-400' }}">
                                            {{ ucfirst($tx->payment_status) }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-xs text-right whitespace-nowrap">
                                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold {{ $tx->record_status === 'active' ? 'bg-surface-container-high text-on-surface' : 'bg-rose-500/10 text-rose-400' }}">
                                            {{ ucfirst($tx->record_status) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-10 text-center text-xs text-on-surface-variant">No expense records found for the selected filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="p-4 border-t border-outline-variant/60">
                    {{ $expenseData->links() }}
                </div>
            </div>
        </div>
    @endif

    <!-- TAB 4: TRANSFER DETAILED REPORT -->
    @if($activeTab === 'transfers' && $transferData)
        <div class="space-y-4">
            <!-- Filter Bar -->
            <div class="bg-surface-container border border-outline-variant rounded-lg p-4 flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[200px]">
                    <input type="text"
                           wire:model.live.debounce.300ms="search"
                           placeholder="Search by Transfer ID, Description..."
                           class="w-full bg-surface-container-high text-on-surface text-xs rounded border border-outline-variant/60 px-3 py-1.5 focus:outline-none focus:border-primary">
                </div>

                <div>
                    <select wire:model.change="from_account_id" class="bg-surface-container-high text-on-surface text-xs rounded border border-outline-variant/60 px-3 py-1.5 focus:outline-none">
                        <option value="">From: All Accounts</option>
                        @foreach($accounts as $acc)
                            <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <select wire:model.change="to_account_id" class="bg-surface-container-high text-on-surface text-xs rounded border border-outline-variant/60 px-3 py-1.5 focus:outline-none">
                        <option value="">To: All Accounts</option>
                        @foreach($accounts as $acc)
                            <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <select wire:model.change="record_status" class="bg-surface-container-high text-on-surface text-xs rounded border border-outline-variant/60 px-3 py-1.5 focus:outline-none">
                        <option value="all">Record: All</option>
                        <option value="active">Active</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>

            <!-- Table -->
            <div class="border border-outline-variant/60 rounded-lg overflow-hidden bg-surface-container-low">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-surface-container border-b border-outline-variant/60">
                            <tr>
                                <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase">Date</th>
                                <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase">Transfer ID</th>
                                <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase">From Account</th>
                                <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase">To Account</th>
                                <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase">Amount</th>
                                <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase">Description</th>
                                <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase text-right">Record Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/40">
                            @forelse($transferData as $tx)
                                <tr class="hover:bg-surface-container/50 transition {{ $tx->isCancelled() ? 'opacity-50' : '' }}">
                                    <td class="py-3 px-4 text-xs font-medium text-on-surface whitespace-nowrap">{{ $tx->transfer_date->format('d M Y') }}</td>
                                    <td class="py-3 px-4 text-xs font-mono text-on-surface-variant whitespace-nowrap">{{ Str::limit($tx->transfer_id, 13) }}</td>
                                    <td class="py-3 px-4 text-xs font-semibold text-on-surface whitespace-nowrap">{{ $tx->fromAccount->name ?? 'N/A' }}</td>
                                    <td class="py-3 px-4 text-xs font-semibold text-on-surface whitespace-nowrap">{{ $tx->toAccount->name ?? 'N/A' }}</td>
                                    <td class="py-3 px-4 text-xs font-bold font-mono text-primary whitespace-nowrap">{{ \App\Support\Format::currency($tx->amount) }}</td>
                                    <td class="py-3 px-4 text-xs text-on-surface-variant">{{ $tx->description ?? 'N/A' }}</td>
                                    <td class="py-3 px-4 text-xs text-right whitespace-nowrap">
                                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold {{ $tx->record_status === 'active' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400' }}">
                                            {{ $tx->record_status === 'active' ? 'Completed' : 'Cancelled' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-10 text-center text-xs text-on-surface-variant">No transfer records found for the selected filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="p-4 border-t border-outline-variant/60">
                    {{ $transferData->links() }}
                </div>
            </div>
        </div>
    @endif
</div>
