# KUNA PATISSERIE — AI DEVELOPMENT GUIDE

## Start here

Before using an AI coding agent, ensure these files exist in the repository:

1. `PRD.md`
2. `AI_RULES.md`
3. `FINANCIAL_INVARIANTS.md`
4. `FINANCIAL_TEST_MATRIX.md`
5. `IMPLEMENTATION_PLAN.md`
6. `TASK_PROTOCOL.md`
7. `CONTEXT.md`

## Required reading order

For every task:

`PRD.md -> AI_RULES.md -> FINANCIAL_INVARIANTS.md -> IMPLEMENTATION_PLAN.md -> TASK_PROTOCOL.md -> CONTEXT.md -> relevant source code`

## Important

The AI must not treat the companion files as a license to reinterpret the PRD.

If there is conflict:
- stop;
- identify the exact conflict;
- cite the relevant PRD sections;
- ask for a decision.

## Recommended development style

Use one bounded task at a time.

Example:
- Phase 2, Task A: create Account migration/model.
- Verify tests.
- Phase 2, Task B: account CRUD.
- Verify tests.
- Phase 2, Task C: account balance behavior.
- Verify tests.

Do not ask the agent to build the entire application in one prompt.

## Financial rule

The application is cash-basis management reporting, not statutory double-entry accounting.

Keep:
- cash;
- Revenue;
- Expense;
- receivable;
- payable;
- loan;
- transfer;
- asset

conceptually distinct.

## Completion standard

"Implemented" means code + tests + verification, not merely files generated.
