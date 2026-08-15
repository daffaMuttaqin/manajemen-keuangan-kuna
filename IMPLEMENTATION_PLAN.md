# IMPLEMENTATION PLAN

Execute phases sequentially. Do not implement future phases early unless a dependency is strictly required and documented.

## Phase 1 — Foundation
Objective: Laravel project foundation.
Features: Laravel setup, MySQL, Tailwind, Livewire, authentication, base layout.
Acceptance: admin can log in and log out.
Tests: authentication/foundation.

## Phase 2 — Accounts
Features: account table, CRUD, opening balances, active/inactive, balances.
Tests: creation, opening balance, inactive account, balance calculation.

## Phase 3 — Menu
Features: menu CRUD, current price, active/inactive, search, derived sales statistics.
Tests: price changes, historical price preservation, statistics.

## Phase 4 — Income and Expense
Features: forms, validation, server-side calculation, categories, statuses.
Tests: totals, discounts, categories, unpaid records.

## Phase 5 — Payment Logic
Features: Transaction Approvals/payment confirmation, unpaid -> paid, account effects, receivable/payable linkage, audit.
Tests: payment, duplicate payment, atomicity, audit.

## Phase 6 — Cancellation and Editing
Features: edit, cancellation, financial reversal, audit.
Tests: paid edits, unpaid edits, cancellation, rollback.

## Phase 7 — Transfers
Features: transfer form, account movement, insufficient balance, concurrency protection.
Tests: success, same account, insufficient balance, concurrent transfer.

## Phase 8 — [CANCELLED / SCOPE REMOVED] Loans, Receivables, Payables, Assets
Status: CANCELLED.
Note: Separate modules for Loans, Receivables, Payables, and Asset inventory tracking have been cancelled per user decision. The `Asset` Expense category remains supported under Phase 4/6 Expense transactions.

## Phase 9 — Dashboard
Features: KPI cards (Current Balance, Total Revenue, Total Expenses, Net Profit), account balances, cash-flow chart, recent transactions.
Tests: every KPI formula, date ranges, empty state.

## Phase 10 — Reports and CSV
Features: filters, search, pagination, sorting, CSV export.
Tests: filtered data, date range, status, CSV contents.

## Phase 11 — Audit Trail
Features: audit logging and history review.
Tests: creation, edits, payment, cancellation, account/menu/transfer changes.

## Phase 12 — Stabilization
Activities: full test suite, financial reconciliation, security review, responsive testing, database integrity review, duplicate-submission testing, performance testing, UI polish.
Rule: no new features during stabilization unless required for correctness.

## Phase gate

A phase is complete only when:
- feature behavior is implemented;
- acceptance criteria pass;
- relevant tests pass;
- affected invariants pass;
- no known unresolved financial correctness issue remains;
- implementation report is recorded.

Do not begin the next phase while the current phase has unresolved financial correctness failures.
