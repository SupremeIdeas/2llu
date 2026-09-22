<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2LLU Batch 1 (§5). Per-group chat thread — text and/or a single image per
 * message.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circle_chat_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('group_id')->constrained('circle_groups');
            $table->foreignId('user_id')->constrained();
            $table->text('message')->nullable();
            $table->string('image_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circle_chat_messages');
    }
};
