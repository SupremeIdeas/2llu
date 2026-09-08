<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2LLU Batch 1 (§5b). Tracks a missed-contribution debt owed between two
 * members of the same group. The penalty/compensation engine that populates
 * and settles these rows lands in Batch 2 — schema only here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circle_debts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('debtor_user_id')->constrained('users');
            $table->foreignId('creditor_user_id')->constrained('users');
            $table->foreignUuid('group_id')->constrained('circle_groups');
            $table->foreignUuid('contribution_id')->nullable()->constrained('circle_contributions');
            $table->unsignedBigInteger('original_amount');
            $table->unsignedBigInteger('penalty_amount');
            $table->unsignedBigInteger('total_amount');
            $table->enum('status', ['active', 'paid'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circle_debts');
    }
};
