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

// Public blog (Module 30).
Route::get('/blog', [\App\Http\Controllers\BlogController::class, 'index'])->name('blog');
Route::get('/blog/{post:slug}', [\App\Http\Controllers\BlogController::class, 'show'])->name('blog.show');

// Public pricing page (Module 29) — real plans when live, estimate tiers before.
Route::get('/pricing', \App\Livewire\PricingPage::class)->name('pricing');

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
        Route::get('/referrals', Referrals::class)->name('referrals');

        // NaaraCredits rewards area (loyalty module) — opt-in earning.
        Route::get('/rewards', \App\Livewire\Rewards::class)->name('rewards');

        // Data estimator (blueprint Section 32).
        Route::get('/data-estimator', \App\Livewire\DataEstimator::class)->name('data-estimator');

        // Developer portal (ROADMAP §Layer 2) — only when the Developer API is
        // enabled (the api.enabled middleware 404s otherwise, hiding the program).
        Route::get('/developer', \App\Livewire\DeveloperPortal::class)
            ->middleware('api.enabled')->name('developer');
    });

    // Account & data rights (blueprint Section 26) — reachable while unverified
    // so a user can still manage or delete their account and resend the email.
    Route::get('/account', \App\Livewire\Account::class)->name('account');
    // Security Center (Module 23) — also reachable unverified (to change email).
    Route::get('/account/security', \App\Livewire\SecurityCenter::class)->name('security');
    // NaaraCare AI support chat (Module 24) — reachable unverified (they may need help).
    Route::get('/support', \App\Livewire\SupportChat::class)->name('support');
    // Private support voice clips (Module 25) — owner or ticket staff only.
    Route::get('/support/voice/{message}', \App\Http\Controllers\SupportVoiceController::class)->name('support.voice');
    Route::get('/account/export', \App\Http\Controllers\AccountExportController::class)
        ->name('account.export.download');
});

// Admin panel (blueprint Sections 13, 15, 17, 25). Mounted on the env-driven
// admin path; the `admin` middleware enforces the IP allow-list, a plain 404
// for guests/non-admins (never a login page), and TOTP 2FA enrolment. `auth`
// is intentionally omitted so unauthenticated visitors 404 instead of being
// bounced to /login. Throttled to blunt path probing.
Route::middleware(['admin', 'throttle:admin'])
    ->prefix(config('admin.path'))
    ->name('admin.')
    ->group(function () {
        // Panel entry + 2FA self-enrolment — any panel user (incl. staff).
        Route::get('/', \App\Livewire\Admin\Dashboard::class)->name('dashboard');
        Route::get('/security', \App\Livewire\Admin\Security::class)->name('security');

        // Admin configuration — super_admin & admin only (staff excluded).
        Route::middleware('role:super_admin|admin')->group(function () {
            Route::get('/pricing', \App\Livewire\Admin\Pricing::class)->name('pricing');
            Route::get('/pricing/architect', \App\Livewire\Admin\PricingArchitect::class)->name('pricing-architect');
            Route::get('/errors', \App\Livewire\Admin\ErrorLogViewer::class)->name('errors');
            Route::get('/appearance', \App\Livewire\Admin\Splash::class)->name('appearance');
            Route::get('/branding', \App\Livewire\Admin\Branding::class)->name('branding');
            Route::get('/site', \App\Livewire\Admin\SiteEditor::class)->name('site');
            Route::get('/chrome', \App\Livewire\Admin\SiteChromePage::class)->name('chrome');
            Route::get('/legal', \App\Livewire\Admin\LegalEditor::class)->name('legal');
            Route::get('/blog', \App\Livewire\Admin\Posts::class)->name('blog');
            Route::get('/pages', \App\Livewire\Admin\CustomPages::class)->name('pages');
            Route::get('/service-icons', \App\Livewire\Admin\ServiceIconsPage::class)->name('service-icons');
            Route::get('/banners', \App\Livewire\Admin\Banners::class)->name('banners');
            Route::get('/coupons', \App\Livewire\Admin\Coupons::class)->name('coupons');
            Route::get('/credits', \App\Livewire\Admin\Credits::class)->name('credits');
            Route::get('/developer-api', \App\Livewire\Admin\DeveloperApi::class)->name('developer-api');
            Route::get('/payouts', \App\Livewire\Admin\Payouts::class)->name('payouts');
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

// Payment gateway webhooks (blueprint Section 19.3): signature-verified,
// idempotent wallet credit.
Route::post('/webhooks/payments/{gateway}', PaymentWebhookController::class)
    ->name('webhooks.payments');

// Payout (transfer) webhooks (ROADMAP §Layer 0.2): signature-verified,
// idempotent payout-request settlement.
Route::post('/webhooks/payouts/{provider}', \App\Http\Controllers\Webhooks\PayoutWebhookController::class)
    ->name('webhooks.payouts');

// Rewarded-ad / offerwall postback (loyalty module): HMAC-verified,
// idempotent credit grant. Both verbs — networks vary.
Route::match(['get', 'post'], '/webhooks/offerwall', \App\Http\Controllers\Webhooks\OfferwallPostbackController::class)
    ->name('webhooks.offerwall');
