<?php

namespace App\Livewire\Admin;

use App\Jobs\RecomputePlanPricingJob;
use App\Models\EsimPlan;
use App\Models\Setting;
use App\Services\Pricing\PricingEngine;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin pricing panel (blueprint Section 13.3). Global markup + profit floor,
 * per-plan override / fixed price / active / featured, and a LIVE profit
 * summary that refreshes as the admin types (wire:model.live). Saving the
 * global markup dispatches a recompute of every plan; every change is
 * audit-logged. Cost is admin-only here — this surface is behind the admin gate.
 */
#[Layout('components.layouts.admin')]
class Pricing extends Component
{
    use WithPagination;

    // Global settings
    public $default_markup_pct;

    public $minimum_profit_usd;

    // Per-plan edit buffer
    public ?int $editingPlanId = null;

    public $override_markup_pct = '';

    public $manual_retail_usd = '';

    public bool $is_active = true;

    public bool $is_featured = false;

    public ?string $saved = null;

    public function mount(): void
    {
        $this->default_markup_pct = Setting::getValue('pricing.default_markup_pct', 30);
        $this->minimum_profit_usd = Setting::getValue('pricing.minimum_profit_usd', 0.50);
    }

    public function saveGlobal(): void
    {
        $this->validate([
            'default_markup_pct' => 'required|numeric|min:0|max:1000',
            'minimum_profit_usd' => 'required|numeric|min:0',
        ]);

        Setting::setValue('pricing.default_markup_pct', (float) $this->default_markup_pct, 'pricing');
        Setting::setValue('pricing.minimum_profit_usd', (float) $this->minimum_profit_usd, 'pricing');

        // Recompute every plan's retail against the new global markup (rule 1.4).
        RecomputePlanPricingJob::dispatch();
        $this->audit('pricing.global_updated', null, [
            'default_markup_pct' => $this->default_markup_pct,
            'minimum_profit_usd' => $this->minimum_profit_usd,
        ]);

        $this->saved = 'Global pricing saved — all plans are being repriced.';
    }

    public function editPlan(int $planId): void
    {
        $plan = EsimPlan::findOrFail($planId);
        $this->editingPlanId = $plan->id;
        $this->override_markup_pct = $plan->override_markup_pct ?? '';
        $this->manual_retail_usd = $plan->manual_retail_usd ?? '';
        $this->is_active = (bool) $plan->is_active;
        $this->is_featured = (bool) $plan->is_featured;
        $this->saved = null;
    }

    public function cancelEdit(): void
    {
        $this->reset('editingPlanId', 'override_markup_pct', 'manual_retail_usd', 'is_active', 'is_featured');
    }

    public function savePlan(PricingEngine $engine): void
    {
        $plan = EsimPlan::findOrFail($this->editingPlanId);
        $plan->override_markup_pct = $this->override_markup_pct === '' ? null : (float) $this->override_markup_pct;
        $plan->manual_retail_usd = $this->manual_retail_usd === '' ? null : (float) $this->manual_retail_usd;
        $plan->is_active = $this->is_active;
        $plan->is_featured = $this->is_featured;
        $plan->save();

        // Keep computed_retail_usd (and the generated final_retail_usd) in sync.
        $engine->recompute($plan);

        $this->audit('pricing.plan_updated', $plan->id, [
            'override_markup_pct' => $plan->override_markup_pct,
            'manual_retail_usd' => $plan->manual_retail_usd,
            'is_active' => $plan->is_active,
        ]);

        $this->saved = "Plan “{$plan->name}” updated.";
        $this->cancelEdit();
    }

    private function audit(string $action, ?int $modelId, array $payload): void
    {
        \App\Support\Auditor::log($action, EsimPlan::class, $modelId, $payload);
    }

    public function render(PricingEngine $engine)
    {
        // Live profit preview for the plan being edited (no logging on keystroke).
        $summary = null;
        if ($this->editingPlanId) {
            $preview = EsimPlan::find($this->editingPlanId);
            if ($preview) {
                $preview->override_markup_pct = $this->override_markup_pct === '' ? null : (float) $this->override_markup_pct;
                $preview->manual_retail_usd = $this->manual_retail_usd === '' ? null : (float) $this->manual_retail_usd;
                $summary = $engine->getProfitSummary($preview, log: false);
            }
        }

        return view('livewire.admin.pricing', [
            'plans' => EsimPlan::orderBy('name')->paginate(10),
            'summary' => $summary,
        ]);
    }
}
