<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Schema for Phase 6 Audit Foundation (FT-030, FT-015, FT-016).
     * Minimal audit log table storing financial mutation action, target entity, actor, and details.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action', 50);
            $table->string('auditable_type', 100);
            $table->unsignedBigInteger('auditable_id');
            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['auditable_type', 'auditable_id'], 'idx_audit_auditable');
            $table->index('action', 'idx_audit_action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
