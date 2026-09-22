<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2LLU Batch 1 (§5b). Uploaded bank statements backing an advanced-tier
 * plan's requires_bank_statement gate. The Claude-based UnderwritingService
 * that populates ai_analysis and approves/rejects lands in Batch 2 — schema
 * only here, no upload/validation logic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_bank_statements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained();
            $table->foreignUuid('plan_id')->nullable()->constrained('circle_plans');
            $table->string('pdf_url');
            $table->unsignedInteger('file_size_bytes');
            $table->json('ai_analysis')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_bank_statements');
    }
};
