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

## INV-006 — Loan receipt
Loan principal does not affect Revenue.

## INV-007 — Loan repayment
Loan principal repayment does not affect Net Profit.

## INV-008 — Opening balance
Opening balances do not affect Revenue or Profit.

## INV-009 — Cancellation
Cancelled records do not contribute to active financial totals.

## INV-010 — Historical price
Changing a Menu item price never changes historical transaction prices.

## INV-011 — Receivable uniqueness
A receivable never creates additional Revenue.

## INV-012 — Payable uniqueness
A payable never creates additional Expense.

## INV-013 — Idempotent payment
Repeating payment confirmation cannot duplicate the financial effect.

## INV-014 — Atomicity
Multi-record financial operations either fully succeed or fully fail.

## INV-015 — Transfer endpoints
Transfer source and destination must differ.

## INV-016 — Transfer balance safety
Ordinary transfers cannot create a negative source account balance.

## INV-017 — Asset treatment
Asset purchase does not reduce Net Profit in V1.

## INV-018 — Server authority
Client-calculated amounts are never authoritative.

## INV-019 — Historical integrity
Financial history cannot be silently erased.

## INV-020 — Account lifecycle
Inactive accounts cannot be used for new financial transactions.

## Canonical financial effects

| Event | Cash/account | Revenue | COGS | OpEx | Profit | Receivable | Payable | Loan |
|---|---|---|---|---|---|---|---|---|
| Opening Balance | + initial state | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| Unpaid Income | 0 | 0 | 0 | 0 | 0 | + | 0 | 0 |
| Paid Income | + | + | 0 | 0 | + | settle | 0 | 0 |
| Unpaid COGS | 0 | 0 | 0 | 0 | 0 | 0 | + | 0 |
| Paid COGS | - | 0 | + | 0 | - | 0 | settle | 0 |
| Unpaid OpEx | 0 | 0 | 0 | 0 | 0 | 0 | + | 0 |
| Paid OpEx | - | 0 | 0 | + | - | 0 | settle | 0 |
| Asset Purchase | - | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| Transfer | redistribution | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| Loan Received | + | 0 | 0 | 0 | 0 | 0 | 0 | + |
| Loan Principal Repayment | - | 0 | 0 | 0 | 0 | 0 | 0 | - |
| Cancellation | reverse applicable prior effect | reverse | reverse | reverse | reverse | reverse | reverse | reverse where applicable |

## Formula invariants

`Current Balance = Sum of active account balances`

`Gross Profit = Revenue - COGS`

`Operating Expense = Operational + Marketing + Rent + Employee Salaries + Other`

`Net Profit = Gross Profit - Operating Expense`

`Net Financial Position = Current Balance + Outstanding Receivables - Outstanding Payables - Outstanding Loan Principal`

## Required financial trace

For every new financial operation, document:
- source record;
- state before;
- state after;
- cash effect;
- reporting effect;
- obligation effect;
- liability effect;
- audit event;
- rollback behavior.
