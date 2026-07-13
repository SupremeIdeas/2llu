<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Gate the admin panel (blueprint Section 25). Hardening, in order:
 *
 *  1. Optional IP allow-list — an IP outside it gets a plain 404 and never
 *     learns the admin area exists.
 *  2. Role — guests and non-admins get a plain 404 (never a login page, so the
 *     secret path reveals nothing). Staff scopes arrive in Module 16.
 *  3. Two-factor — an admin without a confirmed TOTP secret is redirected to
 *     the admin security page to enrol before any other admin page opens.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $allow = config('admin.ip_allowlist', []);
        if (! empty($allow) && ! in_array($request->ip(), $allow, true)) {
            throw new NotFoundHttpException;
        }

        $user = $request->user();
        if ($user === null || ! $user->hasAnyRole(['super_admin', 'admin'])) {
            throw new NotFoundHttpException;
        }

        if (config('admin.require_2fa')
            && ! $this->hasConfirmedTwoFactor($user)
            && ! $request->routeIs('admin.security')) {
            return redirect()->route('admin.security');
        }

        return $next($request);
    }

    /**
     * A confirmed TOTP enrolment: the secret exists AND was confirmed (Fortify
     * runs with the 'confirm' feature, so confirmed_at is the reliable signal).
     */
    private function hasConfirmedTwoFactor(User $user): bool
    {
        return ! is_null($user->two_factor_secret)
            && ! is_null($user->two_factor_confirmed_at);
    }
}
