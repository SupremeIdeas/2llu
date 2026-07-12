<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
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
    }
}
