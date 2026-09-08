<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * A circle tier/product definition (contribution amount, cycle cadence,
 * eligibility) that circle_groups are opened against (2LLU Batch 1 §4).
 */
class CirclePlan extends Model
{
    use HasUuids;

    protected $fillable = [
        'name', 'slug', 'cycle_type', 'cycle_duration', 'members_per_group',
        'contribution_amount', 'registration_fee', 'platform_fee_percent',
        'kyc_level_required', 'currency', 'requires_bank_statement',
        'min_income_multiplier', 'countries_allowed', 'tooltip_details',
        'bullet_points', 'turn_sort_strategy', 'priority_rule_id', 'status',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'platform_fee_percent' => 'decimal:2',
            'requires_bank_statement' => 'boolean',
            'min_income_multiplier' => 'decimal:2',
            'countries_allowed' => 'array',
            'bullet_points' => 'array',
        ];
    }

    public function priorityRule(): BelongsTo
    {
        return $this->belongsTo(CirclePriorityRule::class, 'priority_rule_id');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(CircleGroup::class, 'plan_id');
    }

    public function bankStatements(): HasMany
    {
        return $this->hasMany(UserBankStatement::class, 'plan_id');
    }

    /** Debts run up in any group opened under this plan. */
    public function debts(): HasManyThrough
    {
        return $this->hasManyThrough(
            CircleDebt::class,
            CircleGroup::class,
            'plan_id',   // FK on circle_groups referencing circle_plans
            'group_id',  // FK on circle_debts referencing circle_groups
            'id',        // local key on circle_plans
            'id',        // local key on circle_groups
        );
    }
}
