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
            $policy = $this->policyWithTurnstile((string) config('security.csp.policy'));
            $policy = $this->policyWithConvai($policy);
            $headers['Content-Security-Policy'] = $policy;
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

    /**
     * When Turnstile is active, whitelist Cloudflare's origin in exactly the two
     * directives its widget needs (script-src + frame-src) and nowhere else, so
     * the challenge loads without loosening the policy for anything else.
     */
    private function policyWithTurnstile(string $policy): string
    {
        if (! \App\Support\Turnstile::active()) {
            return $policy;
        }

        $origin = \App\Support\Turnstile::ORIGIN;
        $directives = array_map('trim', explode(';', $policy));
        $sawFrame = false;

        foreach ($directives as $i => $directive) {
            if (str_starts_with($directive, 'script-src ') && ! str_contains($directive, $origin)) {
                $directives[$i] = $directive.' '.$origin;
            }
            if (str_starts_with($directive, 'frame-src ')) {
                $sawFrame = true;
                if (! str_contains($directive, $origin)) {
                    $directives[$i] = $directive.' '.$origin;
                }
            }
        }

        if (! $sawFrame) {
            $directives[] = "frame-src 'self' ".$origin;
        }

        return implode('; ', array_filter($directives));
    }

    /**
     * When the ElevenLabs Convai widget is active, add exactly the ElevenLabs
     * origins its embed needs (script/connect/frame/font + a blob worker) and
     * nowhere else. Gated on ConvaiWidget::active(), so the strict default policy
     * is untouched until an admin turns the widget on. (Task #17.)
     */
    private function policyWithConvai(string $policy): string
    {
        if (! \App\Support\ConvaiWidget::active()) {
            return $policy;
        }

        $add = \App\Support\ConvaiWidget::CSP;
        $directives = array_map('trim', explode(';', $policy));
        $present = [];

        foreach ($directives as $i => $directive) {
            foreach ($add as $name => $origins) {
                if (str_starts_with($directive, $name.' ')) {
                    $present[$name] = true;
                    foreach ($origins as $origin) {
                        if (! str_contains($directive, $origin)) {
                            $directive .= ' '.$origin;
                        }
                    }
                    $directives[$i] = $directive;
                }
            }
        }

        // Directives not already in the policy (e.g. worker-src) are appended.
        foreach ($add as $name => $origins) {
            if (empty($present[$name])) {
                $directives[] = $name.' '.implode(' ', $origins);
            }
        }

        return implode('; ', array_filter($directives));
    }
}
