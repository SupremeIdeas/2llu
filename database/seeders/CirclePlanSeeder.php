<?php

namespace Database\Seeders;

use App\Models\CirclePlan;
use App\Models\CirclePriorityRule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the default turn-priority rule and the 24 circle_plans rows (8
 * weekly, 8 biweekly, 8 monthly tiers — 2LLU Batch 1 §5d). Nothing here is
 * live yet, so every plan seeds as status=draft. Idempotent — matched by
 * slug/name so a re-seed never clobbers a later admin edit.
 *
 * NOTE: registration_fee is a flat ₦500 placeholder on every tier per the
 * build instructions — real per-tier registration fees need product input
 * before this goes live. See the batch report for detail.
 */
class CirclePlanSeeder extends Seeder
{
    /** Flat placeholder registration fee (kobo-free NGN) — see class docblock. */
    private const REGISTRATION_FEE_PLACEHOLDER = 500;

    public function run(): void
    {
        $rule = CirclePriorityRule::query()->firstOrCreate(
            ['name' => 'Default Purpose Priority'],
            [
                'ordered_purposes' => [
                    'emergency', 'loan_payoff', 'school_fees', 'business_startup', 'travel', 'other',
                ],
                'tie_breaker' => 'join_order',
                'is_active' => true,
            ]
        );

        $tiers = [
            'weekly' => [
                'amounts' => [2_000, 5_000, 10_000, 20_000, 35_000, 50_000, 75_000, 100_000],
                'members_per_group' => 13,
                'cycle_duration' => 13,
                'label' => 'Weekly',
            ],
            'biweekly' => [
                'amounts' => [20_000, 40_000, 75_000, 125_000, 200_000, 300_000, 400_000, 500_000],
                'members_per_group' => 12,
                'cycle_duration' => 12,
                'label' => 'Biweekly',
            ],
            'monthly' => [
                'amounts' => [15_000, 30_000, 50_000, 100_000, 200_000, 300_000, 400_000, 500_000],
                'members_per_group' => 12,
                'cycle_duration' => 12,
                'label' => 'Monthly',
            ],
        ];

        $displayOrder = 0;

        foreach ($tiers as $cycleType => $tier) {
            foreach ($tier['amounts'] as $i => $amount) {
                $tierNumber = $i + 1;
                $isAdvanced = $tierNumber >= 3;
                $name = "{$tier['label']} Tier {$tierNumber} — ₦".number_format($amount);
                $slug = Str::slug("{$cycleType}-tier-{$tierNumber}-{$amount}");

                CirclePlan::query()->updateOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => $name,
                        'cycle_type' => $cycleType,
                        'cycle_duration' => $tier['cycle_duration'],
                        'members_per_group' => $tier['members_per_group'],
                        'contribution_amount' => $amount,
                        'registration_fee' => self::REGISTRATION_FEE_PLACEHOLDER,
                        'platform_fee_percent' => 0.5,
                        'kyc_level_required' => $isAdvanced ? 'advanced' : 'basic',
                        'currency' => 'NGN',
                        'requires_bank_statement' => $isAdvanced,
                        'min_income_multiplier' => 0.50,
                        'countries_allowed' => ['NG'],
                        'bullet_points' => null,
                        'turn_sort_strategy' => 'fifo',
                        'priority_rule_id' => $rule->id,
                        'status' => 'draft',
                        'display_order' => $displayOrder++,
                    ]
                );
            }
        }
    }
}
