<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A running instance of a circle plan — the group of members contributing on
 * a schedule and taking turns collecting the pot (2LLU Batch 1 §4).
 */
class CircleGroup extends Model
{
    use HasUuids;

    protected $fillable = [
        'plan_id', 'name', 'country', 'state', 'status', 'member_count',
        'max_members', 'current_round', 'cycle_start_date', 'renew_requested',
    ];

    protected function casts(): array
    {
        return [
            'cycle_start_date' => 'date',
            'renew_requested' => 'boolean',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(CirclePlan::class, 'plan_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(CircleMember::class, 'group_id');
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(CircleContribution::class, 'group_id');
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(CircleChatMessage::class, 'group_id');
    }

    public function debts(): HasMany
    {
        return $this->hasMany(CircleDebt::class, 'group_id');
    }
}
