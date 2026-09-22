<?php

namespace App\Services\Circle;

use App\Models\CircleGroup;
use App\Models\CirclePriorityRule;
use Illuminate\Support\Collection;

/**
 * Assigns each member of a circle group their payout turn_number, either by
 * plain join order (fifo) or by the group's plan's declared purpose
 * priority (priority_auto), tie-broken within a rank per the rule's
 * tie_breaker (2LLU Batch 1 §5).
 */
class TurnSortingService
{
    public function assignTurns(CircleGroup $group): void
    {
        $plan = $group->plan;
        $members = $group->members()->with('fundRequest')->get();

        $sorted = $plan->turn_sort_strategy === 'priority_auto'
            ? $this->byPriority($members, $plan->priorityRule ?? CirclePriorityRule::where('is_active', true)->first())
            : $members->sortBy('joined_at');

        foreach ($sorted->values() as $i => $member) {
            $member->update(['turn_number' => $i + 1]);
        }
    }

    private function byPriority(Collection $members, ?CirclePriorityRule $rule): Collection
    {
        if (! $rule) {
            return $members->sortBy('joined_at');
        }

        return $members->sort(function ($a, $b) use ($rule) {
            $ra = $rule->rankOf($a->fundRequest?->purpose ?? '');
            $rb = $rule->rankOf($b->fundRequest?->purpose ?? '');
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }

            return $rule->tie_breaker === 'random' ? rand(-1, 1) : $a->joined_at <=> $b->joined_at;
        });
    }
}
