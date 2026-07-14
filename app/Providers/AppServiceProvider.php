<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // PricingEngine is the single owner of all price math (blueprint 1.4).
        $this->app->singleton(\App\Services\Pricing\PricingEngine::class);

        // WalletService is the single owner of wallet balance changes (1.2).
        $this->app->singleton(\App\Services\Wallet\WalletService::class);

        // eSIM providers, resolved by name via app("esim.$provider") — one
        // interface, one router (blueprint Section 5.1). Swappable by design.
        $this->app->singleton('esim.esimgo', \App\Services\eSIM\EsimGoService::class);
        $this->app->singleton('esim.airalo', \App\Services\eSIM\AiraloService::class);
        $this->app->singleton('esim.quibity', \App\Services\eSIM\QuibityService::class);

        // Number providers, resolved by name via app("number.$provider").
        // OTP/rental lane (SmsProviderInterface): Getatext (US), 5sim (global),
        // SMS-Activate (global backup). Permanent lane (NumberProviderInterface):
        // Twilio (primary), Telnyx (backup). Router never crosses lanes (S11).
        $this->app->singleton('number.getatext', \App\Services\SMS\GetatextService::class);
        $this->app->singleton('number.fivesim', \App\Services\SMS\FiveSimService::class);
        $this->app->singleton('number.smsactivate', \App\Services\SMS\SmsActivateService::class);
        $this->app->singleton('number.twilio', \App\Services\SMS\Numbers\TwilioService::class);
        $this->app->singleton('number.telnyx', \App\Services\SMS\Numbers\TelnyxService::class);

        // Payment gateways, resolved by name via app("pay.$gateway").
        $this->app->singleton('pay.flutterwave', \App\Services\Payments\FlutterwaveGateway::class);
        $this->app->singleton('pay.paystack', \App\Services\Payments\PaystackGateway::class);
        $this->app->singleton('pay.stripe', \App\Services\Payments\StripeGateway::class);

        // Claude-assisted maintenance loop (blueprint Section 29). Bound to the
        // production clients by default; both are gated on config and report
        // unavailable until configured. Tests swap in fakes.
        $this->app->bind(
            \App\Services\Maintenance\Contracts\FixProposer::class,
            \App\Services\Maintenance\ClaudeFixProposer::class,
        );
        $this->app->bind(
            \App\Services\Maintenance\Contracts\CodeHostClient::class,
            \App\Services\Maintenance\GitHubCodeHostClient::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Blueprint Section 3.1: super_admin bypasses every authorization gate.
        // Admins can only assign roles below their own (enforced per-action later).
        Gate::before(function ($user, string $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });

        // Overlay any admin-saved API credentials on top of config() so every
        // service keeps reading config('services.*') unchanged and providers
        // flip Active the moment a key is saved (blueprint Section 17.4, money
        // rule 10). Runs every request/job; degrades to .env pre-install.
        \App\Support\ProviderKeys::applyToConfig();

        // Same overlay for admin-managed outgoing-mail config (Module 22): the
        // operator sets the mailer + SMTP creds + "from" identity in the panel,
        // and every Mailable/Notification picks them up with no .env editing.
        \App\Support\MailSettings::applyToConfig();

        // Custom-icon overrides are cached; bust that cache when the mapping
        // setting changes (blueprint Section 16.3).
        \App\Models\Setting::saved(function (\App\Models\Setting $setting) {
            if ($setting->key === 'ui.icon_overrides') {
                \App\Support\IconOverrides::flush();
            }
            if (\App\Support\SplashSettings::isSplashKey($setting->key)) {
                \App\Support\SplashSettings::flush();
            }
            if (\App\Support\SecuritySettings::isSecurityKey($setting->key)) {
                \App\Support\SecuritySettings::flush();
            }
            if (\App\Support\ProviderKeys::isProviderKey($setting->key)) {
                \App\Support\ProviderKeys::flush();
            }
            if (\App\Support\MailSettings::isMailKey($setting->key)) {
                \App\Support\MailSettings::flush();
            }
        });

        // Rate limits (blueprint Section 19.2): 300/min authenticated, 60/min
        // public; 10/min for order actions (enforced in the checkout components).
        RateLimiter::for('api', fn (Request $request) => $request->user()
            ? Limit::perMinute(300)->by($request->user()->id)
            : Limit::perMinute(60)->by($request->ip()));

        RateLimiter::for('orders', fn (Request $request) => Limit::perMinute(10)
            ->by(optional($request->user())->id ?: $request->ip()));

        // Admin area (blueprint Section 25): per-admin (or per-IP) cap to blunt
        // brute-force probing of the secret admin path.
        RateLimiter::for('admin', fn (Request $request) => Limit::perMinute(config('admin.throttle', 60))
            ->by('admin:'.(optional($request->user())->id ?: $request->ip())));
    }
}
