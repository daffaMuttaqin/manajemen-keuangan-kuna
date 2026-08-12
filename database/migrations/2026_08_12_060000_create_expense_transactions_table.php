<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Schema follows PRD.md domain model for ExpenseTransaction.
     *
     * Key differences from income_transactions:
     *   - No menu_item_id, quantity, unit_price, discount_percentage, subtotal, discount_amount.
     *     Expense is a direct monetary amount, not derived from menu items.
     *   - Has expense_category instead of category (uses PRD-specified expense categories).
     *   - Has amount (DECIMAL) as the single authoritative monetary field (INV-018).
     *   - account_id is nullable because unpaid expense has no cash source yet (INV-004).
     *   - Must reference an active account when payment_status = 'paid' (INV-020).
     *   - Paid Active Expense DECREASES the account balance (INV-002).
     *   - Unpaid Expense creates an outstanding payable obligation only (INV-004).
     *   - Cancelled records are preserved in history, never deleted (INV-019).
     */
    public function up(): void
    {
        Schema::create('expense_transactions', function (Blueprint $table) {
            $table->id();

            // Business-level idempotency identifier, generated server-side as UUID (FT-022).
            $table->string('transaction_id', 40)->unique();

            $table->date('transaction_date');

            // Expense category per PRD Operating Expense formula:
            //   COGS / Cake Production, Operational, Marketing, Rent, Employee Salaries, Other
            // Stored as string (max 50) to remain flexible without schema migration for typos.
            $table->string('expense_category', 50);

            // Optional operator notes describing the expense.
            $table->text('description')->nullable();

            // The authoritative monetary amount of this expense. Always server-computed. (INV-018)
            // Stored as DECIMAL to avoid floating-point imprecision.
            $table->decimal('amount', 19, 2);

            // Cash source account. NULL for unpaid expense (INV-004).
            // Must reference an active account when payment_status = 'paid' (INV-020).
            // restrictOnDelete prevents accidental deletion of accounts with expense history (INV-019).
            $table->foreignId('account_id')
                  ->nullable()
                  ->constrained('accounts')
                  ->restrictOnDelete();

            // Payment lifecycle state: 'unpaid' | 'paid'. Separate from record_status.
            // Unpaid = outstanding payable only; Paid = cash outflow (INV-002 vs INV-004).
            $table->string('payment_status', 20)->default('unpaid');

            // Record lifecycle state: 'active' | 'cancelled'. Separate from payment_status.
            // Cancelled records remain in DB but are excluded from all financial aggregations (INV-009, INV-019).
            $table->string('record_status', 20)->default('active');

            // Set when payment_status transitions to 'paid'. Used for audit and future reporting.
            $table->timestamp('paid_at')->nullable();

            // Audit: who created this expense record.
            $table->foreignId('created_by')
                  ->constrained('users')
                  ->restrictOnDelete();

            $table->timestamps();

            // Indexes for the query patterns described in the architecture:
            // (1) Balance aggregation: account_id + record_status + payment_status
            $table->index(['account_id', 'record_status', 'payment_status'], 'idx_expense_account_status');
            // (2) Period filters used by dashboard/reports
            $table->index('transaction_date', 'idx_expense_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_transactions');
    }
};
