<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member's own stated purpose for the funds they'll collect —
 * self-reported, no approval gate. Feeds TurnSortingService's priority_auto
 * strategy via CirclePriorityRule::rankOf() (2LLU Batch 1 §4).
 */
class CircleFundRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'member_id', 'purpose', 'description',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(CircleMember::class, 'member_id');
    }
}
