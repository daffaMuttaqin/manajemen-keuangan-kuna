<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Schema follows PRD.md domain model for IncomeTransaction.
     * All monetary columns use DECIMAL to avoid floating-point inaccuracies.
     * account_id is nullable because unpaid income has no cash destination yet.
     * unit_price is a snapshot copied from MenuItem at creation (INV-010).
     * subtotal, discount_amount, total_amount are always server-computed (INV-018).
     * transaction_id is a UUID generated server-side for idempotency (FT-022).
     */
    public function up(): void
    {
        Schema::create('income_transactions', function (Blueprint $table) {
            $table->id();

            // Business-level idempotency identifier, generated server-side as UUID.
            $table->string('transaction_id', 40)->unique();

            $table->date('transaction_date');

            // MenuItem reference — nullable FK for PRD compliance.
            // Required by validation in the application layer.
            $table->foreignId('menu_item_id')
                  ->nullable()
                  ->constrained('menu_items')
                  ->restrictOnDelete();

            // Quantity supports fractional units (e.g. 0.5 kg).
            $table->decimal('quantity', 12, 2);

            // Snapshot of MenuItem.current_price at time of creation. Never updated after. (INV-010)
            $table->decimal('unit_price', 19, 2);

            // Discount as percentage (0–100). Server uses this to compute discount_amount.
            $table->decimal('discount_percentage', 5, 2)->default(0);

            // Server-computed: quantity × unit_price. (INV-018)
            $table->decimal('subtotal', 19, 2);

            // Server-computed: subtotal × discount_percentage / 100. (INV-018)
            $table->decimal('discount_amount', 19, 2)->default(0);

            // Server-computed: subtotal − discount_amount. (INV-018)
            $table->decimal('total_amount', 19, 2);

            // Revenue category label. Max 30 chars per PRD schema.
            $table->string('category', 30);

            // Optional operator notes.
            $table->text('description')->nullable();

            // Cash destination account. NULL for unpaid income (INV-003).
            // Must reference an active account when payment_status = 'paid'.
            $table->foreignId('account_id')
                  ->nullable()
                  ->constrained('accounts')
                  ->restrictOnDelete();

            // Payment lifecycle state: 'unpaid' | 'paid'. Separate from record_status.
            $table->string('payment_status', 20)->default('unpaid');

            // Record lifecycle state: 'active' | 'cancelled'. Separate from payment_status.
            $table->string('record_status', 20)->default('active');

            // Set when payment_status transitions to 'paid'.
            $table->timestamp('paid_at')->nullable();

            // Audit: who created this record.
            $table->foreignId('created_by')
                  ->constrained('users')
                  ->restrictOnDelete();

            $table->timestamps();

            // Indexes for the query patterns described in the architecture:
            // (1) Balance aggregation: account_id + record_status + payment_status
            $table->index(['account_id', 'record_status', 'payment_status'], 'idx_income_account_status');
            // (2) Period filters used by dashboard/reports
            $table->index('transaction_date', 'idx_income_date');
            // (3) Relationship lookups
            $table->index('menu_item_id', 'idx_income_menu_item');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('income_transactions');
    }
};
