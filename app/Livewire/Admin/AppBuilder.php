<?php

namespace App\Livewire\Admin;

use App\Models\AppBuild;
use App\Services\AppExport\BuildDispatcher;
use App\Support\AppExport;
use App\Support\Auditor;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Admin → App Builder (App Export prompt §1). One place for Frank to configure
 * the exported Android/iOS app — name, colours, splash, icon, version — upload
 * signing credentials, trigger builds, watch their real status, and toggle where
 * the "Download the app" CTA appears. Honest-state throughout: a compiled build
 * is never presented as "published". Re-authorized every request.
 */
#[Layout('components.layouts.admin')]
class AppBuilder extends Component
{
    use WithFileUploads;
    use WithPagination;

    public array $form = [];

    /** placements[key] => ['active' => bool, 'label' => string] */
    public array $placements = [];

    public $splash = null;
    public $icon = null;
    public $keystore = null;

    public function booted(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function mount(): void
    {
        $all = AppExport::all();
        $this->form = collect($all)->except('placements')->all();

        foreach (AppExport::PLACEMENTS as $key => $label) {
            $this->placements[$key] = [
                'active' => (bool) ($all['placements'][$key]['active'] ?? false),
                'label' => (string) ($all['placements'][$key]['label'] ?? ''),
            ];
        }
    }

    public function save(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);

        $data = $this->validate([
            'form.app_name' => 'required|string|max:60',
            'form.short_name' => 'required|string|max:24',
            'form.theme_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'form.background_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'form.version' => 'required|string|regex:/^\d+\.\d+\.\d+$/',
            'form.build_number' => 'required|integer|min:1',
            'form.changelog' => 'nullable|string|max:2000',
            'form.preloader' => 'required|in:'.implode(',', AppExport::PRELOADERS),
            'form.download_enabled' => 'boolean',
            'form.android_store_live' => 'boolean',
            'form.android_store_url' => 'nullable|url|max:300',
            'form.ios_store_live' => 'boolean',
            'form.ios_store_url' => 'nullable|url|max:300',
            'form.ci_webhook_url' => 'nullable|url|max:300',
            'form.privacy_policy_url' => 'nullable|url|max:300',
            'form.support_email' => 'nullable|email|max:190',
            'form.support_url' => 'nullable|url|max:300',
            'form.account_deletion_url' => 'nullable|string|max:300',
            'form.category' => 'nullable|string|max:60',
            'form.content_rating' => 'nullable|string|max:60',
            'form.short_description' => 'nullable|string|max:200',
            'form.full_description' => 'nullable|string|max:4000',
            'form.keywords' => 'nullable|string|max:200',
            'form.data_safety' => 'nullable|string|max:2000',
            'form.min_android_target_api' => 'nullable|integer|min:30|max:40',
            'form.permissions_note' => 'nullable|string|max:1000',
            'form.onboarding_enabled' => 'boolean',
            'splash' => 'nullable|image|mimes:webp,png,jpg,jpeg|max:3000',
            'icon' => 'nullable|image|mimes:webp,png,jpg,jpeg|max:2000',
        ]);

        // Persist the full form (incl. onboarding slides array) — AppExport::save
        // keeps only known keys, so unvalidated extras can't sneak in.
        $payload = array_merge($this->form, $data['form']);

        if ($this->splash) {
            $payload['splash_url'] = MediaStorage::storePublic($this->splash, 'app-export');
            $this->splash = null;
        }
        if ($this->icon) {
            $payload['icon_url'] = MediaStorage::storePublic($this->icon, 'app-export');
            $this->icon = null;
        }

        // A store listing can't be "live" without a URL — guard the honest-state rule.
        if (($payload['android_store_live'] ?? false) && empty($payload['android_store_url'])) {
            $this->addError('form.android_store_url', 'Add the Play Store URL before marking it live.');

            return;
        }
        if (($payload['ios_store_live'] ?? false) && empty($payload['ios_store_url'])) {
            $this->addError('form.ios_store_url', 'Add the App Store URL before marking it live.');

            return;
        }

        // Placements — normalise to the known keys only.
        $placements = [];
        foreach (AppExport::PLACEMENTS as $key => $label) {
            $placements[$key] = [
                'active' => (bool) ($this->placements[$key]['active'] ?? false),
                'label' => trim((string) ($this->placements[$key]['label'] ?? '')),
            ];
        }
        $payload['placements'] = $placements;

        AppExport::save($payload);
        $this->form = collect(AppExport::all())->except('placements')->all();
        Auditor::log('appexport.settings_saved', null, null, ['version' => $payload['version']]);
        $this->dispatch('nx-toast', type: 'success', message: 'App settings saved.');
    }

    /** Onboarding slide upload (portrait first-run images). */
    public $slideImage = null;

    public function addSlide(): void
    {
        $slides = array_values((array) ($this->form['onboarding_slides'] ?? []));
        $slides[] = ['image' => '', 'title' => '', 'subtitle' => ''];
        $this->form['onboarding_slides'] = array_slice($slides, 0, 5);
    }

    public function removeSlide(int $index): void
    {
        $slides = array_values((array) ($this->form['onboarding_slides'] ?? []));
        unset($slides[$index]);
        $this->form['onboarding_slides'] = array_values($slides);
    }

    public function uploadSlideImage(int $index): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
        $this->validate(['slideImage' => 'required|image|mimes:webp,png,jpg,jpeg|max:3000']);
        $url = MediaStorage::storePublic($this->slideImage, 'app-onboarding');
        $this->slideImage = null;

        $slides = array_values((array) ($this->form['onboarding_slides'] ?? []));
        if (isset($slides[$index])) {
            $slides[$index]['image'] = $url;
            $this->form['onboarding_slides'] = $slides;
        }
    }

    public function uploadKeystore(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
        $this->validate(['keystore' => 'required|file|max:2000']);

        $base64 = base64_encode(file_get_contents($this->keystore->getRealPath()));
        AppExport::storeCredential('android_keystore', $base64, $this->keystore->getClientOriginalName());
        $this->keystore = null;
        Auditor::log('appexport.keystore_uploaded');
        $this->dispatch('nx-toast', type: 'success', message: 'Keystore stored (encrypted). Back it up now — see the warning.');
    }

    public function acknowledgeBackup(): void
    {
        AppExport::save(['keystore_backed_up' => true]);
        $this->form['keystore_backed_up'] = true;
        Auditor::log('appexport.keystore_backup_ack');
    }

    public function generateBuild(string $platform, string $artifactType): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
        app(BuildDispatcher::class)->create($platform, $artifactType, Auth::user());
        $this->dispatch('nx-toast', type: 'success', message: ucfirst($platform).' build queued.');
    }

    public function render()
    {
        return view('livewire.admin.app-builder', [
            'placementLabels' => AppExport::PLACEMENTS,
            'preloaders' => AppExport::PRELOADERS,
            'hasKeystore' => AppExport::hasCredential('android_keystore'),
            'keystoreMeta' => AppExport::credentialMeta('android_keystore'),
            'builds' => AppBuild::latest('id')->paginate(8),
            'checklist' => AppExport::publishChecklist(),
            'score' => AppExport::readinessScore(),
        ]);
    }
}
