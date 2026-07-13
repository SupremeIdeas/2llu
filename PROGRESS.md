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

### ✅ Module 3 — PricingEngine  (Sections 1, 13)  — passed acceptance 2026-07-12
`app/Services/Pricing/PricingEngine.php` is the single owner of all price math (bound as a singleton): `calculateRetail`, `calculateSmsRetail`, `getProfitSummary`, plus `recompute`/`RecomputePlanPricingJob` for global-markup reprices. Two upward-only guards — Airalo minimum-selling-price (Airalo plans only) and MarginGuard (cost + min profit floor, cannot be disabled). Every calculation writes a `pricing_engine_logs` row (guard_active + guard_delta). `Setting::getValue/setValue` (encrypted, cached) back the config; `PricingSettingsSeeder` seeds the Section 13.3 defaults (markups, floors, alerts).
**Acceptance — all green:**
- retail never < cost + min profit — property test across costs 0.01→999.99 × markups 0→200; manual overrides and near-zero markups all floored by MarginGuard.
- Airalo guard auto-corrects up — raises sub-minimum Airalo retail to `airalo_min_price`; ignored for non-Airalo providers.
- every calc logged — `pricing_engine_logs` row per call verified. Locked by `tests/Feature/PricingEngineTest.php` (12 tests / 104 assertions). Full suite 24/24.

### ✅ Module 4 — WalletService  (Sections 1, 14)  — passed acceptance 2026-07-12
`app/Services/Wallet/WalletService.php` (singleton) is the single owner of wallet balance changes: `debit`, `credit`, `refund`, `reward`, and `charge()` (charge-then-deliver with orphan-charge guard). Every change runs inside a DB transaction with `lockForUpdate` on the wallet row AND an atomic cache lock per wallet (Redis in prod), and writes a `wallet_transactions` row with `balance_before`/`balance_after` in the same transaction. Idempotent by `reference` (money actions never blind-retry). Dual-currency (NGN/USD). Auto-refund + `AlertAdminJob` (writes `error_logs`) when delivery fails. Exceptions: `InsufficientBalanceException`, `OrphanChargeRefundedException`.
**Acceptance — all green:**
- concurrent-debit proves no double-spend — 20 real concurrent OS processes debiting a 100 balance → exactly 10 succeed, 10 rejected, final balance 0.00, 10 debit rows (never negative). Plus a deterministic contention test in the suite.
- failed downstream save auto-refunds — `charge()` debits, runs delivery, and on any throw auto-refunds (net zero), alerts, and rethrows `OrphanChargeRefundedException`. Locked by `tests/Feature/WalletServiceTest.php` (9 tests). Full suite 33/33.

### ✅ Module 5 — eSIM Providers + ProviderRouter  (Sections 5, 6)  — passed acceptance 2026-07-12
`EsimProviderInterface` (6 methods) with three implementations bound as `esim.esimgo`/`esim.airalo`/`esim.quibity`: `EsimGoService` (v2.5, X-API-Key + x-sandbox), `AiraloService` (OAuth2 client-credentials via Laravel Http, token cached), `QuibityService` (Bearer + x-sandbox). `ProviderRouter` does profit-aware failover eSIM Go → Airalo → Quibity via `findEquivalentPlan` (cheapest cost that covers country+data+validity), skips any margin-eating fallback, logs `order_logs`, and on total failure refunds the wallet + `AlertAdminJob` + throws `EsimProviderException`. `CatalogueSyncService` maps each provider's catalogue into `esim_plans` (Airalo `net_price`→cost, `minimum_selling_price`→`airalo_min_price`; never Airalo's own `price`) and recomputes retail via the PricingEngine; `SyncEsimCatalogueJob` (queued) + `esim:sync` command drive it.
**Acceptance — all green:**
- fake-HTTP failover — eSIM Go fail → Airalo fail → Quibity success returns a Quibity `EsimOrderResult` with profit logged.
- margin-eating fallback skipped + refunds — a provider whose cost leaves < min profit is never called; wallet refunded, `AlertAdminJob` fired, `EsimProviderException` thrown. Locked by `ProviderRouterTest`, `EsimGoServiceTest`, `CatalogueSyncTest` (10 tests). Full suite 43/43.

### ✅ Module 6 — Number Layer + Router  (Sections 7–12)  — passed acceptance 2026-07-12
Capability routing (NOT blind failover): `SmsProviderInterface` (Getatext/5sim/SMS-Activate) + `NumberProviderInterface` (Twilio/Telnyx), bound as `number.$provider`. `SmsNumberRouter::laneFor(country,type)` implements the exact lane map and falls back ONLY within a lane. `GetatextService` (US, `Auth:` header, error→exception mapping) and `FiveSimService` (global, Bearer JWT, rating discipline) are full HTTP clients; SMS-Activate/Twilio/Telnyx are interface-complete skeletons (report unavailable until endpoints+keys wired at go-live — not invented). `PollSmsOtpJob` polls every 5s to a 15-min window: on a code → store + `finish()` + broadcast `OtpReceived` + complete; on timeout → `cancel()` + refund. Getatext webhook (`POST /webhooks/getatext`, CSRF-exempt, optional shared-secret, idempotent, logged to `webhook_logs`). Margin protection: live cost capped against the already-charged retail; lane-exhaustion refunds + `AlertAdminJob`.
**Acceptance — all green:**
- rent→code works — `buyOtp` → `PollSmsOtpJob` received path stores code, calls 5sim `/finish`, broadcasts `OtpReceived`.
- non-US OTP routes to 5sim (Getatext skipped); out-of-stock falls back in-lane; margin-eating provider skipped.
- timeout auto-cancels + refunds; webhook verified + idempotent. Locked by `SmsNumberRouterTest`, `FiveSimServiceTest`, `GetatextServiceTest`, `PollSmsOtpJobTest`, `GetatextWebhookTest` (21 tests). Full suite 64/64.

### ✅ Module 7 — Payments & Wallet Top-up  (Sections 14, 19)  — passed acceptance 2026-07-12
`PaymentGatewayInterface` (initialize/verifySignature/parseWebhook) with three gateways bound as `pay.$gateway`: `PaystackGateway` (HMAC-SHA512 of raw body), `FlutterwaveGateway` (`verif-hash` shared secret), `StripeGateway` (`t=..,v1=..` HMAC-SHA256 with timestamp tolerance) — all constant-time (`hash_equals`). `PaymentWebhookController` (`POST /webhooks/payments/{gateway}`) verifies the signature BEFORE touching the payload, logs to `webhook_logs`, returns 200 after verify, and dispatches `CreditWalletJob` on success. `CreditWalletJob` (`ShouldBeUnique`) credits via `WalletService` with `reference=topup:{gateway}:{ref}` so the money moves exactly once. Top-ups are metadata-driven (user_id in the verified provider metadata) — no payments table needed.
**Acceptance — all green:**
- a top-up credits the wallet exactly once even if the webhook is delivered twice — Paystack double-delivery yields one credit / correct balance.
- HMAC-verified webhooks + idempotency on provider_order_ref; bad/stale signatures rejected 401 with nothing credited. Locked by `tests/Feature/PaymentWebhookTest.php` (6 tests). Full suite 70/70.

### ✅ Module 8 — Icon System  (Section 16)  — passed acceptance 2026-07-12
Single inline SVG sprite (`partials/icon-sprite.blade.php`, 31 `<symbol>`s, `fill:none`+`stroke:currentColor` so icons inherit text colour and dark/light) included once in the base layout. `<x-icon name="" class="">` component renders the sprite `<use>` or, when the admin has mapped one, a custom image. `App\Support\IconOverrides` parses the `ui.icon_overrides` setting (`name = url` lines, slug keys, URL-validated), cached 1h, auto-busted on setting save, and degrades to the built-in sprite if settings are unavailable. `icons:cache` warms the cache and fails the deploy if any `<x-icon name>` lacks a sprite symbol/override. Theme-toggle now uses `<x-icon>`.
**Acceptance — all green:**
- zero emoji in views (regex scan over all blade files); custom-icon URL overrides the sprite (`<img>` replaces `<use>`).
- missing-icon check: `icons:cache` succeeds on the real views, fails on a fabricated missing icon. Locked by `IconSystemTest` + `IconsCacheCommandTest` (8 tests). Full suite 78/78.

### ✅ Module 9 — Customer UI  (Sections 4, 12, 14, 16)  — passed acceptance 2026-07-12
Livewire 3 (v3.8.2, pinned — composer first pulled v4). Full-page components on a `components.layouts.customer` chrome (brand nav + theme toggle): `Catalogue`, `Checkout`, `Wallet`, `GetNumber`, `Dashboard` (My Connectivity), `Referrals`, plus public legal/FAQ pages and dark-mode Fortify login/register views. `CurrencyService` (deferred from M3) + an `EsimPlan::display_price` accessor (USD + NGN) are the only price surface — cost never rendered. Checkout debits then fulfils via `ProviderRouter` with the orphan-charge guard; wallet top-up starts a gateway; get-a-number quotes→debits→routes→polls for the code (`wire:poll`).
**Acceptance — all green:**
- no cost field in any payload — display accessor + `$hidden` cost columns; catalogue/checkout render retail only (asserted cost value never appears).
- every element has dark: variants; every action has a loading state — enforced by a view scan test (`wire:loading` on all action views).
- Locked by `tests/Feature/CustomerUiTest.php` (8 tests) + live boot check (login/register/faq 200, `/dashboard`→login). Full suite 86/86.

### ✅ Module 10 — Admin Panel  (Sections 13, 15, 17)  — passed acceptance 2026-07-12
Admin area at `/adminmaster` behind an `EnsureAdmin` middleware (plain 404 for non-admins). `Admin\Pricing` (global markup + profit floor, per-plan override/fixed price/active/featured, LIVE profit summary via `getProfitSummary(log:false)`; saving the global markup dispatches `RecomputePlanPricingJob` and audit-logs). `Admin\ApiGuideModal` — one modal engine; every `<x-admin-help-icon provider field>` dispatches `open-api-guide` (content from `Support\ApiGuide`, Sections 15.3–15.9). `Admin\ErrorLogViewer` — per-day + severity filter with CSV/JSON export. `Admin\Dashboard` — provider wallet health (from `providers:health-check`), Active/Coming-Soon per product (`Support\ProviderStatus`, S17.4), and a 30-day profit snapshot. `providers:health-check` command caches balances and fires low-balance alerts (S17.2).
**Acceptance — all green:**
- admin sets markup + sees profit live — global save persists+reprices+audits; per-plan preview updates as you type without logging.
- every key field has a working help modal — `ApiGuideModal` opens with the right content per provider/field.
- error log exports CSV + JSON — both download for the selected day. Non-admins get 404. Locked by `tests/Feature/AdminPanelTest.php` (7 tests). Full suite 93/93.

### ✅ Module 11 — Installer & Deploy  (Section 22)  — passed acceptance 2026-07-12
Web installer at `/install` (`InstallController` + `Support\Installer`): requirements → database → application → provider keys → finalize. `RedirectIfNotInstalled` (web group) sends a fresh upload to `/install`; `EnsureNotInstalled` closes the wizard once the `storage/installed` lock exists. Finalize writes `.env`, generates `APP_KEY`, migrates+seeds, creates the super_admin, writes the lock, and (in prod) caches config/routes/views/icons — then redirects to `/login`. Blank provider keys are skipped so the product shows "Coming Soon" (`Support\ProviderStatus`). Scheduler wired in `routes/console.php` (`providers:health-check` /15min, `esim:sync` daily). CI/CD `deploy.yml` (build→test→SSH deploy, secret-gated) + `DEPLOYMENT.md` (cPanel + VPS).
**Acceptance — all green:**
- clean server → admin login via the browser installer — `/` redirects to `/install`, the finalize step creates the super_admin + lock and redirects to `/login` (verified live + test).
- Coming-Soon shows for blank keys — installer skips empty keys; `ProviderStatus` reports Active/Coming Soon by real config. Locked by `tests/Feature/InstallerTest.php` (5 tests). Full suite 98/98.

### ✅ Module 12 — Core Hardening & Tests  (Sections 1, 19)  — passed acceptance 2026-07-12
Rate limits (Section 19.2): `api` limiter 300/min auth · 60/min public (on `routes/api.php`); order actions 10/min enforced inside Checkout/GetNumber. `SecurityHeaders` middleware (nosniff, SAMEORIGIN, referrer-policy, permissions-policy) on every web response. Durable error capture: `ErrorLogger` writes server errors to `error_logs` via the `withExceptions` report hook (skips HTTP/validation/auth noise) — feeds the admin ErrorLog export. Sentry installed (DSN-gated, disabled without `SENTRY_LARAVEL_DSN`). Audit trail: `Support\Auditor` records who/what/where; admin pricing mutations audit-logged.
**Acceptance — all green:**
- suite passes + money-safety tests green — full run 105/105; a model-sweep test proves NO model leaks cost/profit; pricing/wallet/provider-fallback/webhook-signature suites all green.
- rate limits + Sentry + audit logging — order actions blocked at 10/min; exceptions land in `error_logs`; every admin action goes through `Auditor`. Locked by `tests/Feature/HardeningTest.php` (7 tests). **Core platform (Modules 1–12) complete.**

### ✅ Module 13 — Opening Splash / Brand Screen  (Section 24)  — passed acceptance 2026-07-12
`Support\SplashSettings::current()` (cached, cache-busted on any `splash.*` setting save, degrades to disabled if settings unavailable). `<x-splash>` component: absolute overlay, theme-correct background painted on the FIRST frame via the existing pre-paint theme script (no flash), Alpine picks the light/dark logo, fades out after `duration_ms` (capped 4000), optional show-once-per-session. Included once in the base layout. `Admin\Splash` panel (`/adminmaster/appearance`) edits toggle/name/tagline/duration/logos (Wasabi-CDN URLs, light+dark) — saving busts the cache so it reflects with no redeploy, and audit-logs.
**Acceptance — all green:**
- splash shows in correct theme with no flash — overlay carries `dark:bg-navy` and paints under the pre-paint `.dark` class; renders only when enabled.
- admin swaps logos/text without redeploy — settings save → cache bust → `SplashSettings::current()` reflects immediately; invalid logo URLs rejected. Locked by `tests/Feature/SplashTest.php` (6 tests). Full suite 111/111.

---

## NEXT  (build strictly top to bottom)

### === CORE PLATFORM (Modules 1–12) ===

### === PLATFORM STANDARD & NICHE EDGE (Modules 13–21) ===

### >>> CURRENT: Module 14 — Secure Admin Route /adminmaster  (Section 25)
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

### 2026-07-12 — Module 3 (PricingEngine)
- **Single owner of price math.** `PricingEngine` (singleton) is the only place a retail price is computed. Nothing else does price arithmetic — enforce this in review for every later module.
- **Guard order & logging:** formula → manual override (if set) → Airalo min guard → MarginGuard. `pricing_engine_logs.computed_retail` = pre-guard candidate, `final_retail` = post-guard, `guard_delta` = final − computed (≥ 0), `guard_active` = the last guard that fired (`none`/`airalo_min`/`margin_guard`).
- **Manual price still guarded.** A manual fixed price bypasses the markup formula but MarginGuard still floors it (blueprint 13.3, priority 1). Note the DB generated column `final_retail_usd = COALESCE(manual, computed)` uses the RAW manual value, so the admin UI (M10) must block sub-floor manual entries; the engine's `calculateRetail` returns the guarded value for quoting.
- **`recompute(plan)`** stores the pure engine price (manual override temporarily nulled) into `computed_retail_usd`; `final_retail_usd` then follows via COALESCE. `RecomputePlanPricingJob` (queued, Horizon) reprices all plans on a global-markup change — dispatch it from the admin pricing panel in M10.
- **Settings:** `Setting::getValue/setValue` cache per-key for 1h and wrap values as `['value' => …]` so scalars survive the `encrypted:array` cast; cache is busted on save/delete. `PricingSettingsSeeder` is idempotent (won't clobber admin edits) and runs from `DatabaseSeeder`.
- **Deferred:** `CurrencyService` (NGN display, Section 13.4) needs `AiraloService::getExchangeRates()` for the auto rate — build it in Module 5/9 when AiraloService exists; until then all pricing is USD.

### 2026-07-12 — Module 4 (WalletService)
- **Single owner of balances.** `WalletService` (singleton) is the only thing that mutates `user_wallets`/`wallet_transactions`. Every debit/credit: cache lock per wallet (`Cache::lock("wallet:{id}")`) → `DB::transaction` → `lockForUpdate` → read `balance_before` → apply → write `balance_after` + the transaction row, all atomic.
- **Concurrency guard is belt-and-suspenders.** `lockForUpdate` gives real row locking on MySQL (prod). On SQLite it's a no-op, so the **cache lock** (Redis in prod/local) provides cross-process serialization. CI uses the array cache store (per-process) which is fine because the CI suite is single-process; the deterministic contention test proves the invariant there. The 20-process cross-process proof was run locally against Redis (10 OK / 10 REJECT / balance 0).
- **Orphan-charge guard:** use `charge($user,$amt,$cur, fn($debit)=>deliver())` for any purchase — it debits, runs delivery, and auto-refunds + `AlertAdminJob` + throws `OrphanChargeRefundedException` if delivery fails. Never call `debit()` then deliver separately.
- **Idempotency:** pass `['reference'=>...]` (or `idempotency_key`); a repeat with the same reference returns the existing txn and moves no money. Refunds inside `charge()` use `refund:{debit_ref}` so they can't double-refund.
- **total_deposits vs total_spent:** only real `credit` (top-up) grows `total_deposits`; `refund`/`referral` don't. `debit` grows `total_spent`. These lifetime aggregates are currency-agnostic (single column each per schema) — treat as informational, not a per-currency ledger.
- **Known deprecation (cleanup later):** assigning float values to `decimal`-cast money attributes triggers a brick/math "passing floats" deprecation (brick/math will drop float support in 0.15). Non-breaking today and values store correctly; when upgrading brick/math, assign money as strings. Affects all models with decimal casts, so fix in a hardening pass, not piecemeal.

### 2026-07-12 — Module 5 (eSIM Providers + ProviderRouter)
- **One interface, one router.** All three providers implement `EsimProviderInterface` and are resolved by name (`app("esim.$provider")`). Controllers/jobs must NEVER call a provider service directly — always go through `ProviderRouter`.
- **Airalo transport deviation (documented).** Blueprint says use the official `airalo/airalo-php-sdk`. We used Laravel `Http` (OAuth2 client-credentials, token cached ~23h) so the provider is `Http::fake`-testable and carries no unpinned dependency. Swapping to the SDK later is isolated to `AiraloService`. The money-critical fields are unaffected: `net_price`→`cost_price_usd` (PRIVATE), `minimum_selling_price`→`airalo_min_price`; Airalo's own `price` is never stored as our retail.
- **Profit-aware failover.** `ProviderRouter::orderPlan` assumes the wallet was ALREADY debited `final_retail_usd` (checkout in M9 debits first — ideally wrap via `WalletService::charge()`), tries eSIM Go→Airalo→Quibity, and for each uses `findEquivalentPlan` (cheapest active plan of that provider covering the same countries ⊇, data ≥, validity ≥). A provider whose cost leaves < min profit is SKIPPED (not attempted). Total failure → wallet refund + `AlertAdminJob` + `EsimProviderException`.
- **Refund currency.** `orderPlan(planId, user, currency='USD')` — pass the actual debit currency from checkout so the refund matches what was charged (default USD per blueprint).
- **`AiraloService::revoke()` throws** (Airalo has no self-serve API revoke; refunds via partner support) and `getBalance()` returns 0.0 for Airalo/Quibity (no documented balance endpoint; only eSIM Go/Getatext/5sim are in the low-balance-alert set). Not exercised by the router.
- **Catalogue mapping quirks:** eSIM Go catalogue may be `{bundles:[...]}` or a bare list; Quibity `{data|plans}` or bare list; Airalo is nested `data[].operators[].packages[]` with countries at operator level. `CatalogueSyncService` handles all three defensively and always recomputes retail via `PricingEngine`.
- **Deferred:** eSIM Go webhooks (Section 5.2.3 — `usage.alert`/`bundle.*`/`esim.*`/`order.*`, HMAC-SHA256 verify) belong with the webhook-heavy Module 7; `CurrencyService` (NGN) can now use `AiraloService::getExchangeRates()` when built in M9.

### 2026-07-12 — Module 6 (Number Layer + Router)
- **Routing, not failover.** Numbers aren't interchangeable, so `SmsNumberRouter` matches country+type to the owning provider and falls back ONLY within the same lane (`laneFor`). A Nigeria OTP never touches a US provider; an OTP never becomes a permanent number.
- **Provider refs live in `sms_orders.getatext_id`.** The schema only has that one ref column, so it holds the provider order ref for ALL providers (5sim id, Getatext id, etc.), not just Getatext. Slightly misnamed; documented.
- **Margin cap fix (design).** `calculateSmsRetail` always enforces a floor, so a retail freshly derived from the same live cost can never trip the "cost eats margin" guard — it's dead under that path. The guard is meaningful only against the price the user was ALREADY charged, so the router compares live cost to `request.charged - min_profit` (falls back to a fresh quote when there's no pre-charge). Also passes `max_price` down to the provider buy call.
- **5sim rating discipline is automated.** `PollSmsOtpJob` calls `finish()` on every received code and `cancel()` on timeout — never leave an order hanging (a zero rating blocks ordering for 24h). Poll = 5s cadence, 15-min timeout window.
- **Skeletons are honest, not fake-working.** SMS-Activate, Twilio, Telnyx implement their interfaces and are container-bound, but `buy*` throws "not wired yet" (blueprint rule 1.1: don't invent endpoints). Wire their real endpoints + keys at go-live. Permanent lane (`twilio`→`telnyx`) is defined and unit-tested via `laneFor`, but `order()` throws "coming soon" for `permanent` — full permanent provisioning + monthly billing (Part 14.4) is a later module.
- **Getatext webhook has no HMAC** (Getatext sends from many IPs; do NOT IP-whitelist). "Verified" = optional `GETATEXT_WEBHOOK_TOKEN` shared secret (constant-time) + the payload matching a real pending order + idempotency. All webhooks logged to `webhook_logs` before processing. Route is CSRF-exempt via `bootstrap/app.php` (`webhooks/*`).
- **Refund currency caveat (same as M5):** `sms_orders` has no currency column; timeout/lane refunds default to USD (matching `charged_to_user`, a USD retail). M9 checkout must align the debit currency. `OtpReceived` broadcasts on `private-user.{id}` — the channel authorization callback is added with the broadcasting setup in M9.
- **Deferred to M9 (Customer UI):** the unified "My Connectivity" dashboard, the Get-a-Number flow, and live OTP streaming UI — Module 6 is the backend/routing layer only.

### 2026-07-12 — Module 7 (Payments & Wallet Top-up)
- **Signature verify BEFORE touching the payload** (rule 19.3). Each gateway's scheme: Paystack = `hash_hmac('sha512', rawBody, secretKey)` vs `x-paystack-signature`; Flutterwave = static `verif-hash` == `FLUTTERWAVE_SECRET_HASH`; Stripe = parse `t`,`v1` from `Stripe-Signature`, expected = `hash_hmac('sha256', "{t}.{rawBody}", webhookSecret)`, with a 300s timestamp tolerance. All use `hash_equals`. The controller reads `$request->getContent()` (raw) for HMAC — tests post raw JSON via `$this->call(...content)` so the signed bytes match.
- **Exactly-once is two-layered:** `CreditWalletJob` is `ShouldBeUnique` (keeps duplicate jobs off the queue) AND credits through `WalletService` with `reference=topup:{gateway}:{ref}` (the real guarantee — a repeat reference moves no money). Proven by delivering the same Paystack webhook twice → one credit.
- **No payments table (deviation, documented).** Section 18 has no payments/top-ups table, so top-ups are metadata-driven: `initialize()` puts `user_id` in the provider metadata + generates our `NAARA-{uuid}` reference; the verified webhook returns user_id + amount + reference, and the credit lands as a `wallet_transactions` row. After signature verification the webhook amount is authoritative (it's what was actually paid). If a first-class payments ledger is wanted later, add a table + migration.
- **Amount units:** Paystack/Stripe send minor units (kobo/cents) → divided by 100; Flutterwave sends major units. Currency taken from the (verified) webhook.
- **initialize()** for all three is real HTTP (Paystack `/transaction/initialize`, Flutterwave `/payments`, Stripe `/checkout/sessions` form-encoded) returning `{reference, redirect_url}`; documented endpoints, not invented. The checkout UI that calls it is M9.
- **Deferred:** eSIM Go provider webhooks (S5.2.3) still pending — fold into the webhook infrastructure now in place (same verify→log→queue pattern) during M9/M10 or a webhook pass.

### 2026-07-12 — Module 8 (Icon System)
- **One sprite, `<use>` everywhere.** `partials/icon-sprite.blade.php` holds 31 `<symbol id="i-name">` (Lucide-style paths, MIT). `<x-icon>` emits `<use href="#i-{slug}">`, inheriting `currentColor` + dark/light for free. No emoji, no icon font, no external CDN.
- **Override mechanism vs admin UI.** The white-label override (`ui.icon_overrides` setting → `IconOverrides` → `<x-icon>` renders `<img>`) is fully built + tested. The admin textarea panel that edits that setting lives in the Admin panel (Module 10) — the mechanism it drives is done.
- **Resilience:** `IconOverrides::all()` catches DB/settings errors and returns `[]` (built-in sprite) so a pre-install or DB hiccup never blanks the page — this also keeps the stock `ExampleTest` (no migrations) green when `/` renders icons.
- **`icons:cache`** parses `id="i-..."` from the sprite + `<x-icon name="literal">` from every blade (dynamic `:name` is skipped) and fails on any unresolved icon. Add it to the deploy pipeline in Module 11 alongside `config:cache`/`route:cache`/`view:cache`.
- **When adding a new icon:** add a `<symbol>` to the sprite; running `icons:cache` will catch any `<x-icon>` you referenced without one.

### 2026-07-12 — Module 9 (Customer UI)
- **Livewire 3, pinned.** Composer's default pulled Livewire 4; forced `^3.0` (v3.8.2) per CLAUDE.md. Livewire 3 bundles Alpine, so the manual Alpine import was removed from `app.js` (double-Alpine breaks it) and `@livewireStyles`/`@livewireScripts` added to the base layout. Full-page components default to the `components.layouts.customer` layout (which wraps `components.layouts.app`).
- **One price surface.** `EsimPlan::display_price` (Attribute, not appended) → `CurrencyService::displayPrice` returns USD + NGN. Cost is never passed to CurrencyService and cost columns stay `$hidden`. NGN symbol rendered as the ASCII string "NGN " (not ₦) to stay clear of the emoji scan and encoding issues.
- **Checkout money flow (important):** debit → `ProviderRouter::orderPlan` → persist `esim_order`. Do NOT wrap `orderPlan` in `WalletService::charge()` — `orderPlan` already self-refunds on total provider failure, so `charge()` would double-refund. The orphan guard here covers only the "order succeeded but persisting failed" case (manual refund + alert). Number checkout uses `SmsNumberRouter::quote()` (new) to debit before ordering; the router self-refunds if the lane exhausts (charged set).
- **Live OTP via `wire:poll`, not Echo.** Broadcasting/Soketi + Echo client is deferred; `GetNumber` polls the order every 3s to show the code once `PollSmsOtpJob` sets it. `OtpReceived` still fires (logs under `BROADCAST_CONNECTION=log`). Wire the `private-user.{id}` channel + Echo when Soketi is set up.
- **CurrencyService rate:** auto (Airalo `getExchangeRates`) with try/catch → manual/1500 fallback, cached 1h. In tests pin `pricing.ngn_rate_source=manual` to avoid a live call.
- **Deferred (documented, for later modules):** rentals inbox view + permanent-number UI + eSIM QR/usage widgets + device-compat check before purchase (S12.3/S32); the **referral profit-share ENGINE** (claim-before-pay reward on first purchase, S14.3) — only the referral display is built, the reward listener is not; i18n + multi-currency beyond USD/NGN (S32). Fortify email-verification gate is NOT applied to customer routes yet (only `auth`) — add `verified` in the hardening module.

### 2026-07-12 — Module 10 (Admin Panel)
- **Admin at `/adminmaster` now**, gated by `EnsureAdmin` (auth + `hasAnyRole(['super_admin','admin'])`) which throws a plain 404 for everyone else. Module 14 makes the path env-driven and adds 2FA/IP-allow-list; the 404 behavior is already in place.
- **Live profit without log spam.** `PricingEngine::calculateRetail`/`getProfitSummary` gained a `bool $log = true` param; the admin live preview calls `getProfitSummary(log:false)` so typing in the markup field doesn't write a `pricing_engine_logs` row per keystroke. Real saves still log. Note the preview recomputes retail from the markup FORMULA (cost × markup + guards), which can differ from a plan's stored `computed_retail_usd` if that was seeded directly.
- **One modal engine** (`ApiGuideModal`) placed once in the admin layout; `<x-admin-help-icon>` just dispatches `open-api-guide`. Content lives in `Support\ApiGuide` (verbatim from S15). ESC/backdrop close via Alpine.
- **Active/Coming-Soon is real config** (`Support\ProviderStatus`): a provider is Active only when all its required config keys are non-empty — drives the dashboard badges and, later, the customer "Coming Soon" gating (S17.4).
- **`providers:health-check`** pings only wallet-key providers (esimgo/getatext/5sim), caches `providers:health` for 30 min, and dispatches a `warning` `AlertAdminJob` when a balance is below its `pricing.low_balance_alert.*` threshold. Add it to the scheduler (every 15 min) in Module 11.
- **Audit logging** started here: pricing changes write `audit_logs` (who/what/ip). Module 12 extends audit coverage to every admin action.
- **Downloads:** Livewire `->assertFileDownloaded(...)` confirms the CSV/JSON exports; `streamDownload` returns the file from the action.

### 2026-07-12 — Module 11 (Installer & Deploy)
- **`RedirectIfNotInstalled` is global** (web group) and sends any non-installed request to `/install`, EXCEPT `install/*`, `webhooks/*`, and `up`. That exemption matters: provider/payment webhooks must keep working before/independent of install. Verified live (webhook returns 422, not a 302).
- **Tests are "installed" by default.** `tests/TestCase::setUp` calls `Installer::markInstalled()` so the middleware passes through for all feature tests. `InstallerTest` opts out (unlock in setUp, relock in tearDown) and points `Installer::$envPath` at a throwaway file so it never clobbers the real `.env`.
- **Finalize under tests** skips the live DB reconfigure + `config:cache` (guarded by `app()->runningUnitTests()`) — it migrates on the current sqlite connection, seeds roles+pricing, creates the super_admin, and writes the lock. In production it repoints the `mysql` connection from the wizard's creds, `DB::purge`es, migrates, then caches everything.
- **`.env` writing** merges keys into the existing file (or `.env.example` if absent), quoting values with spaces/#. Provider fields arrive as `key_ESIMGO_API_KEY` and blanks are skipped → Coming Soon.
- **To re-run the installer:** delete `storage/installed`.
- **Scheduler:** `routes/console.php` uses the `Schedule` facade (Laravel 11 style). One server cron entry (`schedule:run`) drives `providers:health-check` (/15min) and `esim:sync` (daily) — documented in `DEPLOYMENT.md`.
- **CD `deploy.yml`** is secret-gated (`DEPLOY_SSH_KEY` etc.) so it stays green without secrets; the existing `tests.yml` remains the PR gate.

### 2026-07-12 — Module 12 (Core Hardening & Tests)  ★ CORE PLATFORM COMPLETE
- **Rate limits:** named limiters `api` (300 auth / 60 guest) and `orders` (10/min) in `AppServiceProvider`. `routes/api.php` uses `throttle:api`. Livewire order actions can't be route-throttled per-action, so Checkout/GetNumber call `RateLimiter::tooManyAttempts('orders:{userId}', 10)` + `hit(...,60)` directly.
- **Error capture:** `withExceptions(report: fn)` → `ErrorLogger::capture` writes to `error_logs` (guarded try/catch so logging can't mask the original error; skips HttpException/Validation/Auth). This is the durable feed for the admin ErrorLog CSV/JSON export.
- **Sentry** installed but inert without `SENTRY_LARAVEL_DSN` — no network calls in dev/CI. It auto-captures exceptions alongside the `error_logs` write when a DSN is set.
- **Security headers** via `SecurityHeaders` middleware (web group). Deliberately NOT a strict CSP yet — a full CSP that doesn't break Livewire/Vite inline is the Module 19 security-matrix job. Only nosniff/frame/referrer/permissions here.
- **Audit:** `Support\Auditor::log()` is the one entry point; admin pricing mutations use it. Extend every future admin mutation to call `Auditor::log()`; Module 16 (staff scopes) and any admin write must audit.
- **Money-safety sweep** (`HardeningTest`) instantiates every cost-bearing model and asserts `toArray()` never contains cost/profit — a regression guard for the "never expose cost" rule as new fields are added.
- **Modules 1–12 done.** Remaining 13–21 are the Platform Standard & Niche Edge (splash, /adminmaster hardening, GDPR lifecycle, staff scopes, DB backup, Claude maintenance loop, security matrix, UI kit, niche edge).

### 2026-07-12 — Module 13 (Opening Splash / Brand Screen)
- **No-flash = the pre-paint script does the work.** The overlay just uses `bg-[#F8F9FA] dark:bg-navy`; because the theme-boot script in `<head>` sets `.dark` before first paint (already there since M1), the correct background paints on frame 1. Logos are swapped by Alpine in `init()` (reads `html.dark`) — brief until Alpine loads, but the background never flashes.
- **Config-driven, cache-busted.** `SplashSettings` mirrors the `IconOverrides` pattern: cached, flushed via the `Setting::saved` hook on any `splash.*` key, and try/catch-guarded so a pre-install/no-DB render (e.g. the stock `ExampleTest` hitting `/`) shows no splash instead of erroring.
- **Logos are URLs** (light+dark, Wasabi/CDN). A direct file-upload-to-Wasabi widget can be added later; pasting the Wasabi signed/CDN URL is the current path (valid + testable without live Wasabi keys).
- **Included in the base layout** so it appears on every app open (gated by `splash.enabled`, default off — so existing pages/tests are unaffected).

### 2026-07-13 — Enhancements before Module 14 (installer redesign, storage fallback, logo uploads, default admin)
Requested by the owner between M13 and M14. All shipped in one commit; suite 120/120.
- **Storage fallback (`Support\MediaStorage`).** `wasabiConfigured()` is true only when key+secret+bucket are ALL filled; `disk()` returns `wasabi` then, else the server `public` disk. So a fresh cPanel/VPS install works before any Wasabi keys are added — uploads land in `public/storage` (needs `storage:link`, which the installer runs). `storePublic()` names files by UUID and returns the public URL. **Researched size limits:** 2 MB raster / 512 KB SVG, accepts PNG/JPEG/WebP/GIF/SVG (`uploadRules()` = `mimes:png,jpg,jpeg,webp,gif,svg|max:2048`). `acceptAttribute()` feeds the file input's `accept`.
- **SVG sanitize (XSS).** Every SVG is cleaned before storing: strips `<script>`, `<foreignObject>`, `on*=` handlers and `javascript:` hrefs. Locked by `MediaStorageTest`.
- **Splash logo uploads.** `Admin\Splash` now has 4 `*_file` upload holders + a Livewire `updated()` hook that validates and stores via MediaStorage, wiring the resulting URL into the matching field in real time (still overridable by pasting a URL). The blade shows a preview thumbnail per logo on a light/dark swatch. Light/dark logos remain **CSS-switched** (`block dark:hidden` / `hidden dark:block`) so the wrong-theme logo never bleeds; a missing-mode logo falls back to the wordmark.
- **Installer redesign to match the owner's MagicAI reference.** 4 steps with a chevron indicator: **Welcome** ("Let's start") → **Server Requirements** (checklist) → **Setup** (Environment/Database Alpine tabs) → **Done**. Routes are now `GET /install` (welcome), `GET /install/requirements`, `GET /install/setup`, `POST /install/setup` (name `install.run`). App URL is validated to reject a trailing slash. Deleted the old `database/application/providers` step views. Browser-verified all 4 screens (Playwright) and sent shots to the owner.
- **Default admin seeder.** `DefaultAdminSeeder` (in `DatabaseSeeder`, idempotent) creates a `super_admin` — email `supremeideasz@gmail.com`, password `22504108303@AdminMaster` (owner-specified for fast first login). The **Done** screen surfaces these creds with a prominent "change the password after first login" warning. `EMAIL`/`PASSWORD` consts are referenced by tests.
- **Gotcha (screenshots):** in a bare `artisan serve` with no built Vite assets, Alpine doesn't load, so the splash overlay's fade timer never fires and it covers the page forever. Disable `splash.enabled` in the dev DB before capturing installer screenshots.
