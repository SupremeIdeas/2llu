<?php

namespace App\Livewire\Admin;

use App\Support\Auditor;
use App\Support\ProviderKeys as ProviderKeysStore;
use App\Support\ProviderStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → API Keys (blueprint Sections 15 & 17.4, money-safety rule 10).
 * Super-admin only. Paste each provider/gateway/integration credential once
 * and it takes effect immediately — no .env editing, no redeploy, no coding.
 * Saved values are encrypted at rest and never echoed back to the browser:
 * the form shows a masked preview and blank inputs, so submitting an empty
 * field LEAVES the stored secret untouched. Providers flip Active as soon as
 * their required keys are present (mirrors the storefront "Coming Soon" gate).
 */
#[Layout('components.layouts.admin')]
class ProviderKeys extends Component
{
    /** field-name => new value typed by the admin (blank = leave as-is). */
    public array $inputs = [];

    public ?string $saved = null;

    public function mount(): void
    {
        // Always start blank — we never send stored secrets to the browser.
        foreach (array_keys(ProviderKeysStore::fieldMap()) as $field) {
            $this->inputs[$field] = '';
        }
    }

    /**
     * Save only the fields the admin actually typed into. A blank field is a
     * no-op (keeps whatever is stored) rather than an erase, so the operator
     * can update one key without re-pasting the rest.
     */
    public function save(): void
    {
        abort_unless(Auth::user()->hasRole('super_admin'), 403);

        $changed = array_filter(
            $this->inputs,
            fn ($v) => is_string($v) && trim($v) !== '',
        );

        if ($changed !== []) {
            ProviderKeysStore::save($changed);

            // Audit WHICH keys changed — never the values themselves.
            Auditor::log('providers.keys_updated', null, null, [
                'fields' => array_keys($changed),
            ]);
        }

        // Clear the inputs so secrets don't linger in the component state.
        foreach ($this->inputs as $field => $_) {
            $this->inputs[$field] = '';
        }

        $this->saved = $changed === []
            ? 'Nothing to save — enter a key to update it.'
            : 'API keys saved. They take effect immediately.';
    }

    public function render()
    {
        // Build a display model: the schema plus each field's masked preview
        // and each provider's live Active/Coming-Soon status.
        $schema = ProviderKeysStore::schema();
        $previews = [];
        foreach (ProviderKeysStore::fieldMap() as $field => $_) {
            $previews[$field] = ProviderKeysStore::preview($field);
        }

        return view('livewire.admin.provider-keys', [
            'schema' => $schema,
            'previews' => $previews,
            'statuses' => ProviderStatus::all(),
        ]);
    }
}
