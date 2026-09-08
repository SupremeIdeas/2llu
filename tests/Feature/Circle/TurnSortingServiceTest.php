<?php

namespace Tests\Feature\Circle;

use App\Models\CircleFundRequest;
use App\Models\CircleGroup;
use App\Models\CircleMember;
use App\Models\CirclePlan;
use App\Models\CirclePriorityRule;
use App\Models\User;
use App\Services\Circle\TurnSortingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TurnSortingService (2LLU Batch 1 §5) — verifies both turn_sort_strategy
 * branches on CirclePlan actually drive the assigned turn_number.
 */
class TurnSortingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeGroup(string $strategy, ?CirclePriorityRule $rule = null): CircleGroup
    {
        $plan = CirclePlan::create([
            'name' => 'Test Plan',
            'slug' => 'test-plan-'.$strategy.'-'.uniqid(),
            'cycle_type' => 'weekly',
            'cycle_duration' => 13,
            'members_per_group' => 13,
            'contribution_amount' => 5000,
            'registration_fee' => 500,
            'kyc_level_required' => 'basic',
            'countries_allowed' => ['NG'],
            'turn_sort_strategy' => $strategy,
            'priority_rule_id' => $rule?->id,
        ]);

        return CircleGroup::create([
            'plan_id' => $plan->id,
            'name' => 'Test Group',
            'country' => 'NG',
            'max_members' => 13,
        ]);
    }

    private function addMember(CircleGroup $group, \DateTimeInterface $joinedAt, ?string $purpose = null): CircleMember
    {
        $member = CircleMember::create([
            'group_id' => $group->id,
            'user_id' => User::factory()->create()->id,
            'joined_at' => $joinedAt,
        ]);

        if ($purpose !== null) {
            CircleFundRequest::create([
                'member_id' => $member->id,
                'purpose' => $purpose,
                'description' => 'test',
            ]);
        }

        return $member;
    }

    /**
     * The rule ranks purposes emergency < loan_payoff < school_fees <
     * business_startup < travel < other. A member with a higher-priority
     * purpose who joined LAST must still outrank a lower-priority purpose
     * who joined FIRST — priority beats join order. The FIFO tie-break only
     * applies to members who share the same purpose rank.
     */
    public function test_priority_auto_strategy_ranks_by_purpose_and_only_falls_back_to_join_order_within_a_rank(): void
    {
        $rule = CirclePriorityRule::create([
            'name' => 'Default',
            'ordered_purposes' => ['emergency', 'loan_payoff', 'school_fees', 'business_startup', 'travel', 'other'],
            'tie_breaker' => 'join_order',
            'is_active' => true,
        ]);

        $group = $this->makeGroup('priority_auto', $rule);

        // Joined first but low priority ("travel").
        $travel = $this->addMember($group, now()->subDays(10), 'travel');
        // Joined last but highest priority ("emergency") — must still win turn 1.
        $emergency = $this->addMember($group, now()->subDay(), 'emergency');
        // Two "school_fees" members, same rank — FIFO tie-break decides between them.
        $schoolFeesLater = $this->addMember($group, now()->subDays(3), 'school_fees');
        $schoolFeesEarlier = $this->addMember($group, now()->subDays(7), 'school_fees');

        (new TurnSortingService)->assignTurns($group);

        $emergency->refresh();
        $travel->refresh();
        $schoolFeesEarlier->refresh();
        $schoolFeesLater->refresh();

        $this->assertSame(1, $emergency->turn_number, 'highest-priority purpose must take turn 1 despite joining last');
        // Within the tied "school_fees" rank, the earlier joiner goes first.
        $this->assertLessThan($schoolFeesLater->turn_number, $schoolFeesEarlier->turn_number);
        // "travel" is the lowest-priority purpose seeded here, so it must be last.
        $this->assertSame(4, $travel->turn_number);
    }

    /**
     * fifo strategy must ignore purpose entirely, even when a purpose exists
     * that would otherwise rank highly under priority_auto.
     */
    public function test_fifo_strategy_sorts_strictly_by_joined_at_and_ignores_purpose(): void
    {
        $group = $this->makeGroup('fifo');

        // Joined first but "other" (lowest priority under any priority rule).
        $first = $this->addMember($group, now()->subDays(5), 'other');
        // Joined second but "emergency" (highest priority) — must NOT jump ahead under fifo.
        $second = $this->addMember($group, now()->subDays(3), 'emergency');
        $third = $this->addMember($group, now()->subDay(), null);

        (new TurnSortingService)->assignTurns($group);

        $first->refresh();
        $second->refresh();
        $third->refresh();

        $this->assertSame(1, $first->turn_number);
        $this->assertSame(2, $second->turn_number);
        $this->assertSame(3, $third->turn_number);
    }
}
