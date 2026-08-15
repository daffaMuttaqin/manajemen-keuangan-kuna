@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-outline-variant/60">
        <div>
            <h2 class="text-2xl font-bold text-on-surface">Financial Overview</h2>
            <p class="text-sm text-on-surface-variant mt-1">Monitor Kuna Patisserie's financial performance and operational activity.</p>
        </div>
        <div class="flex items-center gap-3">
            <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-surface-container border border-outline-variant rounded text-xs font-medium text-on-surface-variant">
                <span class="material-symbols-outlined text-[16px] text-primary">calendar_today</span>
                <span>Current Period: All-Time</span>
            </div>
            <a href="{{ route('accounts.index') }}" class="px-3.5 py-1.5 bg-primary text-on-primary font-semibold text-xs rounded hover:bg-primary-container transition flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[16px]">account_balance</span>
                Manage Accounts
            </a>
        </div>
    </div>

    <!-- KPI Section (4 Compact Financial Cards) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- KPI 1: Active Cash Balance (Real Backend Data) -->
        <div class="bg-surface-container-low border border-outline-variant rounded-lg p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Current Cash Balance</span>
                <div class="w-8 h-8 rounded bg-primary-container/20 flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined text-[20px]">account_balance_wallet</span>
                </div>
            </div>
            <div class="text-xl font-bold text-primary">
                Rp {{ number_format($totalBalance, 2, ',', '.') }}
            </div>
            <p class="text-xs text-on-surface-variant/70 mt-2 flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                Sum of {{ count($accounts) }} active company {{ Str::plural('account', count($accounts)) }}
            </p>
        </div>

        <!-- KPI 2: Total Revenue (Phase 4 — Live Data) -->
        <div class="bg-surface-container-low border border-outline-variant rounded-lg p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Total Revenue</span>
                <div class="w-8 h-8 rounded bg-emerald-500/10 flex items-center justify-center text-emerald-400">
                    <span class="material-symbols-outlined text-[20px]">trending_up</span>
                </div>
            </div>
            <div class="text-xl font-bold text-emerald-400">
                Rp {{ number_format($totalRevenue, 2, ',', '.') }}
            </div>
            <p class="text-xs text-on-surface-variant/70 mt-2 flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                Active paid income (all-time)
            </p>
        </div>

        <!-- KPI 3: Total Expenses (Phase 4 — Live Data) -->
        <div class="bg-surface-container-low border border-outline-variant rounded-lg p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Total Expenses</span>
                <div class="w-8 h-8 rounded bg-rose-500/10 flex items-center justify-center text-rose-400">
                    <span class="material-symbols-outlined text-[20px]">trending_down</span>
                </div>
            </div>
            <div class="text-xl font-bold text-rose-400">
                Rp {{ number_format($totalExpenses, 2, ',', '.') }}
            </div>
            <p class="text-xs text-on-surface-variant/70 mt-2 flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                Active paid expenses (all-time)
            </p>
        </div>

        <!-- KPI 4: Net Profit (Live Authoritative Formula) -->
        <div class="bg-surface-container-low border border-outline-variant rounded-lg p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Net Profit</span>
                <div class="w-8 h-8 rounded bg-emerald-500/10 flex items-center justify-center text-emerald-400">
                    <span class="material-symbols-outlined text-[20px]">insights</span>
                </div>
            </div>
            <div class="text-xl font-bold {{ $netProfit >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                Rp {{ number_format($netProfit, 2, ',', '.') }}
            </div>
            <p class="text-xs text-on-surface-variant/70 mt-2 flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full {{ $netProfit >= 0 ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                Total Revenue minus Profit-Eligible Expenses
            </p>
        </div>
    </div>

    <!-- Main Section: Cash Position & Financial Overview Chart Container -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left 2 Cols: Cash Trend / Financial Chart Container -->
        <div class="lg:col-span-2 bg-surface-container-low border border-outline-variant rounded-lg p-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-base font-semibold text-on-surface">Revenue vs Expense Trend</h3>
                    <p class="text-xs text-on-surface-variant mt-0.5">Historical financial movements overview</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-on-surface-variant/70">Chart container ready</span>
                </div>
            </div>

            <!-- Chart Ready Empty Container -->
            <div class="h-64 border border-dashed border-outline-variant/60 rounded flex flex-col items-center justify-center p-6 text-center bg-surface-container/30">
                <div class="w-12 h-12 rounded-full bg-surface-container-high flex items-center justify-center text-on-surface-variant mb-3">
                    <span class="material-symbols-outlined text-[28px]">bar_chart</span>
                </div>
                <h4 class="text-sm font-semibold text-on-surface">Financial Trend Chart Container</h4>
                <p class="text-xs text-on-surface-variant max-w-sm mt-1">
                    Visual trend analysis will render here automatically once income and expense transactions are recorded in Phase 4.
                </p>
            </div>
        </div>

        <!-- Right 1 Col: Real Account Balances / Cash Status -->
        <div class="bg-surface-container-low border border-outline-variant rounded-lg p-6 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-semibold text-on-surface">Accounts Breakdown</h3>
                    <a href="{{ route('accounts.index') }}" class="text-xs text-primary hover:underline">View All</a>
                </div>

                <div class="space-y-3">
                    @forelse($accounts as $account)
                        <div class="p-3 bg-surface-container border border-outline-variant/50 rounded flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded bg-surface-container-high flex items-center justify-center text-primary text-xs font-bold uppercase">
                                    {{ substr($account->account_type, 0, 2) }}
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-on-surface">{{ $account->name }}</p>
                                    <p class="text-[11px] text-on-surface-variant capitalize">{{ $account->account_type }} Account</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold text-primary">Rp {{ number_format($balanceService->calculateBalance($account), 2, ',', '.') }}</p>
                                <span class="text-[10px] text-emerald-400 font-medium">Active</span>
                            </div>
                        </div>
                    @empty
                        <div class="p-4 text-center border border-dashed border-outline-variant/50 rounded">
                            <p class="text-xs text-on-surface-variant">No active accounts found.</p>
                            <a href="{{ route('accounts.index') }}" class="text-xs text-primary hover:underline mt-1 inline-block">Create an Account</a>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Menu Quick Metric -->
            <div class="mt-6 pt-4 border-t border-outline-variant/50 flex items-center justify-between">
                <div class="flex items-center gap-2.5 text-xs text-on-surface-variant">
                    <span class="material-symbols-outlined text-[18px] text-primary">restaurant_menu</span>
                    <span>Active Menu Items: <strong class="text-on-surface font-semibold">{{ $activeMenuItemsCount }}</strong></span>
                </div>
                <a href="{{ route('menu.index') }}" class="text-xs text-primary hover:underline font-medium">Manage Menu &rarr;</a>
            </div>
        </div>
    </div>

    <!-- Recent Transactions Section -->
    <div class="bg-surface-container-low border border-outline-variant rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-base font-semibold text-on-surface">Recent Transactions</h3>
                <p class="text-xs text-on-surface-variant mt-0.5">Latest financial movements and transaction entries</p>
            </div>
            <span class="text-xs px-2.5 py-1 bg-surface-container border border-outline-variant rounded text-on-surface-variant">
                Phase 4 Module
            </span>
        </div>

        <div class="border border-outline-variant/60 rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-surface-container border-b border-outline-variant/60">
                        <tr>
                            <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Date</th>
                            <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Description</th>
                            <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Category</th>
                            <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Type</th>
                            <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Amount</th>
                            <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/40 bg-surface-container-low">
                        <tr>
                            <td colspan="6" class="py-12 text-center text-on-surface-variant">
                                <div class="flex flex-col items-center justify-center max-w-sm mx-auto">
                                    <span class="material-symbols-outlined text-[36px] text-on-surface-variant/40 mb-2">receipt_long</span>
                                    <p class="text-sm font-medium text-on-surface">No transactions recorded yet</p>
                                    <p class="text-xs text-on-surface-variant/70 mt-1">
                                        Income, expense, and payment confirmation workflows will be enabled in Phase 4.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
