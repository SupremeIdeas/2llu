<?php

use App\Http\Controllers\Webhooks\GetatextWebhookController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Provider webhooks (CSRF-exempt — see bootstrap/app.php). Getatext OTP
// delivery (blueprint Section 8.2).
Route::post('/webhooks/getatext', GetatextWebhookController::class)
    ->name('webhooks.getatext');

// Payment gateway webhooks (blueprint Section 19.3): signature-verified,
// idempotent wallet credit.
Route::post('/webhooks/payments/{gateway}', PaymentWebhookController::class)
    ->name('webhooks.payments');
