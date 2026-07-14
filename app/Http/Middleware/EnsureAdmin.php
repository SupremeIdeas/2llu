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
 *  2. Role — guests and anyone without an admin-panel role (super_admin, admin
 *     or staff) get a plain 404 (never a login page, so the secret path reveals
 *     nothing). Per-page scopes for staff are enforced by the `role`/
 *     `permission` middleware on the individual routes (blueprint Section 27).
 *  3. Two-factor — a panel user without a confirmed TOTP secret is redirected
 *     to the admin security page to enrol before any other admin page opens.
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

        // A GUEST at the admin path is sent to log in and returned here after
        // (the owner uses /adminmaster as the admin entry point with the setup
        // email + password). Authenticated NON-admins still get a plain 404, so
        // the panel stays invisible to ordinary users (blueprint Section 25).
        if ($user === null) {
            return redirect()->guest(route('login'));
        }
        if (! $user->hasAnyRole(['super_admin', 'admin', 'staff'])) {
            throw new NotFoundHttpException;
        }

        // Presence heartbeat (Module 25) — mark this panel user online so they
        // can be shown as available to take support tickets.
        \App\Support\StaffPresence::heartbeat($user);

        // 2FA is opt-in: only force enrolment when a super-admin has turned it on.
        if (\App\Support\SecuritySettings::admin2faRequired()
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
