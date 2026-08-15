# KUNA PATISSERIE FINANCE MANAGEMENT

**Status:** Source of Truth  
**Version:** 1.1  
**Role:** Product, accounting, architecture, database, UX, security, testing, and implementation requirements.

This document remains the canonical product specification. The companion AI files do not replace business requirements in this PRD. They reorganize and operationalize requirements from the PRD so coding agents can consume them more reliably.

## Source-of-truth hierarchy

1. `PRD.md` — canonical product/business requirements.
2. `FINANCIAL_INVARIANTS.md` — extracted canonical financial invariants from PRD.
3. `FINANCIAL_TEST_MATRIX.md` — extracted mandatory verification cases from PRD.
4. `IMPLEMENTATION_PLAN.md` — execution order derived from PRD phases.
5. `AI_RULES.md` — mandatory coding-agent behavior derived from PRD.
6. `TASK_PROTOCOL.md` — procedure for executing one bounded task safely.
7. `CONTEXT.md` — current implementation state; never overrides PRD requirements.

If any companion file conflicts with PRD.md, stop and report the conflict. Do not silently choose.

## Product definition

A simple internal cash-basis finance management application for Kuna Patisserie that tracks money, income, expenses, transfers, account balances, profitability, and financial history without becoming a full accounting ERP.

## Technology

- Backend: Laravel
- Database: MySQL
- Frontend: Blade + Livewire + Tailwind CSS + Alpine.js
- Charting: lightweight chart library; Chart.js recommended
- CSV: native Laravel/PHP preferred
- No React
- No separate frontend application
- No microservices

## V1 accounting model

- Paid Income -> cash inflow + Revenue + Gross Profit + Net Profit
- Unpaid Income -> unpaid record only; does not change cash or Revenue
- Paid Expense (Profit-Eligible) -> cash outflow + Expense + reduces Net Profit
- Paid Expense (Asset) -> cash outflow + Expense record; decreases cash balance when paid, but does NOT reduce Net Profit or Gross Profit
- Unpaid Expense -> unpaid record only; does not change cash or Expense
- Transfer -> account redistribution; no Revenue/Expense/Profit effect
- Opening balance -> initial account state; no Revenue/Profit effect
- Cancelled records remain in history but are excluded from active totals.

## Expense model & categories

Expense transactions require:
- `transaction_name`: string (required) — dedicated name/title for the expense
- `description`: text (optional) — notes or additional details
- `expense_category`: one of:
  - Profit-Eligible Categories (reduces Net Profit): `COGS / Cake Production` (or `COGS`), `Operational`, `Marketing`, `Salary`, `Rent`, `Employee Salaries`
  - Non-Profit-Eligible Category: `Asset` (decreases cash balance when paid, but excluded from Net Profit calculation)

Table Display Format:
`Date | Transaction Name | Category / Description | Amount | Account | Status | Actions`

## Core formulas

`Current Balance = Sum of active company account balances`

`Gross Profit = Revenue - COGS`

`Profit-Eligible Expenses = Sum of active paid expenses in [COGS, Operational, Marketing, Salary, Rent, Employee Salaries]`

`Net Profit = Revenue - Profit-Eligible Expenses`

Revenue = active paid income in the selected reporting period.

COGS = active paid expenses categorized `COGS / Cake Production` (or `COGS`).

Dashboard KPI Card 4: Renamed to `Net Profit` and calculated using `Net Profit = Revenue - Profit-Eligible Expenses`. Separate cash flow charts remain cash flow charts.

## Critical lifecycle rules

Payment status:
- Unpaid
- Paid

Record status:
- Active
- Cancelled

They are separate concepts.

Recommended V1:
- Do not expose Paid -> Unpaid as a normal UI action.
- Use cancellation and corrected replacement where appropriate.
- Payment confirmation must be idempotent.
- Financial operations must be atomic.
- Paid transaction edits must calculate and apply financial differences atomically.
- Financial history must not be silently erased.

## V1 scope

Authentication, accounts, menu, income, expense, payment confirmation, cancellation/editing, transfers, dashboard, reports, CSV export, audit history, and financial correctness tests.

## Explicit non-goals

No loans, no loan repayments, no dedicated receivables/payables tracking modules, no separate asset inventory tracking, no partial payments, loan interest/amortization, depreciation schedules, tax filing, inventory, CRM, supplier CRM, invoice generation, purchase orders, bank integration, payment gateway, automated reconciliation, advanced forecasting, AI financial advice, multi-company, multi-user roles, approval workflow, native mobile app, PDF export, double-entry bookkeeping.

## Database/domain model

Separate domain tables are required rather than a generic polymorphic transaction mega-table.

Core models:
- User
- Account
- MenuItem
- IncomeTransaction
- ExpenseTransaction
- Transfer
- AuditLog

Use decimal monetary storage, foreign keys, appropriate indexes, explicit relationships, and no hard deletion of financial history.

## Architecture principles

Prefer:
- migrations
- Eloquent
- Form Requests
- Policies
- database transactions
- route model binding
- validation
- Blade
- Livewire
- explicit services/actions for complex financial operations

Recommended services:
- AccountBalanceService
- IncomeCalculationService
- ExpenseCalculationService
- PaymentConfirmationService
- TransactionCancellationService
- TransferService
- FinancialReportService
- AuditLogService

Do not create services/repositories/abstractions merely for trivial CRUD.

## Testing priority

Financial correctness first. Required test categories include unit, feature, validation, financial calculation, database, authorization, transaction integrity, balance calculation, CSV, cancellation, duplicate submission, and race-condition-sensitive tests.

The PRD's Financial Consistency Matrix is the canonical financial behavior reference.
