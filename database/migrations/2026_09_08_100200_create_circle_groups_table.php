<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2LLU Batch 1 (§5). A running instance of a circle plan — the actual group
 * of members contributing on a schedule and taking turns collecting the pot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circle_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_id')->constrained('circle_plans');
            $table->string('name');
            $table->char('country', 2);
            $table->string('state')->nullable();
            $table->enum('status', ['open', 'active', 'completed', 'suspended'])->default('open');
            $table->unsignedTinyInteger('member_count')->default(0);
            $table->unsignedTinyInteger('max_members');
            $table->unsignedTinyInteger('current_round')->default(0);
            $table->date('cycle_start_date')->nullable();
            $table->boolean('renew_requested')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circle_groups');
    }
};
