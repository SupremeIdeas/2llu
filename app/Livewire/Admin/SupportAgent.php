<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Support\Auditor;
use App\Support\SupportSettings;
use App\Services\Support\NaaraCareAgent;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Support agent (Module 24). Configure the NaaraCare agent's human name,
 * persona/tone, and extra platform knowledge it should ground answers on.
 * super_admin & admin. The Anthropic key itself lives on the API-keys page.
 */
#[Layout('components.layouts.admin')]
class SupportAgent extends Component
{
    public string $agent_name = '';

    public string $persona = '';

    public string $knowledge = '';

    /** Autopilot: let the AI resolve safe tickets itself (owner request). */
    public bool $autopilot_enabled = true;

    /** Max goodwill (USD) the AI may grant per ticket. 0 = off (it escalates). */
    public $goodwill_cap_usd = 0;

    public ?string $saved = null;

    public function mount(): void
    {
        $this->agent_name = SupportSettings::name();
        $this->persona = SupportSettings::persona();
        $this->knowledge = SupportSettings::knowledge();
        $this->autopilot_enabled = \App\Support\SupportAutopilot::enabled();
        $this->goodwill_cap_usd = \App\Support\SupportAutopilot::goodwillCapUsd();
    }

    public function save(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $this->validate([
            'agent_name' => 'required|string|max:40',
            'persona' => 'required|string|max:2000',
            'knowledge' => 'nullable|string|max:20000',
            // Goodwill is deliberately capped low — it is the AI's only money lever.
            'goodwill_cap_usd' => 'required|numeric|min:0|max:20',
        ]);

        Setting::setValue('support.agent_name', trim($this->agent_name), 'support');
        Setting::setValue('support.persona', trim($this->persona), 'support');
        Setting::setValue('support.knowledge', trim($this->knowledge), 'support');
        Setting::setValue('support.autopilot.enabled', $this->autopilot_enabled, 'support');
        Setting::setValue('support.autopilot.goodwill_cap_usd', round((float) $this->goodwill_cap_usd, 2), 'support');
        SupportSettings::flush();
        \App\Support\SupportAutopilot::flush();

        Auditor::log('support.agent_updated', null, null, [
            'autopilot_enabled' => $this->autopilot_enabled,
            'goodwill_cap_usd' => round((float) $this->goodwill_cap_usd, 2),
        ]);
        $this->saved = 'Support agent updated.';
    }

    public function render()
    {
        return view('livewire.admin.support-agent', [
            'configured' => app(NaaraCareAgent::class)->available(),
        ]);
    }
}
