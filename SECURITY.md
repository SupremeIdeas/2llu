# NaaraSim — Security Hardening Matrix (Blueprint Section 30)

Every OWASP mistake class and attack vector, mapped to the control that defends
against it in this codebase. Keep this current as modules change.

| Attack class | Control | Where |
|---|---|---|
| **Injection (SQL)** | Eloquent / query builder (parameterised); no string-built SQL in app code | throughout |
| **XSS (stored/reflected)** | Blade auto-escaping; `Content-Security-Policy` with **no external script origins**; SVG uploads sanitised | `SecurityHeaders`, `config/security.php`, `MediaStorage::sanitizeSvg` |
| **Clickjacking** | `X-Frame-Options: SAMEORIGIN` + CSP `frame-ancestors 'self'` | `SecurityHeaders` |
| **MIME sniffing** | `X-Content-Type-Options: nosniff` | `SecurityHeaders` |
| **Protocol downgrade / MITM** | HSTS over HTTPS; secure + HttpOnly + SameSite session cookies; session **encrypted at rest** | `SecurityHeaders`, `config/session.php` |
| **SSRF** | `SsrfGuard` rejects non-http(s) + private/reserved/loopback/link-local (incl. cloud-metadata `169.254.x`); optional host allow-list; `PublicUrl` rule for user URLs | `Support\Security\SsrfGuard`, `Rules\PublicUrl` |
| **Mass assignment** | `$fillable` allow-lists on every model; Livewire uses typed props + `validate()`, never `request()->all()` for business logic | models, Livewire components |
| **Broken access control** | Spatie roles + `EnsureAdmin` (env path, IP allow-list, 2FA, plain 404); per-route `role:`/`permission:` gates; `super_admin`-only destructive actions | `EnsureAdmin`, `routes/web.php`, services |
| **Auth brute-force** | Rate-limited login / two-factor / passkey limiters; order + admin + api throttles | `FortifyServiceProvider`, `AppServiceProvider` |
| **CSRF** | Laravel CSRF on all web POSTs; webhooks exempt but **HMAC/shared-secret verified** first | `bootstrap/app.php`, webhook controllers |
| **Sensitive data exposure** | Cost/profit columns `$hidden`; data export filters third-party PII; secrets only in `.env` via `config()` | models, `UserDataExporter` |
| **Insecure deserialization / secret writes** | Maintenance loop `SecretGuard` blocks any diff touching `.env`/keys or writing secret-looking values | `Support\Maintenance\SecretGuard` |
| **Vulnerable dependencies** | `composer audit` gate in CI (fails on any new advisory; documented allow-list only) + Larastan static analysis | `bin/security-audit.php`, `.github/workflows/tests.yml` |
| **Rate/abuse of money actions** | Order actions 10/min; money moves are idempotent + atomic | Checkout/GetNumber, `WalletService` |
| **Audit / non-repudiation** | Immutable `audit_logs` for every admin/staff/lifecycle/maintenance action | `Support\Auditor` |

## Known accepted risk — Laravel 11 is past security EOL

Laravel 11's security-support window ended **2026-03-12**. `composer audit`
currently reports three `laravel/framework` advisories that are fixed only in
Laravel 12.60+ (signed-URL path confusion; CRLF in the default email rule). They
are listed in the `bin/security-audit.php` allow-list with interim mitigations.

**Action:** schedule the Laravel 12 upgrade — it is now the top security
priority. Once upgraded, remove those IDs from the allow-list so the audit gate
enforces a clean tree.
