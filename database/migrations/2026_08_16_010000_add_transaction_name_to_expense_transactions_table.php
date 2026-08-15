<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds dedicated transaction_name field to expense_transactions.
     */
    public function up(): void
    {
        Schema::table('expense_transactions', function (Blueprint $table) {
            $table->string('transaction_name', 150)->after('transaction_date')->default('Expense Transaction');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expense_transactions', function (Blueprint $table) {
            $table->dropColumn('transaction_name');
        });
    }
};
