<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2LLU Batch 1 (§5b). Self-declared income used by the eligibility filter
 * built in Batch 2 (min_income_multiplier gate) — schema only here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('income_cycle', ['weekly', 'biweekly', 'monthly'])->nullable();
            $table->unsignedBigInteger('income_amount')->nullable();
            $table->timestamp('income_declared_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['income_cycle', 'income_amount', 'income_declared_at']);
        });
    }
};
