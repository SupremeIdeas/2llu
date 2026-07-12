<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Sanctum-authenticated API (blueprint Section 3.1). Provider/order endpoints
// are added in their respective modules; every external call runs as a queued
// job, never synchronously in the request cycle.
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
