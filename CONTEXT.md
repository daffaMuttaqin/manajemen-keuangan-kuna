# KUNA PATISSERIE — AI PROJECT CONTEXT

## Purpose

This file records the current implementation state for AI coding agents.

It is NOT a source of business requirements. PRD.md remains authoritative.

## Current project state

- Project status: Phase 5 Payment Confirmation Foundation completed and verified.
- Current phase: Phase 5 — Payment Logic (Completed).
- Current task: None.
- Laravel status: Configured & operational (v13.24.0).
- MySQL status: Configured (`db-manajemen-kuna` DB, `finance-testing` test DB).
- Authentication status: AuthenticatedSessionController, Login view, Logout, protected routes, and development admin seeder implemented.
- Test suite status: 148 feature/unit tests passing (8 Auth/Seeder, 10 Account, 14 Menu, 4 Dashboard, 2 Example, 35 Income, 38 Expense, 37 Payment Confirmation).

## Completed phases

- [x] Phase 1 — Foundation
- [x] Phase 2 — Accounts
- [x] Phase 3 — Menu
- [x] Phase 4 — Income (Income transactions: creation, server-side calculation, cancellation, account balance integration)
- [x] Phase 4 — Expense (Expense transactions: creation, server-side amount validation, cancellation, account balance deduction, dashboard integration)
- [x] Phase 5 — Payment Logic (Payment Confirmation Foundation: Income & Expense payment confirmation, atomic balance updates, receivable/payable reduction, idempotent protection, inactive account protection)
- [ ] Phase 6 — Cancellation and Editing
- [ ] Phase 7 — Transfers
- [ ] Phase 8 — Loans, Receivables, Payables, Assets
- [x] Dashboard UI (Phase 9 UI shell ready; KPI 1–3 now show live data)
- [ ] Phase 10 — Reports and CSV
- [ ] Phase 11 — Audit Trail
- [ ] Phase 12 — Stabilization

## Active task

None.

## Last verified tests

- `tests/Feature/AuthenticationTest.php` (8 tests passed).
- `tests/Feature/AccountTest.php` (10 tests passed).
- `tests/Feature/MenuTest.php` (14 tests passed).
- `tests/Feature/DashboardTest.php` (4 tests passed).
- `tests/Feature/ExampleTest.php` (1 test passed).
- `tests/Unit/ExampleTest.php` (1 test passed).
- `tests/Feature/IncomeTest.php` (35 tests passed).
- `tests/Feature/ExpenseTest.php` (38 tests passed).
- `tests/Feature/PaymentConfirmationTest.php` (37 tests passed).
- Full suite: 148 tests, 310 assertions — all green.

## Known issues

None.

## Decisions made during implementation

- Menu Management: Uses dark theme visual design tokens (`#16130e` background, `#e9c176` primary gold, `#231f1a` surfaces, `#4e4639` borders) embedded in `layouts.app` shell. Sourced 100% from actual `MenuItem` database model fields (`id`, `name`, `category`, `current_price`, `is_active`). Includes real-time database search, status filter pills (`All Items`, `Active`, `Inactive`), pagination, modal creation/edit, and activation toggle.

- Income (Phase 4): Uses integer-cents arithmetic (instead of bcmath or floating-point) for all monetary calculations in `IncomeCalculationService`. This avoids binary float errors AND avoids bcmath dependency. Pattern: convert to integer cents → do integer arithmetic → format back to 2-decimal string for DECIMAL(19,2) DB storage. Enforces: INV-001, INV-003, INV-009, INV-010, INV-013, INV-014, INV-018, INV-019, INV-020.

- Expense (Phase 4): Same integer-cents arithmetic pattern as Income. Expense schema deliberately excludes menu_item_id, quantity, discount fields (not applicable per PRD). Uses direct `amount` field. Expense DECREASES account balance. Unpaid expense creates outstanding payable obligation only. Enforces: INV-002, INV-004, INV-009, INV-012, INV-014, INV-018, INV-019, INV-020.

- Dashboard (updated Phase 4): KPI 2 (Total Revenue) now shows live IncomeCalculationService::calculateRevenue() data. KPI 3 (Total Expenses) now shows live ExpenseCalculationService::calculateTotalExpenses() data. Net Cash Flow (KPI 4) remains Phase 4+ placeholder because correct Net Financial Position requires Phase 7 (transfers) and Phase 8 (loans) data.

## AI handoff notes

- Completed task: Phase 4 Expense Management module implementation, dashboard integration, and test verification.
- Files created by Expense task:
  - `database/migrations/2026_08_12_060000_create_expense_transactions_table.php`
  - `app/Models/ExpenseTransaction.php`
  - `app/Services/ExpenseCalculationService.php`
  - `app/Livewire/Expense/ManageExpense.php`
  - `resources/views/livewire/expense/manage-expense.blade.php`
  - `tests/Feature/ExpenseTest.php`
- Files modified by Expense task:
  - `app/Services/AccountBalanceService.php` (added expense deduction via ExpenseTransaction sum)
  - `app/Http/Controllers/DashboardController.php` (added IncomeCalculationService + ExpenseCalculationService injection)
  - `resources/views/dashboard.blade.php` (connected KPI 2/3 to real data)
  - `resources/views/layouts/app.blade.php` (activated Expense sidebar link)
  - `routes/web.php` (added /expense route)
- Next task: Phase 5 — Payment Logic (Wait for user instruction).
