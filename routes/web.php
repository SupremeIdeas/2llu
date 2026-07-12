<?php

use App\Http\Controllers\Webhooks\GetatextWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Provider webhooks (CSRF-exempt — see bootstrap/app.php). Getatext OTP
// delivery (blueprint Section 8.2).
Route::post('/webhooks/getatext', GetatextWebhookController::class)
    ->name('webhooks.getatext');
