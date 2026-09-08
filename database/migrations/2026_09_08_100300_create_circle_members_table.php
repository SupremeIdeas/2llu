<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2LLU Batch 1 (§5). A user's membership in one circle group, including the
 * turn_number TurnSortingService assigns for payout ordering.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circle_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('group_id')->constrained('circle_groups');
            $table->foreignId('user_id')->constrained();
            $table->unsignedTinyInteger('turn_number')->nullable();
            $table->timestamp('joined_at');
            $table->enum('status', ['active', 'warning', 'suspended', 'completed'])->default('active');
            $table->unsignedTinyInteger('warning_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circle_members');
    }
};
