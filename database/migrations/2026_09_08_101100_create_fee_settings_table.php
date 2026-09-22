<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2LLU Batch 1 (§5d). Admin-tunable fee percentages/flats used by later
 * batches' fee-split math (FeeCalculator, Batch 2) — bounded by
 * min_value/max_value so a bad edit can't silently break the money path.
 * No admin UI yet; that's a later, separate step.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->decimal('value', 10, 4);
            $table->decimal('min_value', 10, 4);
            $table->decimal('max_value', 10, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_settings');
    }
};
