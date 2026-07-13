<?php

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

// Public legal/help pages (blueprint Section 32).
Route::view('/legal', 'pages.legal')->name('legal');
Route::view('/faq', 'pages.faq')->name('faq');

// Authenticated customer app (blueprint Sections 4, 12, 14, 16).
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/catalogue', Catalogue::class)->name('catalogue');
    Route::get('/checkout/{plan}', Checkout::class)->name('checkout');
    Route::get('/wallet', Wallet::class)->name('wallet');
    Route::get('/numbers', GetNumber::class)->name('numbers');
    Route::get('/referrals', Referrals::class)->name('referrals');
});

// Admin panel (blueprint Sections 13, 15, 17). Admin-only; the env-driven
// /adminmaster path + hard 404 hardening is Module 14.
Route::middleware(['auth', 'admin'])->prefix('adminmaster')->name('admin.')->group(function () {
    Route::get('/', \App\Livewire\Admin\Dashboard::class)->name('dashboard');
    Route::get('/pricing', \App\Livewire\Admin\Pricing::class)->name('pricing');
    Route::get('/errors', \App\Livewire\Admin\ErrorLogViewer::class)->name('errors');
});

// Provider webhooks (CSRF-exempt — see bootstrap/app.php). Getatext OTP
// delivery (blueprint Section 8.2).
Route::post('/webhooks/getatext', GetatextWebhookController::class)
    ->name('webhooks.getatext');

// Payment gateway webhooks (blueprint Section 19.3): signature-verified,
// idempotent wallet credit.
Route::post('/webhooks/payments/{gateway}', PaymentWebhookController::class)
    ->name('webhooks.payments');
