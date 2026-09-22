<?php

namespace App\Livewire\Admin;

use App\Models\CirclePlan;
use App\Models\CirclePriorityRule;
use App\Support\Auditor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Circle Plans (2LLU Batch 1 §6). CRUD for every circle_plans field,
 * including which turn-sort strategy and priority rule a plan uses.
 */
#[Layout('components.layouts.admin')]
class CirclePlans extends Component
{
    public ?string $editingId = null;

    public string $name = '';

    public string $slug = '';

    public string $cycleType = 'weekly';

    public int $cycleDuration = 13;

    public int $membersPerGroup = 13;

    public $contributionAmount = 0;

    public $registrationFee = 500;

    public $platformFeePercent = 0.5;

    public string $kycLevelRequired = 'basic';

    public string $currency = 'NGN';

    public bool $requiresBankStatement = false;

    public $minIncomeMultiplier = 0.50;

    /** Comma-separated ISO-2 country codes, e.g. "NG, GH". */
    public string $countriesAllowed = 'NG';

    public ?string $tooltipDetails = null;

    /** One bullet per line. */
    public string $bulletPoints = '';

    public string $turnSortStrategy = 'fifo';

    public ?string $priorityRuleId = null;

    public string $status = 'draft';

    public int $displayOrder = 0;

    public ?string $saved = null;

    public function newPlan(): void
    {
        $this->reset([
            'editingId', 'name', 'slug', 'tooltipDetails', 'bulletPoints', 'priorityRuleId',
        ]);
        $this->cycleType = 'weekly';
        $this->cycleDuration = 13;
        $this->membersPerGroup = 13;
        $this->contributionAmount = 0;
        $this->registrationFee = 500;
        $this->platformFeePercent = 0.5;
        $this->kycLevelRequired = 'basic';
        $this->currency = 'NGN';
        $this->requiresBankStatement = false;
        $this->minIncomeMultiplier = 0.50;
        $this->countriesAllowed = 'NG';
        $this->turnSortStrategy = 'fifo';
        $this->status = 'draft';
        $this->displayOrder = 0;
    }

    public function edit(string $id): void
    {
        $plan = CirclePlan::findOrFail($id);
        $this->editingId = $plan->id;
        $this->name = $plan->name;
        $this->slug = $plan->slug;
        $this->cycleType = $plan->cycle_type;
        $this->cycleDuration = $plan->cycle_duration;
        $this->membersPerGroup = $plan->members_per_group;
        $this->contributionAmount = $plan->contribution_amount;
        $this->registrationFee = $plan->registration_fee;
        $this->platformFeePercent = (float) $plan->platform_fee_percent;
        $this->kycLevelRequired = $plan->kyc_level_required;
        $this->currency = $plan->currency;
        $this->requiresBankStatement = $plan->requires_bank_statement;
        $this->minIncomeMultiplier = (float) $plan->min_income_multiplier;
        $this->countriesAllowed = implode(', ', $plan->countries_allowed ?? []);
        $this->tooltipDetails = $plan->tooltip_details;
        $this->bulletPoints = implode("\n", $plan->bullet_points ?? []);
        $this->turnSortStrategy = $plan->turn_sort_strategy;
        $this->priorityRuleId = $plan->priority_rule_id;
        $this->status = $plan->status;
        $this->displayOrder = $plan->display_order;
    }

    public function save(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $this->validate([
            'name' => 'required|string|min:2|max:120',
            'slug' => 'required|string|min:2|max:140|regex:/^[a-z0-9\-]+$/|unique:circle_plans,slug,'.($this->editingId ?: 'NULL').',id',
            'cycleType' => 'required|in:daily,weekly,biweekly,monthly',
            'cycleDuration' => 'required|integer|min:1|max:104',
            'membersPerGroup' => 'required|integer|min:2|max:100',
            'contributionAmount' => 'required|integer|min:1',
            'registrationFee' => 'required|integer|min:0',
            'platformFeePercent' => 'required|numeric|min:0|max:100',
            'kycLevelRequired' => 'required|in:basic,advanced',
            'currency' => 'required|string|size:3',
            'minIncomeMultiplier' => 'required|numeric|min:0.01|max:10',
            'countriesAllowed' => 'required|string',
            'turnSortStrategy' => 'required|in:fifo,priority_auto',
            'status' => 'required|in:active,draft',
            'displayOrder' => 'required|integer|min:0',
        ], [
            'slug.regex' => 'Lowercase letters, numbers and dashes only.',
        ]);

        $countries = collect(explode(',', $this->countriesAllowed))
            ->map(fn ($c) => strtoupper(trim($c)))->filter()->values()->all();
        $bullets = collect(explode("\n", $this->bulletPoints))
            ->map(fn ($b) => trim($b))->filter()->values()->all();

        $plan = CirclePlan::updateOrCreate(
            ['id' => $this->editingId],
            [
                'name' => $this->name,
                'slug' => $this->slug ?: Str::slug($this->name),
                'cycle_type' => $this->cycleType,
                'cycle_duration' => $this->cycleDuration,
                'members_per_group' => $this->membersPerGroup,
                'contribution_amount' => $this->contributionAmount,
                'registration_fee' => $this->registrationFee,
                'platform_fee_percent' => $this->platformFeePercent,
                'kyc_level_required' => $this->kycLevelRequired,
                'currency' => strtoupper($this->currency),
                'requires_bank_statement' => $this->requiresBankStatement,
                'min_income_multiplier' => $this->minIncomeMultiplier,
                'countries_allowed' => $countries,
                'tooltip_details' => $this->tooltipDetails ?: null,
                'bullet_points' => $bullets,
                'turn_sort_strategy' => $this->turnSortStrategy,
                'priority_rule_id' => $this->priorityRuleId ?: null,
                'status' => $this->status,
                'display_order' => $this->displayOrder,
            ]
        );

        $this->editingId = $plan->id;
        Auditor::log('circle_plan.saved', CirclePlan::class, null, ['id' => $plan->id, 'slug' => $plan->slug]);
        $this->saved = "Saved \"{$plan->name}\".";
        $this->dispatch('nx-toast', type: 'success', message: 'Plan saved.');
    }

    /** Draft/active toggle — never a hard delete once a plan could have groups against it. */
    public function toggleStatus(string $id): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $plan = CirclePlan::findOrFail($id);
        $plan->update(['status' => $plan->status === 'active' ? 'draft' : 'active']);
        Auditor::log('circle_plan.status_toggled', CirclePlan::class, null, ['id' => $plan->id, 'status' => $plan->status]);
    }

    public function delete(string $id): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $plan = CirclePlan::findOrFail($id);

        if ($plan->groups()->exists()) {
            $this->dispatch('nx-toast', type: 'error', message: 'This plan has circle groups against it and cannot be deleted — set it to draft instead.');

            return;
        }

        Auditor::log('circle_plan.deleted', CirclePlan::class, null, ['id' => $plan->id, 'slug' => $plan->slug]);
        $plan->delete();

        if ($this->editingId === $id) {
            $this->newPlan();
        }
    }

    public function render()
    {
        return view('livewire.admin.circle-plans', [
            'plans' => CirclePlan::query()->orderBy('cycle_type')->orderBy('display_order')->get()->groupBy('cycle_type'),
            'priorityRules' => CirclePriorityRule::query()->orderBy('name')->get(),
        ]);
    }
}
