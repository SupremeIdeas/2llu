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
        // Fresh-upload safeguards (blueprint S22): before the web installer runs,
        // seed an APP_KEY (else the encrypting middleware 500s with no .env) and
        // force file-based session/cache (else StartSession queries a database
        // that doesn't exist yet). Both no-op once the app is installed.
        \App\Support\Installer::bootstrapKey();
        \App\Support\Installer::useSafeDriversUntilInstalled();

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
        $this->app->singleton('pay.paypal', \App\Services\Payments\PaypalGateway::class);
        $this->app->singleton('pay.binance', \App\Services\Payments\BinancePayGateway::class);
        $this->app->singleton('pay.nowpayments', \App\Services\Payments\NowPaymentsGateway::class);
        $this->app->singleton('pay.cryptomus', \App\Services\Payments\CryptomusGateway::class);
        $this->app->singleton('pay.coinpayments', \App\Services\Payments\CoinPaymentsGateway::class);
        $this->app->singleton('pay.payssion', \App\Services\Payments\PayssionGateway::class);

        // Payout account resolution (ROADMAP §Layer 0.1). Paystack first for its
        // markets, Flutterwave as the wider-net resolver. Injected as a list so
        // tests can drive the service with fakes.
        $this->app->singleton(\App\Services\Payouts\PayoutAccountService::class, fn ($app) => new \App\Services\Payouts\PayoutAccountService([
            $app->make(\App\Services\Payouts\PaystackBankResolver::class),
            $app->make(\App\Services\Payouts\FlutterwaveBankResolver::class),
        ]));

        // Payout (money-out) gateways, resolved by name via app("payout.$provider"),
        // and the engine that owns the withdrawal lifecycle (ROADMAP §Layer 0.2).
        $this->app->singleton('payout.paystack', \App\Services\Payouts\PaystackPayoutGateway::class);
        $this->app->singleton('payout.flutterwave', \App\Services\Payouts\FlutterwavePayoutGateway::class);
        $this->app->singleton(\App\Services\Payouts\PayoutService::class, fn ($app) => new \App\Services\Payouts\PayoutService([
            $app->make(\App\Services\Payouts\PaystackPayoutGateway::class),
            $app->make(\App\Services\Payouts\FlutterwavePayoutGateway::class),
        ]));

        // KYC/identity providers, resolved by name via app("kyc.$provider"), and
        // the service that owns verification state (ROADMAP §Layer 0.3). Manual
        // review is the always-available fallback.
        $this->app->singleton('kyc.manual', \App\Services\Kyc\ManualKycProvider::class);
        $this->app->singleton('kyc.smileid', \App\Services\Kyc\SmileIdKycProvider::class);
        $this->app->singleton('kyc.dojah', \App\Services\Kyc\DojahKycProvider::class);
        $this->app->singleton(\App\Services\Kyc\KycService::class, fn ($app) => new \App\Services\Kyc\KycService([
            $app->make(\App\Services\Kyc\ManualKycProvider::class),
            $app->make(\App\Services\Kyc\SmileIdKycProvider::class),
            $app->make(\App\Services\Kyc\DojahKycProvider::class),
        ]));

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

        // NaaraCare AI support agent (Module 24). Prod impl calls the Anthropic
        // Messages API with tool-use, gated on the key; tests inject a fake.
        $this->app->bind(
            \App\Services\Support\Contracts\ChatModel::class,
            \App\Services\Support\ClaudeChatModel::class,
        );

        // Support voice (Module 25) — ElevenLabs in prod; faked in tests.
        $this->app->bind(
            \App\Services\Support\Contracts\VoiceSynthesizer::class,
            \App\Services\Support\ElevenLabsVoice::class,
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

        // A reversed/failed credit withdrawal returns the held credits
        // (ROADMAP §Layer 1). Registered explicitly so it fires regardless of
        // listener auto-discovery.
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\PayoutReversed::class,
            \App\Listeners\ReturnWithdrawnCredits::class,
        );

        // A reversed/failed merchant-earnings withdrawal returns the held
        // earnings to the merchant bucket (ROADMAP §Layer 3.4).
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\PayoutReversed::class,
            \App\Listeners\ReturnMerchantEarnings::class,
        );

        // Extend Socialite with the extra sign-in providers (owner request).
        // Google/Facebook/Twitter are core drivers; Apple/Microsoft/Discord are
        // registered here via their SocialiteProviders packages.
        \Illuminate\Support\Facades\Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
            $event->extendSocialite('apple', \SocialiteProviders\Apple\Provider::class);
            $event->extendSocialite('microsoft', \SocialiteProviders\Microsoft\Provider::class);
            $event->extendSocialite('discord', \SocialiteProviders\Discord\Provider::class);
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
            if (\App\Support\SupportSettings::isSupportKey($setting->key)) {
                \App\Support\SupportSettings::flush();
            }
            if (\App\Support\SupportAutopilot::isAutopilotKey($setting->key)) {
                \App\Support\SupportAutopilot::flush();
            }
            if (\App\Support\BrandSettings::isBrandKey($setting->key)) {
                \App\Support\BrandSettings::flush();
            }
            if (\App\Support\SiteContent::isSiteKey($setting->key)) {
                \App\Support\SiteContent::flush();
            }
            if (\App\Support\NumberCatalogue::isCatalogueKey($setting->key)) {
                \App\Support\NumberCatalogue::flush();
            }
            if (\App\Support\ServiceIcons::isServiceIconKey($setting->key)) {
                \App\Support\ServiceIcons::flush();
            }
            if (\App\Support\SiteChrome::isChromeKey($setting->key)) {
                \App\Support\SiteChrome::flush();
            }
            if (\App\Support\LegalContent::isLegalKey($setting->key)) {
                \App\Support\LegalContent::flush();
            }
            if (\App\Support\CreditSettings::isCreditKey($setting->key)) {
                \App\Support\CreditSettings::flush();
            }
        });

        // Banner cache follows the Banner model itself (Module 31).
        \App\Models\Banner::saved(fn () => \App\Support\Banners::flush());
        \App\Models\Banner::deleted(fn () => \App\Support\Banners::flush());

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
