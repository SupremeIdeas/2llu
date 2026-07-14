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

Route::get('/', function () {
    return view('welcome');
})->name('home');

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

// Public legal/help pages (blueprint Section 32).
Route::view('/legal', 'pages.legal')->name('legal');
Route::view('/faq', 'pages.faq')->name('faq');
Route::view('/refund-policy', 'pages.refund-policy')->name('refund-policy');

// Authenticated customer app (blueprint Sections 4, 12, 14, 16). `active`
// confines a self-paused account to the account page until it reactivates
// (Section 26.1).
Route::middleware(['auth', 'active'])->group(function () {
    // Money + core app routes additionally require a verified email (Module 22 /
    // blueprint Section 3) — a user must confirm their address before buying.
    Route::middleware('verified')->group(function () {
        Route::get('/dashboard', Dashboard::class)->name('dashboard');
        Route::get('/catalogue', Catalogue::class)->name('catalogue');
        Route::get('/checkout/{plan}', Checkout::class)->name('checkout');
        Route::get('/wallet', Wallet::class)->name('wallet');
        Route::get('/numbers', GetNumber::class)->name('numbers');
        Route::get('/referrals', Referrals::class)->name('referrals');

        // Data estimator (blueprint Section 32).
        Route::get('/data-estimator', \App\Livewire\DataEstimator::class)->name('data-estimator');
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
            Route::get('/errors', \App\Livewire\Admin\ErrorLogViewer::class)->name('errors');
            Route::get('/appearance', \App\Livewire\Admin\Splash::class)->name('appearance');
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
