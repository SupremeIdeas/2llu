<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A user's membership in one circle group, including the turn_number
 * TurnSortingService assigns for payout ordering (2LLU Batch 1 §4).
 */
class CircleMember extends Model
{
    use HasUuids;

    protected $fillable = [
        'group_id', 'user_id', 'turn_number', 'joined_at', 'status', 'warning_count',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CircleGroup::class, 'group_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fundRequest(): HasOne
    {
        return $this->hasOne(CircleFundRequest::class, 'member_id');
    }
}
