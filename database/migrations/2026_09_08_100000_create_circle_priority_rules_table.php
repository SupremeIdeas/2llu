<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2LLU Batch 1 (§5). A priority rule defines the purpose ordering
 * TurnSortingService uses for a plan's `priority_auto` turn-sort strategy —
 * created before circle_plans since plans reference it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circle_priority_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->json('ordered_purposes');
            $table->enum('tie_breaker', ['join_order', 'random'])->default('join_order');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circle_priority_rules');
    }
};
