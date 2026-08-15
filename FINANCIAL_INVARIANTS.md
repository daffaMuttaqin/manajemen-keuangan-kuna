# FINANCIAL INVARIANTS — CANONICAL

These are extracted from PRD.md and are mandatory. Treat them as assertions that must remain true after every financial operation.

## INV-001 — Paid income cash effect
A Paid Active Income affects its account exactly once.

## INV-002 — Paid expense cash effect
A Paid Active Expense affects its account exactly once.

## INV-003 — Unpaid income
Unpaid income never changes current cash.

## INV-004 — Unpaid expense
Unpaid expense never changes current cash.

## INV-005 — Transfer conservation
A transfer changes account distribution but not total company Current Balance.

## INV-006 — [SCOPE CANCELLED] Loan receipt
(Loan module cancelled).

## INV-007 — [SCOPE CANCELLED] Loan repayment
(Loan module cancelled).

## INV-008 — Opening balance
Opening balances do not affect Revenue or Profit.

## INV-009 — Cancellation
Cancelled records do not contribute to active financial totals.

## INV-010 — Historical price
Changing a Menu item price never changes historical transaction prices.

## INV-011 — [SCOPE UPDATED] Payment Confirmation
Payment confirmation converts an unpaid transaction to paid status once without creating duplicate revenue/expense records.

## INV-012 — [SCOPE UPDATED] Unpaid transactions
Unpaid income/expense records never alter cash balance or profit metrics until confirmed paid.

## INV-013 — Idempotent payment
Repeating payment confirmation cannot duplicate the financial effect.

## INV-014 — Atomicity
Multi-record financial operations either fully succeed or fully fail.

## INV-015 — Transfer endpoints
Transfer source and destination must differ.

## INV-016 — Transfer balance safety
Ordinary transfers cannot create a negative source account balance.

## INV-017 — Asset treatment
Paid Asset category expense decreases cash balance when paid, but does NOT reduce Net Profit in V1.

## INV-018 — Server authority
Client-calculated amounts are never authoritative.

## INV-019 — Historical integrity
Financial history cannot be silently erased.

## INV-020 — Account lifecycle
Inactive accounts cannot be used for new financial transactions.

## Canonical financial effects

| Event | Cash/account | Revenue | COGS | OpEx | Net Profit |
|---|---|---|---|---|---|
| Opening Balance | + initial state | 0 | 0 | 0 | 0 |
| Unpaid Income | 0 | 0 | 0 | 0 | 0 |
| Paid Income | + | + | 0 | 0 | + |
| Unpaid Expense | 0 | 0 | 0 | 0 | 0 |
| Paid COGS Expense | - | 0 | + | 0 | - |
| Paid Profit-Eligible OpEx | - | 0 | 0 | + | - |
| Paid Asset Expense | - | 0 | 0 | 0 | 0 |
| Transfer | redistribution | 0 | 0 | 0 | 0 |
| Cancellation | reverse applicable prior effect | reverse | reverse | reverse | reverse |

## Formula invariants

`Current Balance = Sum of active account balances`

`Gross Profit = Revenue - COGS`

`Profit-Eligible Expenses = Sum of active paid expenses in [COGS, Operational, Marketing, Salary, Rent, Employee Salaries]`

`Net Profit = Revenue - Profit-Eligible Expenses`

## Required financial trace

For every new financial operation, document:
- source record;
- state before;
- state after;
- cash effect;
- reporting effect;
- audit event;
- rollback behavior.

