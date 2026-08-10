# KUNA PATISSERIE — AI PROJECT CONTEXT

## Purpose

This file records the current implementation state for AI coding agents.

It is NOT a source of business requirements. PRD.md remains authoritative.

## Current project state

- Project status: Phase 2 — Accounts and Opening Balance completed.
- Current phase: Phase 2 — Accounts (Completed)
- Current task: Phase 2 Implementation finished.
- Laravel status: Configured & operational (v13.24.0).
- MySQL status: Configured (`db-manajemen-kuna` DB, `finance-testing` test DB).
- Authentication status: AuthenticatedSessionController, Login view, Logout, and protected route implemented.
- Test suite status: 16 focused feature tests passing (6 Auth, 10 Account).

## Completed phases

- [x] Phase 1 — Foundation
- [x] Phase 2 — Accounts
- [ ] Phase 3 — Menu
- [ ] Phase 4 — Income and Expense
- [ ] Phase 5 — Payment Logic
- [ ] Phase 6 — Cancellation and Editing
- [ ] Phase 7 — Transfers
- [ ] Phase 8 — Loans, Receivables, Payables, Assets
- [ ] Phase 9 — Dashboard
- [ ] Phase 10 — Reports and CSV
- [ ] Phase 11 — Audit Trail
- [ ] Phase 12 — Stabilization

## Active task

None.

## Last verified tests

- `tests/Feature/AuthenticationTest.php` (6 tests passed, 16 assertions).

## Known issues

None.

## Decisions made during implementation

- Auth Controller: Standard Laravel-native `AuthenticatedSessionController` handling login view, store authentication, and logout session destruction.
- Database: MySQL configured for both main application database (`db-manajemen-kuna`) and test database (`finance-testing`).
- UI: Clean minimal login page, base authenticated layout (`layouts.app`), and dashboard placeholder (`/dashboard`).

## AI handoff notes

- Completed task: Phase 2 Accounts and Opening Balance.
- Files created/modified:
  - `database/migrations/*_create_accounts_table.php`
  - `app/Models/Account.php`
  - `app/Services/AccountBalanceService.php`
  - `app/Livewire/Accounts/ManageAccounts.php`
  - `resources/views/livewire/accounts/manage-accounts.blade.php`
  - `resources/views/layouts/app.blade.php`
  - `routes/web.php`
  - `tests/Feature/AccountTest.php`
- Next task: Phase 3 — Menu (Wait for user instruction, do not start Phase 3 automatically).
