<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One member's contribution for one round of a group's cycle. The debit/
 * payment logic that populates status/paid_at lands in Batch 2 — this model
 * is schema-only for now (2LLU Batch 1 §5b).
 */
class CircleContribution extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'group_id', 'round_number', 'amount', 'paystack_reference',
        'status', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CircleGroup::class, 'group_id');
    }

    public function debt(): HasOne
    {
        return $this->hasOne(CircleDebt::class, 'contribution_id');
    }
}
