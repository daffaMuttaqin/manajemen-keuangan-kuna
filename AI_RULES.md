# AI CODING RULES — KUNA PATISSERIE

## 1. Authority

1. `PRD.md` is the source of truth.
2. Never invent financial rules.
3. Never silently alter formulas.
4. Never resolve conflicting requirements by guesswork.
5. If a requirement is ambiguous or contradictory, stop and report the exact conflict.
6. Companion files organize PRD requirements; they do not override them.

## 2. Financial safety — absolute rules

1. Paid Active Income affects its account exactly once.
2. Paid Active Expense affects its account exactly once.
3. Unpaid income never changes current cash.
4. Unpaid expense never changes current cash.
5. Transfers change account distribution but not total company Current Balance.
6. Loan principal never becomes Revenue.
7. Loan principal repayment never becomes ordinary Expense.
8. Opening balance never becomes Revenue or Profit.
9. Cancelled records do not contribute to active totals.
10. Menu price changes never alter historical transaction prices.
11. Receivables never create additional Revenue.
12. Payables never create additional Expense.
13. Repeated payment confirmation cannot duplicate financial effects.
14. Multi-record financial operations either fully succeed or fully fail.
15. Transfer source and destination must differ.
16. Ordinary transfers cannot create a negative source balance.
17. Asset purchase does not reduce Net Profit in V1.
18. Client-calculated amounts are never authoritative.
19. Financial history cannot be silently erased.
20. Inactive accounts cannot be used for new financial transactions.

## 3. Monetary correctness

- Never use floating-point arithmetic for authoritative money calculations.
- Use decimal database types.
- Recalculate monetary values server-side.
- Retrieve current database values before calculating.
- Never trust client-provided totals, balances, profit, or authoritative status.
- Preserve original unit prices on historical income transactions.

## 4. Financial operations

For payment confirmation, cancellation, paid edits, transfers, loan movements, and other multi-record financial operations:
- use database transactions;
- use appropriate row locking/concurrency protection;
- validate current state;
- make the operation idempotent where applicable;
- audit important changes;
- ensure failure rolls back the entire operation.

## 5. Codebase discipline

Before coding:
- inspect the existing codebase;
- identify existing models/components/services;
- reuse existing implementations;
- follow project naming conventions;
- avoid duplicate implementations.

While coding:
- implement one bounded feature at a time;
- keep business logic explicit;
- keep relationships explicit;
- keep dependencies minimal;
- prefer Laravel-native functionality;
- do not refactor unrelated code;
- do not add speculative features;
- preserve existing behavior unless the task explicitly changes it.

Do not introduce:
- React
- separate frontend application
- microservices
- unnecessary APIs
- unnecessary queues/jobs
- unnecessary repositories
- unnecessary event buses
- generic abstractions without a concrete use case.

## 6. Testing

Every financial behavior change must include or update tests.

Never weaken an invariant to make a test pass.

A successful task is not "code written"; it is:
- implementation complete;
- relevant tests pass;
- affected invariants verified;
- no unrelated regressions identified.

## 7. Required financial preflight

Before implementing any financial feature, answer:

1. What financial event is occurring?
2. Which record is authoritative?
3. Does cash change?
4. Does Revenue change?
5. Does Expense change?
6. Does Profit change?
7. Does a receivable change?
8. Does a payable change?
9. Does a loan liability change?
10. Does the operation need a database transaction?
11. Can it be submitted twice?
12. What audit event is created?
13. What happens if it fails halfway?

If these cannot be answered from PRD.md, do not guess.

## 8. Completion report

After each task, report:
- files changed;
- business behavior implemented;
- tests added/changed;
- tests run and result;
- financial invariants affected;
- invariants verified;
- assumptions, ambiguities, or unresolved issues;
- unrelated work intentionally not performed.
