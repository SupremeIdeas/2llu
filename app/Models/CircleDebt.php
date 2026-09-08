<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A missed-contribution debt owed between two members of the same group.
 * The penalty/compensation engine that creates and settles these rows lands
 * in Batch 2 — this model is schema-only for now (2LLU Batch 1 §5b).
 */
class CircleDebt extends Model
{
    use HasUuids;

    protected $fillable = [
        'debtor_user_id', 'creditor_user_id', 'group_id', 'contribution_id',
        'original_amount', 'penalty_amount', 'total_amount', 'status',
    ];

    public function debtor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'debtor_user_id');
    }

    public function creditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creditor_user_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CircleGroup::class, 'group_id');
    }

    public function contribution(): BelongsTo
    {
        return $this->belongsTo(CircleContribution::class, 'contribution_id');
    }
}
