# PROGRESS.md — NaaraSim Build Log (our save point)

> Read this at the start of every session. It says exactly where we stopped.
> After every task: move finished work to DONE, keep the next step at the TOP of NEXT.
> Full detail per module: `NaaraSim-Master-Build-Blueprint-v5.docx` (Sections 0–32).
> We are building WITHOUT Ruflo — single developer, one module at a time.

---

## DONE

*(nothing yet — project not started)*

---

## NEXT  (build strictly top to bottom)

### === CORE PLATFORM (Modules 1–12) ===

### >>> CURRENT: Module 1 — Foundation  (Sections 1, 3)
Laravel 11 install; Sanctum + Fortify + Spatie roles; Redis for queue/cache/session; Horizon; Wasabi disk; Tailwind darkMode:'class'.
**Done when:** fresh install boots; dark toggle works on a blank layout; Horizon dashboard loads admin-only.

### Module 2 — Migrations & Models  (Section 18)
All tables incl. generated final_retail_usd + profit-tracking tables. $fillable on every model. Reversible migrations.
**Done when:** migrate + rollback run clean; final_retail_usd computes from COALESCE.

### Module 3 — PricingEngine  (Sections 1, 13)
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
