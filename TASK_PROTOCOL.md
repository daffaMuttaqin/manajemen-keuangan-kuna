# TASK PROTOCOL — SAFE AI CODING LOOP

Use this protocol for every implementation task.

## Step 1 — Read
Read:
- PRD.md
- AI_RULES.md
- FINANCIAL_INVARIANTS.md
- IMPLEMENTATION_PLAN.md
- current CONTEXT.md

Read the relevant existing code before editing.

## Step 2 — Scope
State:
- current phase;
- exact task;
- files likely affected;
- PRD sections governing the task;
- dependencies;
- explicit non-goals.

Do not start coding until scope is bounded.

## Step 3 — Financial preflight
If the task is financial, answer all 13 questions from AI_RULES.md.

Create a concise before/after financial trace.

## Step 4 — Plan
Describe:
- data changes;
- business logic;
- UI changes;
- validation;
- transaction/locking requirements;
- audit requirements;
- tests.

Do not invent requirements.

## Step 5 — Implement
Modify only the required code.
Reuse existing components/services where appropriate.
Keep business logic explicit.
Avoid unrelated refactoring.

## Step 6 — Test
Run:
- focused tests;
- invariant tests affected by the task;
- broader suite as appropriate.

If a test fails, diagnose the cause. Never weaken an invariant merely to make the test pass.

## Step 7 — Review
Review the diff for:
- duplicate financial effects;
- missing transaction boundaries;
- race conditions;
- client-trust errors;
- incorrect status transitions;
- historical-data mutation;
- missing audit events;
- unrelated changes.

## Step 8 — Report
Record:
- what changed;
- files changed;
- tests run/results;
- invariants verified;
- known limitations;
- unresolved questions;
- next recommended task.

## Stop conditions

Stop instead of guessing if:
- PRD requirements conflict;
- a financial effect is undefined;
- the authoritative source is unclear;
- an existing implementation contradicts the PRD;
- a required dependency is missing;
- a migration could destroy historical meaning;
- a test exposes a financial invariant violation that is not understood.

## Prohibited behavior

Never:
- implement multiple unrelated phases at once;
- silently invent business rules;
- silently alter formulas;
- delete financial history;
- trust client totals;
- bypass tests;
- hide failures;
- claim completion without running relevant verification.
