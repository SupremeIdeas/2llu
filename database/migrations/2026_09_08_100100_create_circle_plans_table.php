<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2LLU Batch 1 (§5). A circle plan is a tier/product definition (contribution
 * amount, cycle cadence, eligibility) that circle_groups are opened against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circle_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('cycle_type', ['daily', 'weekly', 'biweekly', 'monthly']);
            $table->unsignedSmallInteger('cycle_duration');
            $table->unsignedTinyInteger('members_per_group')->default(13);
            $table->unsignedBigInteger('contribution_amount');
            $table->unsignedBigInteger('registration_fee');
            $table->decimal('platform_fee_percent', 5, 2)->default(0.5);
            $table->enum('kyc_level_required', ['basic', 'advanced']);
            $table->char('currency', 3)->default('NGN');
            $table->boolean('requires_bank_statement')->default(false);
            $table->decimal('min_income_multiplier', 3, 2)->default(0.50);
            $table->json('countries_allowed');
            $table->text('tooltip_details')->nullable();
            $table->json('bullet_points')->nullable();
            $table->enum('turn_sort_strategy', ['fifo', 'priority_auto'])->default('fifo');
            $table->foreignUuid('priority_rule_id')->nullable()->constrained('circle_priority_rules')->nullOnDelete();
            $table->enum('status', ['active', 'draft'])->default('draft');
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circle_plans');
    }
};
