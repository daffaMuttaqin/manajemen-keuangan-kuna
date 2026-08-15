<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

    Route::get('/accounts', \App\Livewire\Accounts\ManageAccounts::class)->name('accounts.index');
    Route::get('/menu', \App\Livewire\Menu\ManageMenu::class)->name('menu.index');
    Route::get('/income', \App\Livewire\Income\ManageIncome::class)->name('income.index');
    Route::get('/expense', \App\Livewire\Expense\ManageExpense::class)->name('expense.index');
    Route::get('/transfers', \App\Livewire\Transfer\ManageTransfers::class)->name('transfers.index');

    Route::get('/reports', \App\Livewire\Reports\ManageReports::class)->name('reports.index');
    Route::get('/reports/export/{type}', [\App\Http\Controllers\ReportExportController::class, 'export'])->name('reports.export');

    Route::get('/audit-logs', \App\Livewire\AuditLogs\ManageAuditLogs::class)->name('audit-logs.index');
});


