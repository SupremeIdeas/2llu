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

    public ?string $saved = null;

    public function mount(): void
    {
        $this->agent_name = SupportSettings::name();
        $this->persona = SupportSettings::persona();
        $this->knowledge = SupportSettings::knowledge();
    }

    public function save(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        $this->validate([
            'agent_name' => 'required|string|max:40',
            'persona' => 'required|string|max:2000',
            'knowledge' => 'nullable|string|max:20000',
        ]);

        Setting::setValue('support.agent_name', trim($this->agent_name), 'support');
        Setting::setValue('support.persona', trim($this->persona), 'support');
        Setting::setValue('support.knowledge', trim($this->knowledge), 'support');
        SupportSettings::flush();

        Auditor::log('support.agent_updated');
        $this->saved = 'Support agent updated.';
    }

    public function render()
    {
        return view('livewire.admin.support-agent', [
            'configured' => app(NaaraCareAgent::class)->available(),
        ]);
    }
}
