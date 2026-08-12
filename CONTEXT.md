# KUNA PATISSERIE — AI PROJECT CONTEXT

## Purpose

This file records the current implementation state for AI coding agents.

It is NOT a source of business requirements. PRD.md remains authoritative.

## Current project state

- Project status: Phase 3 — Menu Management completed.
- Current phase: Phase 3 — Menu (Completed)
- Current task: Phase 3 Implementation finished.
- Laravel status: Configured & operational (v13.24.0).
- MySQL status: Configured (`db-manajemen-kuna` DB, `finance-testing` test DB).
- Authentication status: AuthenticatedSessionController, Login view, Logout, and protected route implemented.
- Test suite status: 26 focused feature tests passing (6 Auth, 10 Account, 10 Menu).

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

- `tests/Feature/AuthenticationTest.php` (6 tests passed, 16 assertions).
- `tests/Feature/AccountTest.php` (10 tests passed, 21 assertions).
- `tests/Feature/MenuTest.php` (10 tests passed, 27 assertions).

## Known issues

None.

## Decisions made during implementation

- Auth Controller: Standard Laravel-native `AuthenticatedSessionController` handling login view, store authentication, and logout session destruction.
- Database: MySQL configured for both main application database (`db-manajemen-kuna`) and test database (`finance-testing`).
- UI: Clean minimal login page, base authenticated layout (`layouts.app`), dashboard placeholder (`/dashboard`), Accounts management (`/accounts`), and Menu management (`/menu`).
- Historical Price principle: `current_price` on `MenuItem` represents only the current price. Future transaction modules will store their own price snapshot upon creation.

## AI handoff notes

- Completed task: Phase 3 Menu Management.
- Files created/modified:
  - `database/migrations/2026_08_12_051625_create_menu_items_table.php`
  - `app/Models/MenuItem.php`
  - `app/Livewire/Menu/ManageMenu.php`
  - `resources/views/livewire/menu/manage-menu.blade.php`
  - `resources/views/layouts/app.blade.php`
  - `routes/web.php`
  - `tests/Feature/MenuTest.php`
  - `CONTEXT.md`
- Next task: Phase 4 — Income and Expense (Wait for user instruction, do not start Phase 4 automatically).
