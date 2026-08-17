<!DOCTYPE html>
<html class="dark" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Kuna Patisserie Finance') }}</title>

    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@100..700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-background text-on-surface font-inter min-h-screen antialiased flex flex-col md:flex-row">

    <!-- Mobile Header -->
    <header class="md:hidden bg-surface-container-low border-b border-outline-variant p-4 flex items-center justify-between z-30">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded bg-primary-container flex items-center justify-center font-bold text-on-primary-container">
                KF
            </div>
            <span class="font-semibold text-primary">Kuna Finance</span>
        </div>
        <button id="mobileMenuBtn" aria-label="Toggle navigation menu" class="p-2 text-on-surface-variant hover:text-primary focus:outline-none">
            <span class="material-symbols-outlined text-[24px]">menu</span>
        </button>
    </header>

    <!-- Sidebar Navigation -->
    <aside id="sidebarNav" class="fixed inset-y-0 left-0 z-40 w-64 bg-surface-container-low border-r border-outline-variant transform -translate-x-full md:translate-x-0 transition-transform duration-200 ease-in-out flex flex-col justify-between md:static md:inset-auto">
        <div class="p-5">
            <!-- Brand -->
            <div class="flex items-center gap-3 mb-6 pb-4 border-b border-outline-variant/50">
                <!-- <div class="w-10 h-10 rounded bg-primary-container flex items-center justify-center font-bold text-lg text-on-primary-container shadow-sm">
                    KF
                </div> -->
                <div class="w-10 h-10 rounded flex items-center justify-center overflow-hidden shadow-sm">
    <img
        src="{{ asset('images/logo-kuna.jpeg') }}"
        alt="Kuna Patisserie"
        class="w-full h-full object-cover"
    >
</div>
                <div>
                    <h1 class="font-bold text-primary text-base leading-tight">Kuna Patisserie</h1>
                    <p class="text-xs text-on-surface-variant">Financial Operations</p>
                </div>
            </div>

            <!-- New Transaction Button (Placeholder) -->
            <div class="mb-6">
                <a href="{{ route('income.index') }}"
                   class="w-full py-2.5 px-3 bg-primary/10 border border-primary/30 text-primary rounded text-sm font-medium flex items-center justify-between hover:bg-primary/20 transition">
                    <span class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">add_circle</span>
                        Record Income
                    </span>
                    <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                </a>
            </div>

            <!-- Main Navigation Links -->
            <nav class="space-y-1">
                <p class="px-3 text-[11px] font-semibold text-on-surface-variant/60 uppercase tracking-wider mb-2">Main Menu</p>
                
                <a href="{{ route('dashboard') }}" 
                   class="flex items-center gap-3 px-3 py-2.5 rounded text-sm font-medium transition {{ request()->routeIs('dashboard') ? 'bg-primary-container/20 text-primary border-l-2 border-primary' : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high' }}">
                    <span class="material-symbols-outlined text-[20px]">dashboard</span>
                    Dashboard
                </a>

                <a href="{{ route('accounts.index') }}" 
                   class="flex items-center gap-3 px-3 py-2.5 rounded text-sm font-medium transition {{ request()->routeIs('accounts.*') ? 'bg-primary-container/20 text-primary border-l-2 border-primary' : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high' }}">
                    <span class="material-symbols-outlined text-[20px]">account_balance</span>
                    Accounts
                </a>

                <a href="{{ route('menu.index') }}" 
                   class="flex items-center gap-3 px-3 py-2.5 rounded text-sm font-medium transition {{ request()->routeIs('menu.*') ? 'bg-primary-container/20 text-primary border-l-2 border-primary' : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high' }}">
                    <span class="material-symbols-outlined text-[20px]">restaurant_menu</span>
                    Menu Items
                </a>

                <!-- Future Modules (Visually present, disabled) -->
                <p class="px-3 text-[11px] font-semibold text-on-surface-variant/60 uppercase tracking-wider mt-6 mb-2">Financial Modules</p>

                <a href="{{ route('income.index') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded text-sm font-medium transition {{ request()->routeIs('income.*') ? 'bg-primary-container/20 text-primary border-l-2 border-primary' : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high' }}">
                    <span class="material-symbols-outlined text-[20px]">trending_up</span>
                    Income
                </a>

                <a href="{{ route('expense.index') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded text-sm font-medium transition {{ request()->routeIs('expense.*') ? 'bg-primary-container/20 text-primary border-l-2 border-primary' : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high' }}">
                    <span class="material-symbols-outlined text-[20px]">trending_down</span>
                    Expense
                </a>

                <a href="{{ route('transfers.index') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded text-sm font-medium transition {{ request()->routeIs('transfers.*') ? 'bg-primary-container/20 text-primary border-l-2 border-primary' : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high' }}">
                    <span class="material-symbols-outlined text-[20px]">swap_horiz</span>
                    Transfers
                </a>

                <a href="{{ route('reports.index') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded text-sm font-medium transition {{ request()->routeIs('reports.*') ? 'bg-primary-container/20 text-primary border-l-2 border-primary' : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high' }}">
                    <span class="material-symbols-outlined text-[20px]">assessment</span>
                    Reports
                </a>

                <a href="{{ route('audit-logs.index') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded text-sm font-medium transition {{ request()->routeIs('audit-logs.*') ? 'bg-primary-container/20 text-primary border-l-2 border-primary' : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high' }}">
                    <span class="material-symbols-outlined text-[20px]">history</span>
                    Audit Trail
                </a>
            </nav>
        </div>

        <!-- Sidebar Footer User Profile & Logout -->
        @auth
        <div class="p-4 border-t border-outline-variant/60 bg-surface-container">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5 truncate">
                    <div class="w-8 h-8 rounded-full bg-surface-container-high border border-outline-variant flex items-center justify-center font-bold text-xs text-primary">
                        {{ strtoupper(substr(auth()->user()->name ?? auth()->user()->email, 0, 1)) }}
                    </div>
                    <div class="truncate">
                        <p class="text-xs font-semibold text-on-surface truncate">{{ auth()->user()->name ?? auth()->user()->email }}</p>
                        <p class="text-[10px] text-on-surface-variant truncate">{{ auth()->user()->email }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" title="Logout" class="p-1.5 text-on-surface-variant hover:text-error transition rounded hover:bg-surface-container-high">
                        <span class="material-symbols-outlined text-[20px]">logout</span>
                    </button>
                </form>
            </div>
        </div>
        @endauth
    </aside>

    <!-- Mobile Overlay -->
    <div id="mobileOverlay" class="fixed inset-0 bg-black/60 z-30 hidden md:hidden"></div>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0 min-h-screen bg-background">
        <!-- Top Navbar -->
        <header class="bg-surface-container-low border-b border-outline-variant px-4 md:px-8 py-3.5 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <span class="text-sm font-semibold text-primary">Kuna Finance</span>
                <span class="text-xs text-outline-variant hidden sm:inline">|</span>
                <span class="text-xs text-on-surface-variant hidden sm:inline">Internal Accounting & Financial Operations</span>
            </div>

            <div class="flex items-center gap-4">
                <div class="hidden md:flex items-center gap-3 text-xs text-on-surface-variant border-r border-outline-variant pr-4">
                    <span class="hover:text-on-surface cursor-pointer">Docs</span>
                    <span class="hover:text-on-surface cursor-pointer">Support</span>
                </div>
                <button type="button" title="Notifications" class="text-on-surface-variant hover:text-primary transition p-1">
                    <span class="material-symbols-outlined text-[20px]">notifications</span>
                </button>
                <button type="button" title="Help & Info" class="text-on-surface-variant hover:text-primary transition p-1">
                    <span class="material-symbols-outlined text-[20px]">help_outline</span>
                </button>
            </div>
        </header>

        <!-- Main Page Container -->
        <main class="flex-1 p-4 md:p-8 overflow-y-auto">
            @if (isset($slot))
                {{ $slot }}
            @else
                @yield('content')
            @endif
        </main>
    </div>

    @livewireScripts

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebarNav = document.getElementById('sidebarNav');
            const mobileOverlay = document.getElementById('mobileOverlay');

            if (mobileMenuBtn && sidebarNav && mobileOverlay) {
                mobileMenuBtn.addEventListener('click', function () {
                    sidebarNav.classList.toggle('-translate-x-full');
                    mobileOverlay.classList.toggle('hidden');
                });

                mobileOverlay.addEventListener('click', function () {
                    sidebarNav.classList.add('-translate-x-full');
                    mobileOverlay.classList.add('hidden');
                });
            }
        });
    </script>
</body>
</html>
