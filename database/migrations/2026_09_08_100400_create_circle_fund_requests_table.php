<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2LLU Batch 1 (§5). A member's own stated purpose for the funds they'll
 * collect — self-reported, no approval gate. Consumed by TurnSortingService
 * when a plan's turn_sort_strategy is priority_auto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circle_fund_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('member_id')->constrained('circle_members');
            $table->enum('purpose', ['business_startup', 'travel', 'emergency', 'loan_payoff', 'school_fees', 'other']);
            $table->text('description');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circle_fund_requests');
    }
};
