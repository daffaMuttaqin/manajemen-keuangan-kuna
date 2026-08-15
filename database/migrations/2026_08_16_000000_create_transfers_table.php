<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Schema for Phase 7 Transfers (PRD & FINANCIAL_INVARIANTS.md).
     * Source and destination accounts must both reference valid accounts.
     * transfer_id is a unique UUID generated server-side for idempotency (FT-022).
     */
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();

            // Business-level idempotency identifier, generated server-side as UUID.
            $table->string('transfer_id', 40)->unique();

            $table->date('transfer_date');

            // Source account (money debited)
            $table->foreignId('from_account_id')
                  ->constrained('accounts')
                  ->restrictOnDelete();

            // Destination account (money credited)
            $table->foreignId('to_account_id')
                  ->constrained('accounts')
                  ->restrictOnDelete();

            // Amount transferred (always positive decimal)
            $table->decimal('amount', 19, 2);

            // Optional transfer note
            $table->text('description')->nullable();

            // Lifecycle status: 'active' | 'cancelled'
            $table->string('record_status', 20)->default('active');

            // Audit: user who created this transfer
            $table->foreignId('created_by')
                  ->constrained('users')
                  ->restrictOnDelete();

            $table->timestamps();

            // Indexes for balance calculations and filters
            $table->index(['from_account_id', 'record_status'], 'idx_transfers_from_account');
            $table->index(['to_account_id', 'record_status'], 'idx_transfers_to_account');
            $table->index('transfer_date', 'idx_transfers_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
