<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Sanctum SPA statefulness for first-party requests.
        $middleware->statefulApi();

        // Fresh upload with no lock file -> web installer (blueprint S22.1).
        $middleware->web(append: [
            \App\Http\Middleware\RedirectIfNotInstalled::class,
        ]);

        // Provider webhooks carry no CSRF token; verification is per-provider
        // (HMAC/shared-secret) inside each handler.
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'installer' => \App\Http\Middleware\EnsureNotInstalled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
