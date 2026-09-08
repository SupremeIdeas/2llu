<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-group chat thread — text and/or a single image per message
 * (2LLU Batch 1 §4).
 */
class CircleChatMessage extends Model
{
    use HasUuids;

    protected $fillable = [
        'group_id', 'user_id', 'message', 'image_url',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(CircleGroup::class, 'group_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
