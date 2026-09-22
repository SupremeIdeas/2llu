<?php

namespace App\Livewire\Admin;

use App\Models\CirclePriorityRule;
use App\Support\Auditor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Circle Priority Rules (2LLU Batch 1 §6). The only manual step in
 * the whole turn-sorting system: configure a purpose order + tie-breaker
 * once, and TurnSortingService applies it automatically to every group on
 * a plan using the priority_auto strategy.
 */
#[Layout('components.layouts.admin')]
class CirclePriorityRules extends Component
{
    public const PURPOSES = [
        'emergency' => 'Emergency',
        'loan_payoff' => 'Loan payoff',
        'school_fees' => 'School fees',
        'business_startup' => 'Business startup',
        'travel' => 'Travel',
        'other' => 'Other',
    ];

    public ?string $editingId = null;

    public string $name = '';

    /** @var list<string> */
    public array $orderedPurposes = [];

    public string $tieBreaker = 'join_order';

    public bool $isActive = true;

    public ?string $saved = null;

    public function mount(): void
    {
        $this->orderedPurposes = array_keys(self::PURPOSES);
    }

    public function edit(string $id): void
    {
        $rule = CirclePriorityRule::findOrFail($id);
        $this->editingId = $rule->id;
        $this->name = $rule->name;
        $this->orderedPurposes = $rule->ordered_purposes;
        $this->tieBreaker = $rule->tie_breaker;
        $this->isActive = $rule->is_active;
    }

    public function newRule(): void
    {
        $this->reset('editingId', 'name');
        $this->orderedPurposes = array_keys(self::PURPOSES);
        $this->tieBreaker = 'join_order';
        $this->isActive = false;
    }

    public function moveUp(int $index): void
    {
        if ($index <= 0) {
            return;
        }
        [$this->orderedPurposes[$index - 1], $this->orderedPurposes[$index]]
            = [$this->orderedPurposes[$index], $this->orderedPurposes[$index - 1]];
    }

    public function moveDown(int $index): void
    {
        if ($index >= count($this->orderedPurposes) - 1) {
            return;
        }
        [$this->orderedPurposes[$index + 1], $this->orderedPurposes[$index]]
            = [$this->orderedPurposes[$index], $this->orderedPurposes[$index + 1]];
    }

    public function save(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $this->validate([
            'name' => 'required|string|min:2|max:100',
            'tieBreaker' => 'required|in:join_order,random',
        ]);

        // Activating this rule deactivates every other one — a plan resolves
        // its rule to "the active one" when it has no explicit priority_rule_id,
        // so at most one can be active at a time.
        if ($this->isActive) {
            CirclePriorityRule::query()
                ->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))
                ->update(['is_active' => false]);
        }

        $rule = CirclePriorityRule::updateOrCreate(
            ['id' => $this->editingId],
            [
                'name' => $this->name,
                'ordered_purposes' => $this->orderedPurposes,
                'tie_breaker' => $this->tieBreaker,
                'is_active' => $this->isActive,
            ]
        );

        $this->editingId = $rule->id;
        Auditor::log('circle_priority_rule.saved', CirclePriorityRule::class, null, ['id' => $rule->id, 'name' => $rule->name]);
        $this->saved = "Saved \"{$rule->name}\".";
        $this->dispatch('nx-toast', type: 'success', message: 'Priority rule saved.');
    }

    public function render()
    {
        return view('livewire.admin.circle-priority-rules', [
            'rules' => CirclePriorityRule::query()->latest()->get(),
            'purposeLabels' => self::PURPOSES,
        ]);
    }
}
