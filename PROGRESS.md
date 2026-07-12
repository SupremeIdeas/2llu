# PROGRESS.md — NaaraSim Build Log (our save point)

> Read this at the start of every session. It says exactly where we stopped.
> After every task: move finished work to DONE, keep the next step at the TOP of NEXT.
> Full detail per module: `NaaraSim-Master-Build-Blueprint-v5.docx` (Sections 0–32).
> We are building WITHOUT Ruflo — single developer, one module at a time.

---

## DONE

### ✅ Module 1 — Foundation  (Sections 1, 3)  — passed acceptance 2026-07-12
Laravel 11 (11.54) installed; Sanctum (api guard + `routes/api.php`) + Fortify (2FA/TOTP + email verification) + Spatie Permission (super_admin/admin/staff/user seeded); Redis driving queue/cache/session (phpredis); Horizon installed with an admin-only `viewHorizon` gate; Wasabi S3 disk (private, default disk); Tailwind `darkMode:'class'` with the brand palette + no-flash pre-paint theme script + `<x-theme-toggle>` (inline SVG, no emoji).
**Acceptance — all green:**
- Fresh install boots — `/` returns 200, `/up` health 200, `php artisan test` 6/6 pass.
- Dark toggle works on a blank layout — browser-verified (Chromium/Playwright): flips `<html>.dark`, persists to `localStorage`, survives reload with no flash.
- Horizon loads admin-only — `viewHorizon` gate DENY for guest/user/staff, ALLOW for admin/super_admin (super_admin also bypasses via `Gate::before`). Locked in by `tests/Feature/FoundationTest.php`.

### ✅ Module 2 — Migrations & Models  (Section 18)  — passed acceptance 2026-07-12
15 migrations covering all Section 18.1 core tables (users additions, user_wallets, wallet_transactions, esim_plans, esim_orders, sms_orders, virtual_numbers, referrals) and all Section 18.2 profit/business tables (order_logs, pricing_engine_logs, provider_wallet_logs, settings, webhook_logs, error_logs, audit_logs). `esim_plans.final_retail_usd` is a STORED generated column = `COALESCE(manual_retail_usd, computed_retail_usd)`. Every model has a `$fillable` allowlist; private cost/profit columns (`cost_price_usd`, `airalo_min_price`, markup, `wholesale_cost`, `provider_cost`, `profit`, `monthly_cost`) are `$hidden` so they can never reach a user payload (money-safety rule 1.2). `settings.value` is `encrypted:array` at rest.
**Acceptance — all green:**
- migrate + rollback run clean — verified `migrate:fresh`, `migrate:rollback` (whole batch), and `migrate:refresh` (reset-all → migrate-all) with zero failures.
- final_retail_usd computes from COALESCE — override wins, else computed, else null; recomputes when a manual override is added. Locked in by `tests/Feature/SchemaAndModelsTest.php` (12 assertions incl. cost-hiding + settings encryption). Full suite 12/12.

---

## NEXT  (build strictly top to bottom)

### === CORE PLATFORM (Modules 1–12) ===

### >>> CURRENT: Module 3 — PricingEngine  (Sections 1, 13)
calculateRetail, calculateSmsRetail, getProfitSummary, MarginGuard, Airalo min guard, pricing_engine_logs. No price math anywhere else.
**Done when:** tests prove retail never < cost + min profit; Airalo guard auto-corrects up; every calc logged.

### Module 4 — WalletService  (Sections 1, 14)
Atomic debit/credit in DB transaction + lockForUpdate; wallet_transactions with before/after; orphan-charge guard.
**Done when:** concurrent-debit test proves no double-spend; a failed downstream save auto-refunds.

### Module 5 — eSIM Providers + ProviderRouter  (Sections 5, 6)
EsimProviderInterface; eSIM Go v2.5, Airalo (SDK), Quibity; profit-aware ProviderRouter; catalogue sync calling PricingEngine.
**Done when:** fake-HTTP tests show eSIM Go fail -> Airalo -> Quibity; a margin-eating fallback is skipped and refunds.

### Module 6 — Number Layer + Router  (Sections 7–12)
Capability router (laneFor by country+type); GetatextService (US), FiveSimService (global), SMS-Activate + Telnyx backups; webhook + poll OTP; unified "My Connectivity" dashboard.
**Done when:** rent->code works in sandbox; non-US OTP routes to 5sim (Getatext skipped); out-of-stock falls back in-lane; timeout auto-cancels + refunds; 5sim /finish called; webhook verified.

### Module 7 — Payments & Wallet Top-up  (Sections 14, 19)
Flutterwave/Paystack/Stripe; HMAC-verified webhooks -> queued CreditWalletJob; idempotency on provider_order_ref.
**Done when:** a top-up credits the wallet exactly once even if the webhook is delivered twice.

### Module 8 — Icon System  (Section 16)
Inline SVG sprite; <x-icon> with custom-icon override; admin custom-icon panel; icons:cache. No emoji anywhere.
**Done when:** grep of compiled views finds zero emoji; a custom-icon URL overrides the sprite.

### Module 9 — Customer UI  (Sections 4, 12, 14, 16)
Dashboard, catalogue, checkout, number flow, rentals, wallet, referrals, legal/FAQ — Livewire, dark-mode, SVG icons, prices via display accessor.
**Done when:** no cost field in any payload; every element has dark: variants; every action has a loading state.

### Module 10 — Admin Panel  (Sections 13, 15, 17)
Pricing panel (global + per-plan + live profit), API-guide tooltip modal, provider health + wallet widgets, error-log module with dated export.
**Done when:** admin sets markup + sees profit live; every key field has a working help modal; error log exports CSV + JSON.

### Module 11 — Installer & Deploy  (Section 22)
Web installer (requirements -> DB -> app -> keys -> finalize) with installed lock; cPanel + VPS docs; CI/CD.
**Done when:** a clean server goes from upload to admin login via the browser installer; Coming-Soon shows for blank keys.

### Module 12 — Core Hardening & Tests  (Sections 1, 19)
Test suite (pricing, wallet, provider fallback, webhook signature); rate limits; Sentry; audit logging.
**Done when:** suite passes; money-safety tests green; every admin action audit-logged.

### === PLATFORM STANDARD & NICHE EDGE (Modules 13–21) ===

### Module 13 — Opening Splash / Brand Screen  (Section 24)
Admin-configurable splash: product logo + "from Supreme Ideas", auto dark/light (pre-paint script, no flash), logos on Wasabi, on/off + duration.
**Done when:** splash shows in correct theme with no flash; admin swaps logos/text without redeploy.

### Module 14 — Secure Admin Route /adminmaster  (Section 25)
Env-driven admin path; non-admins get a plain 404 (not a login page); zero user-UI links; 2FA + throttle + optional IP allow-list.
**Done when:** /admin and the real path both 404 for non-admins; only admin/staff with 2FA get in.

### Module 15 — Account Lifecycle & Data Rights  (Section 26)
Self-deactivate; data export (queued, JSON/CSV, filters third-party PII, Wasabi signed URL); deletion request -> super-admin approval -> erase incl. backups; immutable audit log.
**Done when:** user can export all data + pause/resume; deletion needs super-admin approval; staff cannot delete.

### Module 16 — Staff Accounts & Scoped Roles  (Section 27)
Super admin creates staff, assigns scopes (kyc.review, tickets.manage, refunds.process, users.moderate, orders.assist, content.manage, providers.view); privilege-escalation guard; 2FA; audit trail.
**Done when:** staff act only within granted scopes; cannot delete a user; cannot grant scopes they don't hold.

### Module 17 — Database Backup, Export & Import  (Section 28)
spatie/laravel-backup to Wasabi (encrypted); DETECT mysqldump, FALL BACK to ifsnop/mysqldump-php on cPanel; super-admin restore (maintenance mode, snapshot-first); dataset export/import with dry-run + transaction.
**Done when:** backup runs on a host WITHOUT mysqldump; restore works from panel; import dry-runs before committing.

### Module 18 — Claude-Assisted Maintenance Loop  (Section 29)
Log analysis -> fix proposal (diff) -> human approve -> commit to branch + PR -> CI-gated merge -> rollback. Fine-grained GitHub token (this repo only, encrypted); secrets never touched; fully audited.
**Done when:** Claude proposes a diff from a real logged error; approval opens a CI-gated PR; rollback works.

### Module 19 — Security Hardening Matrix  (Section 30)
Implement every control: SSRF allow-list, validated() not all(), CSP + security headers, per-route rate limits, composer audit + Larastan in CI, session hardening.
**Done when:** each row in Section 30 has a control in code/config; CI fails on a vulnerable dependency.

### Module 20 — Reusable UI Kit  (Section 31)
Star rating, ONE modal engine (focus-trap, ESC, backdrop), theme toggle, server-anchored countdown, search+debounce. Themed + accessible.
**Done when:** every dialog uses the one modal; countdown can't be gamed by device clock; all keyboard-usable.

### Module 21 — Niche Edge Features  (Section 32)
Priority: manual LPA install fallback + device-compat check; refund policy + honest status; live chat + WhatsApp + Claude first-line; NaaraCredits loyalty + reviews; data estimator + coverage transparency; i18n + multi-currency; security add-on tier.
**Done when:** built per Section 32 priority; device-compat check runs BEFORE purchase; LPA string shown beside every QR.

---

## SESSION NOTES
*(Claude: record decisions made, half-finished work, and gotchas hit.)*

### 2026-07-12 — Module 1 (Foundation)
- **DB in this repo/sandbox = SQLite; production = MySQL 8.** MySQL isn't available in the build sandbox, so the local `.env` uses `DB_CONNECTION=sqlite` (with `database/database.sqlite`, git-ignored) purely to boot + run migrations/tests here. `.env.example` is the production source of truth and is set to MySQL 8 per the blueprint — switch the live `.env` to MySQL before deploy. No code depends on the driver.
- **Packages:** laravel/sanctum ^4.3, laravel/fortify ^1.37, spatie/laravel-permission ^6.25, laravel/horizon ^5.47, league/flysystem-aws-s3-v3 ^3.35. Alpine.js added via npm for the toggle (Livewire, which also bundles Alpine, lands in a later UI module).
- **Fortify** was wired by publishing config/migrations + registering `App\Providers\FortifyServiceProvider` in `bootstrap/providers.php` (did NOT run `fortify:install` to avoid duplicate 2FA migrations). 2FA (`confirm`) and email verification are both enabled in `config/fortify.php`.
- **Horizon admin-only gate** lives in `HorizonServiceProvider::gate()` → `viewHorizon` = `hasAnyRole(['super_admin','admin'])`. `Gate::before` in `AppServiceProvider` gives super_admin a blanket bypass. Note: Horizon skips the gate entirely in the `local` env — the gate only bites in non-local, which is where it matters.
- **Theme toggle** uses a pre-paint inline script in the layout head (reads `localStorage.theme` → falls back to `prefers-color-scheme`) so there is no flash of the wrong theme. Toggle button is `resources/views/components/theme-toggle.blade.php` (inline moon/sun SVG — the real `<x-icon>` sprite is Module 8). Layout is an anonymous component at `resources/views/components/layouts/app.blade.php` → `<x-layouts.app>`.
- **Wasabi** disk (`config/filesystems.php` → `wasabi`, S3 driver, `visibility: private`, `throw: true`) is the default `FILESYSTEM_DISK`. Keys blank in sandbox.
- **Gotcha:** the pre-installed Chromium is at `/opt/pw-browsers/chromium-1194/chrome-linux/chrome` (the `chromium/` symlink dir has no `chrome` binary). Use `playwright-core` with that `executablePath` + `--no-sandbox` for browser checks.
- **Not yet done (deferred to their modules):** MySQL live DB, the full SVG icon sprite (M8), Livewire UI (M9), the `/adminmaster` env-driven admin route (M14 — Horizon is gated but still on the default `/horizon` path for now).

### 2026-07-12 — Module 2 (Migrations & Models)
- **Generated column:** `esim_plans.final_retail_usd` uses Laravel `->storedAs('COALESCE(manual_retail_usd, computed_retail_usd)')`. Works on both SQLite (`... as (COALESCE(...)) stored`) and MySQL 8 (`GENERATED ALWAYS AS (...) STORED`). It is NOT in `$fillable` and is only populated on a fresh read after insert — call `->fresh()` if you need it immediately after `create()`.
- **Rollback gotcha (SQLite):** dropping the added `users` columns failed because `referral_code`'s unique index and `referred_by`'s index dangled during the table rebuild. Fixed by dropping `users_referral_code_unique` + `users_referred_by_index` in a first `Schema::table` closure, then the columns in a second. `migrate`, `rollback`, and `refresh` all verified clean.
- **Money-safety at the model layer:** private cost/profit columns are in `$hidden` on every model that has them (EsimPlan, EsimOrder, SmsOrder, VirtualNumber, OrderLog, PricingEngineLog) so `toArray()`/`toJson()` can never leak them. Test `SchemaAndModelsTest` asserts this.
- **Deviations from the literal schema, by design:** (1) `users.twofa_secret` omitted — Fortify's `two_factor_secret` already covers 2FA; (2) `users.role` kept as a mirror column but Spatie Permission remains the authorization source of truth; (3) `sms_orders.provider` is a string, not a `(getatext/twilio)` enum, because the number layer also routes OTPs to 5sim/SMS-Activate/Telnyx by lane; (4) `settings.value` stored as `longText` + `encrypted:array` cast (encrypted-at-rest can't be a native JSON column).
- **Money precision:** NGN balances `decimal(18,2)`, USD balances/prices `decimal(18,4)`, provider USD costs `decimal(12,4)`, percentages `decimal(6,3)`.
