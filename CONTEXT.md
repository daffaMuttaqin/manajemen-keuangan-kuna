# KUNA PATISSERIE — AI PROJECT CONTEXT

## Purpose

This file records the current implementation state for AI coding agents.

It is NOT a source of business requirements. PRD.md remains authoritative.

## Current project state

- Project status: Phase 10 Reports and CSV completed and verified.
- Current phase: Phase 10 — Reports and CSV (Completed).
- Current task: None.
- Laravel status: Configured & operational (v13.24.0).
- MySQL status: Configured (`db-manajemen-keuangan-kuna` DB, `finance-testing` test DB).
- Authentication status: AuthenticatedSessionController, Login view, Logout, protected routes, and development admin seeder implemented.
- Test suite status: 221 feature/unit tests passing (8 Auth/Seeder, 10 Account, 14 Menu, 10 Dashboard, 2 Example, 35 Income, 43 Expense, 37 Payment Confirmation, 40 Phase 6 Cancellation/Editing & Audit Foundation, 10 Phase 7 Transfers, 12 Phase 10 Reports & CSV).

## Completed phases

- [x] Phase 1 — Foundation
- [x] Phase 2 — Accounts
- [x] Phase 3 — Menu
- [x] Phase 4 — Income (Income transactions: creation, server-side calculation, cancellation, account balance integration)
- [x] Phase 4 — Expense (Expense transactions: creation, server-side amount validation, cancellation, account balance deduction, dashboard integration)
- [x] Phase 5 — Payment Logic (Payment Confirmation Foundation: Income & Expense payment confirmation, atomic balance updates, receivable/payable reduction, idempotent protection, inactive account protection)
- [x] Phase 6 — Cancellation, Editing, and Minimal Audit Recording Foundation (Edit/cancel income and expense transactions, financial reversal on cancellation, invalid-transition protection, paid-amount-difference application, paid-account-change atomicity, row-level concurrency locking, minimal AuditLog recording in same DB transaction)
- [x] Phase 7 — Transfers (Internal fund transfers between active company accounts, strict balance conservation, deterministic deadlock-free row locking, insufficient balance protection, cancellation, audit log integration, unique transfer_id idempotency)
- [-] Phase 8 — [CANCELLED / SCOPE REMOVED] Loans, Receivables, Payables, Assets (Cancelled per user decision; Asset Expense category retained under Expense module)
- [x] Phase 9 — Dashboard UI (KPI cards, native transaction feeds, date range filtering, Chart.js trend curves)
- [x] Phase 10 — Reports and CSV (Financial Summary P&L, Income, Expense, Transfer detailed reports, category breakdown, chunked streamed CSV export)
- [ ] Phase 11 — Audit Trail (Full UI and history review functionality remaining)
- [ ] Phase 12 — Stabilization

## Active task

None.

## Last verified tests

- `tests/Feature/AuthenticationTest.php` (8 tests passed).
- `tests/Feature/AccountTest.php` (10 tests passed).
- `tests/Feature/MenuTest.php` (14 tests passed).
- `tests/Feature/DashboardTest.php` (10 tests passed).
- `tests/Feature/ExampleTest.php` (1 test passed).
- `tests/Unit/ExampleTest.php` (1 test passed).
- `tests/Feature/IncomeTest.php` (35 tests passed).
- `tests/Feature/ExpenseTest.php` (43 tests passed).
- `tests/Feature/PaymentConfirmationTest.php` (37 tests passed).
- `tests/Feature/Phase6CancellationAndEditingTest.php` (40 tests passed — includes 6 audit foundation tests).
- `tests/Feature/TransferTest.php` (10 tests passed).
- `tests/Feature/ReportTest.php` (12 tests passed).
- Full suite: 221 tests, 567 assertions — all green. Verified 2026-08-15.

## Known issues

- **Full Audit Trail UI — Deferred to Phase 11**: Minimal audit recording foundation (`AuditLog` migration, model, and `AuditLogService::record()`) is implemented and active for Phase 6 financial mutations and Phase 7 transfers inside DB transaction boundaries. The user-facing Audit Trail UI and history review features remain intentionally deferred to Phase 11 per IMPLEMENTATION_PLAN.md.

## Decisions made during implementation

- Menu Management: Uses dark theme visual design tokens (`#16130e` background, `#e9c176` primary gold, `#231f1a` surfaces, `#4e4639` borders) embedded in `layouts.app` shell. Sourced 100% from actual `MenuItem` database model fields (`id`, `name`, `category`, `current_price`, `is_active`). Includes real-time database search, status filter pills (`All Items`, `Active`, `Inactive`), pagination, modal creation/edit, and activation toggle.

- Income (Phase 4): Uses integer-cents arithmetic (instead of bcmath or floating-point) for all monetary calculations in `IncomeCalculationService`. Enforces: INV-001, INV-003, INV-009, INV-010, INV-013, INV-014, INV-018, INV-019, INV-020.

- Expense (Phase 4): Same integer-cents arithmetic pattern as Income. Enforces: INV-002, INV-004, INV-009, INV-012, INV-014, INV-018, INV-019, INV-020. Excludes `'Asset'` category expenses from profit-eligible calculations.

- Dashboard: KPI 1 (Current Balance) shows active account total balance. KPI 2 (Total Revenue) shows live `calculateRevenue()` data. KPI 3 (Total Expenses) shows live `calculateTotalExpenses()` data. KPI 4 (Net Profit) shows live `Net Profit = Revenue - Profit-Eligible Expenses` data (`ExpenseCalculationService::calculateProfitEligibleExpenses()`).

- Phase 6 — Cancellation, Editing, and Minimal Audit Recording Foundation: All editing and cancellation flows are implemented in `IncomeCalculationService` and `ExpenseCalculationService` with row locking (`lockForUpdate()`), status checks, dynamic SUM balance calculation, and `AuditLogService` integration.

- Phase 7 — Transfers: Implemented in `TransferService::createTransfer()` and `cancelTransfer()`. Enforces: (1) `transfer_id` unique UUID constraint for idempotency (FT-022), (2) deterministic lock ordering (`min($fromId, $toId)` then `max($fromId, $toId)`) to eliminate deadlocks, (3) balance check AFTER both account locks are acquired, (4) `from_account_id !== to_account_id` validation (INV-015, FT-009), (5) active status checks on both accounts (INV-020), (6) `AccountBalanceService::calculateBalance()` integration adding incoming and subtracting outgoing active transfers so total company balance is conserved (INV-005, FT-008), (7) `AuditLog` creation inside the DB transaction (FT-030).

- Phase 8 Scope Cancelled: Separate modules for Loans, Receivables, Payables, and Asset Management were cancelled per user decision. The `Asset` Expense category remains in place.

## AI handoff notes

- Completed task: Scope cleanup for Phase 8 cancellation.
- Files updated: `PRD.md`, `FINANCIAL_INVARIANTS.md`, `FINANCIAL_TEST_MATRIX.md`, `IMPLEMENTATION_PLAN.md`, `CONTEXT.md`.
- Next step: Wait for user instruction for Phase 10 (Reports and CSV) or Phase 11 (Audit Trail).


