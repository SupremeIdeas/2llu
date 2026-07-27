<?php

use App\Http\Controllers\InstallController;
use App\Http\Controllers\Webhooks\GetatextWebhookController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use App\Livewire\Catalogue;
use App\Livewire\Checkout;
use App\Livewire\Dashboard;
use App\Livewire\GetNumber;
use App\Livewire\Referrals;
use App\Livewire\Wallet;
use Illuminate\Support\Facades\Route;

// Public marketing site (Module 27) — CMS-driven pages (SiteContent), edited
// from Admin → Pages with no redeploy.
Route::get('/', fn () => view('marketing.home', ['sections' => \App\Support\SiteContent::page('home')]))->name('home');
Route::get('/about', fn () => view('marketing.about', ['sections' => \App\Support\SiteContent::page('about')]))->name('about');
Route::get('/how-it-works', fn () => view('marketing.how-it-works', ['sections' => \App\Support\SiteContent::page('how-it-works')]))->name('how-it-works');
Route::get('/contact', fn () => view('marketing.contact', ['sections' => \App\Support\SiteContent::page('contact')]))->name('contact');

// Public Developer API documentation (ROADMAP §Layer 2) — renders the canonical
// docs/DEVELOPER-API.md reference as a browsable, branded page.
Route::get('/developers', \App\Http\Controllers\DeveloperDocsController::class)->name('developers');

// Admin-authored custom-HTML pages (CMS). Prefixed to /p/ so it can never shadow
// a built-in route; only published, non-reserved slugs resolve.
Route::get('/p/{slug}', \App\Http\Controllers\CustomPageController::class)
    ->where('slug', '[a-z0-9-]+')->name('custom-page');

// Web installer (blueprint Section 22.1). Active only until the lock file
// exists (EnsureNotInstalled).
Route::middleware('installer')->prefix('install')->group(function () {
    Route::get('/', [InstallController::class, 'welcome']);
    Route::get('/requirements', [InstallController::class, 'requirements']);
    Route::get('/setup', [InstallController::class, 'setup']);
    Route::post('/setup', [InstallController::class, 'install'])->name('install.run');
});

// Social login (Module 23). Guarded internally by SocialLogin::googleEnabled().
Route::get('/auth/{provider}/redirect', [\App\Http\Controllers\Auth\SocialAuthController::class, 'redirect'])
    ->name('social.redirect');
Route::get('/auth/{provider}/callback', [\App\Http\Controllers\Auth\SocialAuthController::class, 'callback'])
    ->name('social.callback');

// Public legal/help pages (blueprint Section 32; Module 30 legal CMS).
Route::get('/legal', fn () => view('legal.index', ['docs' => \App\Support\LegalContent::all()]))->name('legal');
Route::get('/legal/{slug}', function (string $slug) {
    abort_unless(\App\Support\LegalContent::exists($slug), 404);

    return view('legal.show', ['doc' => \App\Support\LegalContent::doc($slug)]);
})->name('legal.show');
Route::get('/refund-policy', fn () => view('legal.show', ['doc' => \App\Support\LegalContent::doc('refund')]))->name('refund-policy');
Route::view('/faq', 'pages.faq')->name('faq');

// Installable app: dynamic PWA manifest + public "Download the App" page.
Route::get('/manifest.webmanifest', \App\Http\Controllers\ManifestController::class)->name('manifest');
Route::get('/download', \App\Http\Controllers\DownloadAppController::class)->name('download');
Route::view('/offline', 'pages.offline')->name('offline');
// First-run onboarding carousel (installed app) → lands on login.
Route::get('/get-started', \App\Http\Controllers\OnboardingController::class)->name('onboarding');

// Public, unauthenticated system status page (for users + Developer API integrators).
Route::get('/status', \App\Livewire\StatusPage::class)->name('status');

// Public blog (Module 30).
Route::get('/blog', [\App\Http\Controllers\BlogController::class, 'index'])->name('blog');
Route::get('/blog/{post:slug}', [\App\Http\Controllers\BlogController::class, 'show'])->name('blog.show');

// Public pricing page (Module 29) — real plans when live, estimate tiers before.
Route::get('/pricing', \App\Livewire\PricingPage::class)->name('pricing');

// Merchant invite landing (ROADMAP §Layer 3.3) — a reseller's co-branded
// storefront link. Captures the invite and sends the visitor to register.
Route::get('/merchant/{slug}/join', \App\Livewire\MerchantJoin::class)->name('merchant.join');

// Authenticated customer app (blueprint Sections 4, 12, 14, 16). `active`
// confines a self-paused account to the account page until it reactivates
// (Section 26.1).
Route::middleware(['auth', 'active'])->group(function () {
    // Money + core app routes require a verified email — but only once outgoing
    // mail is configured (owner request), so users are never trapped behind a
    // verification link that can't be sent yet.
    Route::middleware('verified.mail')->group(function () {
        Route::get('/dashboard', Dashboard::class)->name('dashboard');
        Route::get('/catalogue', Catalogue::class)->name('catalogue');
        Route::get('/checkout/{plan}', Checkout::class)->name('checkout');
        Route::get('/wallet', Wallet::class)->name('wallet');
        Route::get('/numbers', GetNumber::class)->name('numbers');
        // Call forwarding for permanent numbers (Live Voice — Part A). The
        // component 404s unless Twilio is Active (voice rides the same keys).
        Route::get('/numbers/forwarding', \App\Livewire\CallForwarding::class)->name('numbers.forwarding');
        // In-browser international dialer (Live Voice — Part B). Also 404s until
        // Twilio is Active. The token endpoint mints the short-lived WebRTC token.
        Route::get('/numbers/dialer', \App\Livewire\Dialer::class)->name('numbers.dialer');
        Route::post('/voice/token', \App\Http\Controllers\VoiceTokenController::class)->name('voice.token');
        // In-app contact book (Live Voice — Part C). Not provider-billed, so no
        // feature gate — standard auth-scoped CRUD that feeds the dialer.
        Route::get('/numbers/contacts', \App\Livewire\Contacts::class)->name('numbers.contacts');
        Route::get('/referrals', Referrals::class)->name('referrals');

        // NaaraCredits rewards area (loyalty module) — opt-in earning.
        Route::get('/rewards', \App\Livewire\Rewards::class)->name('rewards');

        // Partner profit-share earnings (dollars only; 404 for non-partners).
        Route::get('/partner', \App\Livewire\PartnerEarnings::class)->name('partner.earnings');

        // Cash out withdrawable (first-referral) credits (ROADMAP §Layer 1).
        // KYC L2 gated — unverified users are sent to /account/verify.
        Route::get('/rewards/withdraw', \App\Livewire\Withdraw::class)
            ->middleware('kyc:2')->name('rewards.withdraw');

        // Data estimator (blueprint Section 32).
        Route::get('/data-estimator', \App\Livewire\DataEstimator::class)->name('data-estimator');

        // Developer portal (ROADMAP §Layer 2) — only when the Developer API is
        // enabled (the api.enabled middleware 404s otherwise, hiding the program).
        Route::get('/developer', \App\Livewire\DeveloperPortal::class)
            ->middleware('api.enabled')->name('developer');

        // Become a merchant (ROADMAP §Layer 3.1) — KYB + application flow. The
        // page self-gates on the programme flag + KYC L3.
        Route::get('/merchant/apply', \App\Livewire\BecomeMerchant::class)->name('merchant.apply');
        // Merchant storefront dashboard (ROADMAP §Layer 3.5) — active merchants
        // only (404 otherwise): storefront, invite link, customers, earnings, payouts.
        Route::get('/merchant', \App\Livewire\MerchantDashboard::class)->name('merchant.dashboard');
        // Merchant V2 — client management (404s for a non-V2 merchant).
        Route::get('/merchant/clients', \App\Livewire\MerchantClients::class)->name('merchant.clients');
    });

    // Aurora Welcome entrance (first-login animation) — reachable while
    // unverified so it plays immediately after signup, then hands off to the
    // dashboard. Guards itself: no `just_registered` flag → straight to home.
    Route::get('/welcome', \App\Livewire\WelcomeAurora::class)->name('welcome');

    // Account & data rights (blueprint Section 26) — reachable while unverified
    // so a user can still manage or delete their account and resend the email.
    Route::get('/account', \App\Livewire\Account::class)->name('account');
    // Extended self-service profile (owner request).
    Route::get('/account/profile', \App\Livewire\Profile::class)->name('profile');
    // Security Center (Module 23) — also reachable unverified (to change email).
    Route::get('/account/security', \App\Livewire\SecurityCenter::class)->name('security');
    // Identity verification (ROADMAP §Layer 0.3) — KYC L2 gate for withdrawals.
    Route::get('/account/verify', \App\Livewire\IdentityVerification::class)->name('account.verify');
    // In-app notification centre (owner request) — the bell's "see all" page.
    Route::get('/notifications', \App\Livewire\Notifications::class)->name('notifications');
    // Self-hosted web-push subscribe/unsubscribe (owner request).
    Route::post('/push/subscribe', [\App\Http\Controllers\PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::post('/push/unsubscribe', [\App\Http\Controllers\PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');
    // NaaraCare AI support chat (Module 24) — reachable unverified (they may need help).
    Route::get('/support', \App\Livewire\SupportChat::class)->name('support');
    // Private support voice clips (Module 25) — owner or ticket staff only.
    Route::get('/support/voice/{message}', \App\Http\Controllers\SupportVoiceController::class)->name('support.voice');
    // Private support evidence attachments — owner or ticket staff only.
    Route::get('/support/attachment/{message}', \App\Http\Controllers\SupportAttachmentController::class)->name('support.attachment');
    Route::get('/account/export', \App\Http\Controllers\AccountExportController::class)
        ->name('account.export.download');
});

// Dedicated admin sign-in page (blueprint Section 25). Lives at
// {ADMIN_PATH}/login OUTSIDE the `admin` gate so guests can reach it; EnsureAdmin
// redirects unauthenticated panel visitors here (a branded admin login) instead
// of the customer login. Posts to Fortify /login. Throttled like the panel.
Route::get(config('admin.path').'/login', \App\Http\Controllers\Admin\LoginController::class)
    ->middleware('throttle:admin')->name('admin.login');

// Admin password recovery via security questions (guest, hard-throttled). A
// self-service path when email reset isn't available on a self-hosted install.
Route::get(config('admin.path').'/recover', \App\Livewire\Admin\RecoverPassword::class)
    ->middleware('throttle:admin')->name('admin.recover');

// Admin panel (blueprint Sections 13, 15, 17, 25). Mounted on the env-driven
// admin path; the `admin` middleware enforces the IP allow-list, a redirect to
// the dedicated admin login for guests, a plain 404 for signed-in non-admins,
// and TOTP 2FA enrolment. `auth` is intentionally omitted so EnsureAdmin owns
// the guest handling. Throttled to blunt path probing.
Route::middleware(['admin', 'throttle:admin'])
    ->prefix(config('admin.path'))
    ->name('admin.')
    ->group(function () {
        // Panel entry + 2FA self-enrolment — any panel user (incl. staff).
        Route::get('/', \App\Livewire\Admin\Dashboard::class)->name('dashboard');
        Route::get('/security', \App\Livewire\Admin\Security::class)->name('security');
        // Personal account (any panel user manages their own name/email/password).
        Route::get('/account', \App\Livewire\Admin\Account::class)->name('account');

        // Admin configuration — super_admin & admin only (staff excluded).
        Route::middleware('role:super_admin|admin')->group(function () {
            Route::get('/pricing', \App\Livewire\Admin\Pricing::class)->name('pricing');
            Route::get('/pricing/architect', \App\Livewire\Admin\PricingArchitect::class)->name('pricing-architect');
            Route::get('/errors', \App\Livewire\Admin\ErrorLogViewer::class)->name('errors');
            // Feature toggles (owner request) — switch features on/off + setup guides.
            Route::get('/features', \App\Livewire\Admin\Features::class)->name('features');
            // eSIM storefront hero (esim_upgrade Part 2) — title, description, images.
            Route::get('/esim-hero', \App\Livewire\Admin\EsimHero::class)->name('esim-hero');
            Route::get('/numbers-hero', \App\Livewire\Admin\NumbersHero::class)->name('numbers-hero');
            Route::get('/numbers-cards', \App\Livewire\Admin\NumbersBento::class)->name('numbers-cards');
            Route::get('/appearance', \App\Livewire\Admin\Splash::class)->name('appearance');
            Route::get('/dashboard-theme', \App\Livewire\Admin\PlatformThemePage::class)->name('dashboard-theme');
            Route::get('/branding', \App\Livewire\Admin\Branding::class)->name('branding');
            Route::get('/site', \App\Livewire\Admin\SiteEditor::class)->name('site');
            Route::get('/chrome', \App\Livewire\Admin\SiteChromePage::class)->name('chrome');
            Route::get('/legal', \App\Livewire\Admin\LegalEditor::class)->name('legal');
            Route::get('/blog', \App\Livewire\Admin\Posts::class)->name('blog');
            Route::get('/pages', \App\Livewire\Admin\CustomPages::class)->name('pages');
            Route::get('/builder', \App\Livewire\Admin\PageBuilder::class)->name('builder');
            Route::get('/app-builder', \App\Livewire\Admin\AppBuilder::class)->name('app-builder');
            Route::get('/welcome-settings', \App\Livewire\Admin\WelcomeSettings::class)->name('welcome-settings');
            Route::get('/incidents', \App\Livewire\Admin\Incidents::class)->name('incidents');
            Route::get('/notices', \App\Livewire\Admin\Alerts::class)->name('notices');
            Route::get('/service-icons', \App\Livewire\Admin\ServiceIconsPage::class)->name('service-icons');
            Route::get('/banners', \App\Livewire\Admin\Banners::class)->name('banners');
            Route::get('/coupons', \App\Livewire\Admin\Coupons::class)->name('coupons');
            // Announcements & offers — push to every user's notification bell.
            Route::get('/announcements', \App\Livewire\Admin\Announcements::class)->name('announcements');
            Route::get('/credits', \App\Livewire\Admin\Credits::class)->name('credits');
            Route::get('/developer-api', \App\Livewire\Admin\DeveloperApi::class)->name('developer-api');
            Route::get('/payouts', \App\Livewire\Admin\Payouts::class)->name('payouts');
            Route::get('/kyc', \App\Livewire\Admin\KycReview::class)->name('kyc');
            Route::get('/merchants', \App\Livewire\Admin\Merchants::class)->name('merchants');
            Route::get('/partners', \App\Livewire\Admin\Partners::class)->name('partners');
            Route::get('/users', \App\Livewire\Admin\Users::class)->name('users');
            // Growth stack: social links, tracking pixels, social sign-in guides.
            Route::get('/integrations', \App\Livewire\Admin\Integrations::class)->name('integrations');
            Route::get('/deletions', \App\Livewire\Admin\AccountDeletions::class)->name('deletions');
            Route::get('/support-agent', \App\Livewire\Admin\SupportAgent::class)->name('support-agent');
        });

        // Support ticket queue (Module 25) — staff with the tickets.manage scope,
        // plus admin/super_admin (who hold every scope / bypass).
        Route::middleware('permission:tickets.manage')->group(function () {
            Route::get('/tickets', \App\Livewire\Admin\SupportQueue::class)->name('tickets');
        });

        // Staff, backups + maintenance loop — super_admin only (Sections 27–29).
        Route::middleware('role:super_admin')->group(function () {
            Route::get('/staff', \App\Livewire\Admin\Staff::class)->name('staff');
            Route::get('/api-keys', \App\Livewire\Admin\ProviderKeys::class)->name('api-keys');
            Route::get('/email', \App\Livewire\Admin\EmailSettings::class)->name('email');
            Route::get('/backups', \App\Livewire\Admin\Backups::class)->name('backups');
            Route::get('/maintenance', \App\Livewire\Admin\Maintenance::class)->name('maintenance');
            Route::get('/ui-kit', \App\Livewire\Admin\UiKit::class)->name('ui-kit');
        });
    });

// Provider webhooks (CSRF-exempt — see bootstrap/app.php). Getatext OTP
// delivery (blueprint Section 8.2).
Route::post('/webhooks/getatext', GetatextWebhookController::class)
    ->name('webhooks.getatext');

// Twilio inbound-call webhook (Live Voice — Part A): signature-verified, returns
// TwiML that forwards the call to the user's configured target.
Route::post('/webhooks/twilio/voice', \App\Http\Controllers\Webhooks\TwilioVoiceWebhookController::class)
    ->name('webhooks.twilio.voice');

// Twilio in-browser dialer webhooks (Live Voice — Part B): signature-verified.
// `dial` returns the outbound <Dial> TwiML (with a funded timeLimit) for a
// pre-authorised call; `dial-status` settles the wallet on hang-up.
Route::post('/webhooks/twilio/dial', \App\Http\Controllers\Webhooks\TwilioDialerWebhookController::class)
    ->name('webhooks.twilio.dial');
Route::post('/webhooks/twilio/dial-status', \App\Http\Controllers\Webhooks\TwilioDialStatusWebhookController::class)
    ->name('webhooks.twilio.dial-status');

// Payment gateway webhooks (blueprint Section 19.3): signature-verified,
// idempotent wallet credit.
Route::post('/webhooks/payments/{gateway}', PaymentWebhookController::class)
    ->name('webhooks.payments');

// Payout (transfer) webhooks (ROADMAP §Layer 0.2): signature-verified,
// idempotent payout-request settlement.
Route::post('/webhooks/payouts/{provider}', \App\Http\Controllers\Webhooks\PayoutWebhookController::class)
    ->name('webhooks.payouts');

// KYC result callbacks (ROADMAP §Layer 0.3): signature-verified, idempotent
// verification decisions.
Route::post('/webhooks/kyc/{provider}', \App\Http\Controllers\Webhooks\KycWebhookController::class)
    ->name('webhooks.kyc');

// Rewarded-ad / offerwall postback (loyalty module): HMAC-verified,
// idempotent credit grant. Both verbs — networks vary.
Route::match(['get', 'post'], '/webhooks/offerwall', \App\Http\Controllers\Webhooks\OfferwallPostbackController::class)
    ->name('webhooks.offerwall');

// Native app build status callback (App Export §1): HMAC-verified, flips a
// build queued→building→ready/failed and attaches the artifact + logs.
Route::post('/webhooks/appbuild/{provider}', \App\Http\Controllers\Webhooks\AppBuildWebhookController::class)
    ->name('webhooks.appbuild');
