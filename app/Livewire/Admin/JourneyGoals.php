<?php

namespace App\Livewire\Admin;

use App\Models\JourneyGoal;
use App\Services\Journey\JourneyGoalService;
use App\Support\Auditor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Journey Goals (My Journey expansion). Define an achievement — a
 * real, measurable metric + a target + a period — and NaaraSim pays out the
 * reward the moment a user's own activity crosses it (JourneyGoalService).
 * Nothing here ever touches a user's balance directly; this only shapes the
 * goal, the engine does the (idempotent) granting.
 */
#[Layout('components.layouts.admin')]
class JourneyGoals extends Component
{
    public ?int $editingId = null;

    public string $title = '';

    public string $description = '';

    public string $metric = 'esim_purchases';

    public $target = 1;

    public string $period_type = JourneyGoal::PERIOD_LIFETIME;

    public ?string $starts_at = null;

    public ?string $ends_at = null;

    public $reward_credits = 10;

    public string $audience = JourneyGoal::AUDIENCE_ALL;

    public ?string $saved = null;

    private function formData(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'metric' => $this->metric,
            'target' => (float) $this->target,
            'period_type' => $this->period_type,
            'starts_at' => $this->period_type === JourneyGoal::PERIOD_CAMPAIGN ? $this->starts_at : null,
            'ends_at' => $this->period_type === JourneyGoal::PERIOD_CAMPAIGN ? $this->ends_at : null,
            'reward_credits' => (float) $this->reward_credits,
            'audience' => $this->audience,
        ];
    }

    public function save(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $this->validate([
            'title' => 'required|string|max:120',
            'description' => 'required|string|max:255',
            'metric' => 'required|in:'.implode(',', array_keys(JourneyGoalService::METRICS)),
            'target' => 'required|numeric|min:0.01',
            'period_type' => 'required|in:lifetime,monthly,quarterly,yearly,campaign',
            'starts_at' => 'nullable|required_if:period_type,campaign|date',
            'ends_at' => 'nullable|required_if:period_type,campaign|date|after:starts_at',
            'reward_credits' => 'required|numeric|min:0.01|max:1000000',
            'audience' => 'required|in:all,merchant,merchant_v2',
        ]);

        if ($this->editingId) {
            $goal = JourneyGoal::findOrFail($this->editingId);
            $goal->update($this->formData());
            Auditor::log('journey_goal.updated', JourneyGoal::class, $goal->id, ['title' => $goal->title]);
            $this->saved = "Goal \"{$goal->title}\" updated.";
        } else {
            $goal = JourneyGoal::create([...$this->formData(), 'is_active' => true, 'created_by' => Auth::id()]);
            Auditor::log('journey_goal.created', JourneyGoal::class, $goal->id, ['title' => $goal->title]);
            $this->saved = "Goal \"{$goal->title}\" created and live.";
        }

        $this->reset('editingId', 'title', 'description', 'starts_at', 'ends_at');
        $this->metric = 'esim_purchases';
        $this->target = 1;
        $this->period_type = JourneyGoal::PERIOD_LIFETIME;
        $this->reward_credits = 10;
        $this->audience = JourneyGoal::AUDIENCE_ALL;
        $this->dispatch('nx-toast', type: 'success', message: $this->saved);
        $this->dispatch('close-goal-sheet');
    }

    public function edit(int $id): void
    {
        $goal = JourneyGoal::findOrFail($id);
        $this->editingId = $goal->id;
        $this->title = $goal->title;
        $this->description = $goal->description;
        $this->metric = $goal->metric;
        $this->target = (float) $goal->target;
        $this->period_type = $goal->period_type;
        $this->starts_at = $goal->starts_at?->toDateString();
        $this->ends_at = $goal->ends_at?->toDateString();
        $this->reward_credits = (float) $goal->reward_credits;
        $this->audience = $goal->audience;
    }

    public function newGoal(): void
    {
        $this->reset('editingId', 'title', 'description', 'starts_at', 'ends_at');
        $this->metric = 'esim_purchases';
        $this->target = 1;
        $this->period_type = JourneyGoal::PERIOD_LIFETIME;
        $this->reward_credits = 10;
        $this->audience = JourneyGoal::AUDIENCE_ALL;
    }

    public function toggle(int $id): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $goal = JourneyGoal::findOrFail($id);
        $goal->update(['is_active' => ! $goal->is_active]);
        Auditor::log('journey_goal.toggled', JourneyGoal::class, $goal->id, ['active' => $goal->is_active]);
    }

    public function delete(int $id): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        $goal = JourneyGoal::findOrFail($id);

        // A goal that already paid someone out is part of the credit audit
        // trail — pause it instead of erasing the claims that reference it.
        if ($goal->claims()->exists()) {
            $goal->update(['is_active' => false]);
            $this->dispatch('nx-toast', type: 'info', message: 'This goal has already paid out claims, so it was paused instead of deleted.');

            return;
        }

        Auditor::log('journey_goal.deleted', JourneyGoal::class, $goal->id, ['title' => $goal->title]);
        $goal->delete();
    }

    public function render()
    {
        return view('livewire.admin.journey-goals', [
            'goals' => JourneyGoal::withCount('claims')->latest()->get(),
            'metrics' => JourneyGoalService::METRICS,
        ]);
    }
}
