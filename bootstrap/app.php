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
            'active' => \App\Http\Middleware\EnsureActive::class,
            'installer' => \App\Http\Middleware\EnsureNotInstalled::class,
            // Staff role/scope gating (blueprint Section 27).
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);

        // Security headers on every web response (blueprint Section 19.2; the
        // full CSP matrix is Module 19).
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Durable, exportable error capture (Section 17.5).
        $exceptions->report(function (\Throwable $e) {
            \App\Support\ErrorLogger::capture($e);
        });
    })->create();
