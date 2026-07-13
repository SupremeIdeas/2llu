<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Gate the admin panel to admin/super_admin (staff scopes arrive in Module 16).
 * Non-admins get a plain 404 — the admin surface reveals nothing about itself
 * (blueprint Section 25; the env-driven /adminmaster path is hardened in
 * Module 14).
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->hasAnyRole(['super_admin', 'admin'])) {
            throw new NotFoundHttpException;
        }

        return $next($request);
    }
}
