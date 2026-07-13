<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Sanctum-authenticated API (blueprint Section 3.1). Rate limited 300/min
// authenticated, 60/min public (Section 19.2). Every external call runs as a
// queued job, never synchronously in the request cycle.
Route::middleware(['throttle:api', 'auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});
