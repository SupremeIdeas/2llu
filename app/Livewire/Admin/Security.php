<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Support\Auditor;
use App\Support\SecuritySettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Security (blueprint Section 25). TOTP two-factor enrolment for the
 * admin panel: an admin without a confirmed secret is routed here and cannot
 * reach any other admin page until 2FA is confirmed. Uses Fortify's action
 * classes directly (the admin is already authenticated + role-checked).
 */
#[Layout('components.layouts.admin')]
class Security extends Component
{
    /** Show the QR/secret + confirm step after enabling. */
    public bool $showingSetup = false;

    /** TOTP code the admin reads from their authenticator app. */
    public string $code = '';

    /** Admin-toggleable site protection (super-admin only). */
    public bool $csp_enabled = true;

    public bool $hsts_enabled = true;

    public ?string $siteSaved = null;

    public ?string $saved = null;

    public function mount(): void
    {
        $s = SecuritySettings::current();
        $this->csp_enabled = $s['csp_enabled'];
        $this->hsts_enabled = $s['hsts_enabled'];
    }

    /**
     * Save the site-protection toggles. Super-admin only; takes effect
     * immediately (no redeploy) and is audited.
     */
    public function saveSiteProtection(): void
    {
        abort_unless(Auth::user()->hasRole('super_admin'), 403);

        Setting::setValue('security.csp_enabled', $this->csp_enabled, 'security');
        Setting::setValue('security.hsts_enabled', $this->hsts_enabled, 'security');
        SecuritySettings::flush();

        Auditor::log('security.settings_updated', null, null, [
            'csp' => $this->csp_enabled,
            'hsts' => $this->hsts_enabled,
        ]);

        $this->siteSaved = 'Site protection saved — it applies immediately.';
    }

    public function enable(EnableTwoFactorAuthentication $enable): void
    {
        $enable(Auth::user());
        $this->showingSetup = true;
        $this->saved = null;
    }

    public function confirm(ConfirmTwoFactorAuthentication $confirm): void
    {
        $this->validate(['code' => 'required|string']);

        try {
            $confirm(Auth::user(), $this->code);
        } catch (ValidationException $e) {
            $this->addError('code', 'That code is invalid or has expired — try the current one.');

            return;
        }

        $this->showingSetup = false;
        $this->code = '';
        $this->saved = 'Two-factor authentication is on. Your admin account is now protected.';
        Auditor::log('admin.2fa_confirmed');
    }

    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generate): void
    {
        $generate(Auth::user());
        $this->saved = 'New recovery codes generated — save them somewhere safe.';
    }

    /**
     * Only a super_admin may switch 2FA back off (self-protection against an
     * admin locking the org out of the enforcement).
     */
    public function disable(DisableTwoFactorAuthentication $disable): void
    {
        abort_unless(Auth::user()->hasRole('super_admin'), 403);

        $disable(Auth::user());
        $this->showingSetup = false;
        $this->saved = 'Two-factor authentication disabled.';
        Auditor::log('admin.2fa_disabled');
    }

    public function render()
    {
        $user = Auth::user()->fresh();

        $enabled = ! is_null($user->two_factor_secret);
        $confirmed = $enabled && ! is_null($user->two_factor_confirmed_at);

        $qr = $secret = null;
        $recoveryCodes = [];
        if ($enabled) {
            $qr = $user->twoFactorQrCodeSvg();
            $secret = decrypt($user->two_factor_secret);
            $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true) ?? [];
        }

        return view('livewire.admin.security', [
            'enabled' => $enabled,
            'confirmed' => $confirmed,
            'qr' => $qr,
            'secret' => $secret,
            'recoveryCodes' => $recoveryCodes,
            'canDisable' => $user->hasRole('super_admin'),
            'canManageSite' => $user->hasRole('super_admin'),
        ]);
    }
}
