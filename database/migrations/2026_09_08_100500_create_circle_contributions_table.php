<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2LLU Batch 1 (§5). One member's contribution for one round of a group's
 * cycle. Debit/payment logic lands in Batch 2 — this is schema only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circle_contributions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained();
            $table->foreignUuid('group_id')->constrained('circle_groups');
            $table->unsignedTinyInteger('round_number');
            $table->unsignedBigInteger('amount');
            $table->string('paystack_reference')->unique()->nullable();
            $table->enum('status', ['pending', 'paid', 'missed'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circle_contributions');
    }
};
