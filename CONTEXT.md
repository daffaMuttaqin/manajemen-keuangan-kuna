# KUNA PATISSERIE — AI PROJECT CONTEXT

## Purpose

This file records the current implementation state for AI coding agents.

It is NOT a source of business requirements. PRD.md remains authoritative.

## Current project state

- Project status: Phase 3 — Menu Management completed. Development Admin Seeder added.
- Current phase: Phase 3 — Menu (Completed)
- Current task: Development Admin Seeder added.
- Laravel status: Configured & operational (v13.24.0).
- MySQL status: Configured (`db-manajemen-kuna` DB, `finance-testing` test DB).
- Authentication status: AuthenticatedSessionController, Login view, Logout, protected routes, and development admin seeder implemented.
- Test suite status: 27 focused feature tests passing (7 Auth/Seeder, 10 Account, 10 Menu).

## Completed phases

- [x] Phase 1 — Foundation
- [x] Phase 2 — Accounts
- [x] Phase 3 — Menu
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

- `tests/Feature/AuthenticationTest.php` (7 tests passed, 21 assertions).
- `tests/Feature/AccountTest.php` (10 tests passed, 21 assertions).
- `tests/Feature/MenuTest.php` (10 tests passed, 27 assertions).

## Known issues

None.

## Decisions made during implementation

- Auth Controller: Standard Laravel-native `AuthenticatedSessionController` handling login view, store authentication, and logout session destruction.
- Development Admin Seeder: `DatabaseSeeder` uses `User::updateOrCreate()` for idempotent creation of local admin user (`admin@gmail.com` / `12345678`).
- Database: MySQL configured for both main application database (`db-manajemen-kuna`) and test database (`finance-testing`).
- UI: Clean minimal login page, base authenticated layout (`layouts.app`), dashboard placeholder (`/dashboard`), Accounts management (`/accounts`), and Menu management (`/menu`).
- Historical Price principle: `current_price` on `MenuItem` represents only the current price. Future transaction modules will store their own price snapshot upon creation.

## AI handoff notes

- Completed task: Phase 3 Menu Management & Development Admin Seeder.
- Files created/modified:
  - `database/seeders/DatabaseSeeder.php`
  - `tests/Feature/AuthenticationTest.php`
  - `CONTEXT.md`
- Recommended database setup command: `php artisan migrate:fresh --seed`
- Next task: Phase 4 — Income and Expense (Wait for user instruction, do not start Phase 4 automatically).
