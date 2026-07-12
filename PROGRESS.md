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

---

## NEXT  (build strictly top to bottom)

### === CORE PLATFORM (Modules 1–12) ===

### >>> CURRENT: Module 8 — Icon System  (Section 16)
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
