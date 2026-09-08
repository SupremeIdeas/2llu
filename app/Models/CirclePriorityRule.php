<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Declares the purpose ordering TurnSortingService uses for a plan's
 * priority_auto turn-sort strategy (2LLU Batch 1 §5).
 */
class CirclePriorityRule extends Model
{
    use HasUuids;

    protected $fillable = [
        'name', 'ordered_purposes', 'tie_breaker', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'ordered_purposes' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function plans(): HasMany
    {
        return $this->hasMany(CirclePlan::class, 'priority_rule_id');
    }

    /**
     * Position of a purpose in this rule's declared order — lower ranks sort
     * first. An unrecognised purpose sorts last.
     */
    public function rankOf(string $purpose): int
    {
        $i = array_search($purpose, $this->ordered_purposes, true);

        return $i === false ? PHP_INT_MAX : $i;
    }
}
