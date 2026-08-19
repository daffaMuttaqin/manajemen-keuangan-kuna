<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds required sales_channel field to income_transactions table.
     * Default 'Other' is applied to preserve integrity of existing historical records.
     */
    public function up(): void
    {
        Schema::table('income_transactions', function (Blueprint $table) {
            $table->string('sales_channel', 20)->default('Other')->after('category');
            $table->index('sales_channel', 'idx_income_sales_channel');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('income_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_income_sales_channel');
            $table->dropColumn('sales_channel');
        });
    }
};
