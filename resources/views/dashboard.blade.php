@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Page Header & Period Filter Bar -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 pb-4 border-b border-outline-variant/60">
        <div>
            <h2 class="text-2xl font-bold text-on-surface">Financial Overview</h2>
            <p class="text-sm text-on-surface-variant mt-1">Monitor Kuna Patisserie's financial performance and operational activity.</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <!-- Period Selector Form -->
            <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-center gap-2">
                <div class="inline-flex items-center rounded-lg bg-surface-container border border-outline-variant p-1 text-xs">
                    <a href="{{ route('dashboard', ['period' => 'all_time']) }}"
                       class="px-2.5 py-1 rounded font-medium transition {{ $period === 'all_time' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}">
                        All-Time
                    </a>
                    <a href="{{ route('dashboard', ['period' => 'this_month']) }}"
                       class="px-2.5 py-1 rounded font-medium transition {{ $period === 'this_month' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}">
                        This Month
                    </a>
                    <a href="{{ route('dashboard', ['period' => 'last_month']) }}"
                       class="px-2.5 py-1 rounded font-medium transition {{ $period === 'last_month' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}">
                        Last Month
                    </a>
                    <a href="{{ route('dashboard', ['period' => 'this_year']) }}"
                       class="px-2.5 py-1 rounded font-medium transition {{ $period === 'this_year' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface' }}">
                        This Year
                    </a>
                </div>

                <!-- Custom Range Inputs -->
                <input type="hidden" name="period" value="custom">
                <div class="flex items-center gap-1.5 bg-surface-container border border-outline-variant rounded px-2 py-1 text-xs">
                    <span class="material-symbols-outlined text-[16px] text-primary">calendar_today</span>
                    <input type="date"
                           name="from"
                           value="{{ $fromDate }}"
                           class="bg-transparent text-on-surface text-xs border-none p-0 focus:ring-0 outline-none w-28">
                    <span class="text-on-surface-variant/60">to</span>
                    <input type="date"
                           name="to"
                           value="{{ $toDate }}"
                           class="bg-transparent text-on-surface text-xs border-none p-0 focus:ring-0 outline-none w-28">
                    <button type="submit" class="ml-1 px-2 py-0.5 bg-primary-container text-primary font-semibold rounded hover:bg-primary/20 text-[11px]">
                        Apply
                    </button>
                </div>
            </form>

            <a href="{{ route('accounts.index') }}" class="px-3.5 py-1.5 bg-primary text-on-primary font-semibold text-xs rounded hover:bg-primary-container transition flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[16px]">account_balance</span>
                Manage Accounts
            </a>
        </div>
    </div>

    @if($dateValidationError)
        <div class="p-3.5 bg-rose-500/10 border border-rose-500/30 rounded-lg text-xs text-rose-400 flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px]">warning</span>
            <span>{{ $dateValidationError }}</span>
        </div>
    @endif

    <!-- Period Context Badge -->
    <div class="text-xs text-on-surface-variant flex items-center gap-2">
        <span class="px-2 py-0.5 rounded bg-surface-container border border-outline-variant font-medium text-primary uppercase text-[10px] tracking-wider">
            Period: {{ str_replace('_', ' ', strtoupper($period)) }}
        </span>
        @if($fromDate && $toDate)
            <span>Showing metrics from <strong class="text-on-surface">{{ \Carbon\Carbon::parse($fromDate)->format('d M Y') }}</strong> to <strong class="text-on-surface">{{ \Carbon\Carbon::parse($toDate)->format('d M Y') }}</strong></span>
        @else
            <span>Showing cumulative all-time financial activity</span>
        @endif
    </div>

    <!-- KPI Section (4 Compact Financial Cards) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- KPI 1: Active Cash Balance (Real Point-in-Time Data) -->
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

        <!-- KPI 2: Total Revenue (Period Bounded) -->
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
                Active paid income (selected period)
            </p>
        </div>

        <!-- KPI 3: Total Expenses (Period Bounded — Includes Asset Expenses) -->
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
                Active paid expenses (includes Asset)
            </p>
        </div>

        <!-- KPI 4: Net Profit (Period Bounded — Excludes Asset Expenses) -->
        <div class="bg-surface-container-low border border-outline-variant rounded-lg p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Net Profit</span>
                <div class="w-8 h-8 rounded bg-amber-500/10 flex items-center justify-center text-amber-400">
                    <span class="material-symbols-outlined text-[20px]">insights</span>
                </div>
            </div>
            <div class="text-xl font-bold {{ $netProfit >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                Rp {{ number_format($netProfit, 2, ',', '.') }}
            </div>
            <p class="text-xs text-on-surface-variant/70 mt-2 flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full {{ $netProfit >= 0 ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                Revenue minus Profit-Eligible Expenses
            </p>
        </div>
    </div>

    <!-- Main Section: Cash Position & Financial Trend Chart Container -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left 2 Cols: Cash Trend / Financial Chart Container -->
        <div class="lg:col-span-2 bg-surface-container-low border border-outline-variant rounded-lg p-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-base font-semibold text-on-surface">Financial Performance Trend</h3>
                    <p class="text-xs text-on-surface-variant mt-0.5">Revenue, Total Expenses, and Net Profit trajectory</p>
                </div>
                <div class="flex items-center gap-4 text-xs font-medium">
                    <div class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                        <span class="text-on-surface-variant">Revenue</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                        <span class="text-on-surface-variant">Total Expenses</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
                        <span class="text-on-surface-variant">Net Profit</span>
                    </div>
                </div>
            </div>

            <!-- Chart Canvas -->
            <div class="h-64 relative">
                <canvas id="financialTrendChart"></canvas>
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
                <p class="text-xs text-on-surface-variant mt-0.5">Latest financial entries and fund transfers</p>
            </div>
            <span class="text-xs px-2.5 py-1 bg-surface-container border border-outline-variant rounded text-on-surface-variant">
                Top {{ count($recentTransactions) }} Latest Entries
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
                            <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider">Account</th>
                            <th class="py-3 px-4 text-xs font-semibold text-on-surface-variant uppercase tracking-wider text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/40 bg-surface-container-low">
                        @forelse($recentTransactions as $tx)
                            <tr class="hover:bg-surface-container/50 transition {{ $tx['is_cancelled'] ? 'opacity-50' : '' }}">
                                {{-- Date --}}
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <p class="text-xs font-medium text-on-surface">{{ $tx['date']->format('d M Y') }}</p>
                                </td>

                                {{-- Title --}}
                                <td class="py-3.5 px-4">
                                    <p class="text-xs font-semibold text-on-surface">{{ $tx['title'] }}</p>
                                </td>

                                {{-- Category --}}
                                <td class="py-3.5 px-4">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-medium bg-surface-container-high text-on-surface border border-outline-variant/40">
                                        {{ $tx['category'] }}
                                    </span>
                                </td>

                                {{-- Type --}}
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    @if($tx['type'] === 'Income')
                                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                                            Income
                                        </span>
                                    @elseif($tx['type'] === 'Expense')
                                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/30">
                                            Expense
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-primary-container/20 text-primary border border-primary/30">
                                            Transfer
                                        </span>
                                    @endif
                                </td>

                                {{-- Amount --}}
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <p class="text-xs font-bold font-mono {{ $tx['type'] === 'Income' ? 'text-emerald-400' : ($tx['type'] === 'Expense' ? 'text-rose-400' : 'text-primary') }}">
                                        Rp {{ number_format($tx['amount'], 2, ',', '.') }}
                                    </p>
                                </td>

                                {{-- Account --}}
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <p class="text-xs text-on-surface-variant">{{ $tx['account_name'] }}</p>
                                </td>

                                {{-- Status --}}
                                <td class="py-3.5 px-4 whitespace-nowrap text-right">
                                    @if($tx['status_display'] === 'Paid' || $tx['status_display'] === 'Completed')
                                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                                            {{ $tx['status_display'] }}
                                        </span>
                                    @elseif($tx['status_display'] === 'Unpaid')
                                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/30">
                                            Unpaid
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-surface-container-high text-on-surface-variant border border-outline-variant/60">
                                            Cancelled
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-12 text-center text-on-surface-variant">
                                    <div class="flex flex-col items-center justify-center max-w-sm mx-auto">
                                        <span class="material-symbols-outlined text-[36px] text-on-surface-variant/40 mb-2">receipt_long</span>
                                        <p class="text-sm font-medium text-on-surface">No transactions recorded for this period</p>
                                        <p class="text-xs text-on-surface-variant/70 mt-1">
                                            Transactions created within the selected date range will appear here automatically.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js CDN & Initialization Script -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const ctx = document.getElementById('financialTrendChart').getContext('2d');
        const chartData = @json($chartData);

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: [
                    {
                        label: 'Revenue',
                        data: chartData.revenue,
                        borderColor: '#35C98A',
                        backgroundColor: 'rgba(53, 201, 138, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true,
                    },
                    {
                        label: 'Total Expenses',
                        data: chartData.total_expenses,
                        borderColor: '#F05D63',
                        backgroundColor: 'rgba(240, 93, 99, 0.05)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true,
                    },
                    {
                        label: 'Net Profit',
                        data: chartData.net_profit,
                        borderColor: '#FFD044',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        borderDash: [4, 4],
                        tension: 0.3,
                        fill: false,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(context.parsed.y);
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(42, 53, 69, 0.5)'
                        },
                        ticks: {
                            color: '#A5AFBF',
                            font: { size: 11 }
                        }
                    },
                    y: {
                        grid: {
                            color: 'rgba(42, 53, 69, 0.5)'
                        },
                        ticks: {
                            color: '#A5AFBF',
                            font: { size: 11 },
                            callback: function(value) {
                                return 'Rp ' + (value / 1000).toLocaleString('id-ID') + 'k';
                            }
                        }
                    }
                }
            }
        });
    });
</script>
@endsection

