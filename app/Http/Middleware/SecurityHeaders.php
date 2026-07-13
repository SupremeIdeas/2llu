<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security response headers (blueprint Sections 19.2 & 30): the baseline set,
 * plus a Content-Security-Policy tuned for the TALL stack and HSTS over HTTPS.
 * The CSP + HSTS are config-driven (config/security.php) so they can be tuned
 * without a code change.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-Permitted-Cross-Domain-Policies' => 'none',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
        ];

        // CSP + HSTS are admin-toggleable at runtime (config is the default).
        if (\App\Support\SecuritySettings::cspEnabled() && filled(config('security.csp.policy'))) {
            $headers['Content-Security-Policy'] = config('security.csp.policy');
        }

        if (\App\Support\SecuritySettings::hstsEnabled() && $request->secure()) {
            $headers['Strict-Transport-Security'] = 'max-age='.config('security.hsts.max_age').'; includeSubDomains';
        }

        foreach ($headers as $key => $value) {
            if (! $response->headers->has($key)) {
                $response->headers->set($key, $value);
            }
        }

        return $response;
    }
}
