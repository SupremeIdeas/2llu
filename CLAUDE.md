# CLAUDE.md — NaaraSim Project Brief

> Claude Code reads this file automatically at the start of every session.
> It is the permanent context for this project. Do not delete it.
> The full specification is `NaaraSim-Master-Build-Blueprint-v5.docx` in this folder (Sections 0–32).

---

## HOW WE BUILD (read this first)

- **You are the single developer.** We are NOT using Ruflo or any multi-agent orchestration. Ignore all Ruflo / swarm / agent / SPARC references in Section 23 of the blueprint. Use Section 23 ONLY for the module order and the acceptance checks.
- **Build ONE module at a time**, in the order listed in PROGRESS.md. Never attempt to "build the platform" in one go.
- **Never start a module** whose dependency module isn't finished and passing its acceptance check.
- **After every task, update PROGRESS.md** (move finished work to DONE, put the exact next step at the top of NEXT).
- **Test money paths on SANDBOX keys only.** Real provider/payment keys go in last, after the hardening module.

---

## What we are building

**NaaraSim** — a Pan-African travel-connectivity SaaS selling:
1. **eSIM data plans** for 190+ countries
2. **Virtual phone numbers** (permanent, voice + SMS) and **SMS verification numbers** (disposable OTP + rentals)
3. It uniquely sells BOTH data AND numbers in one app — most competitors are data-only. Lean into that.

Owner: Frank Charles Ebubedike, Supreme Ideas Agency, Onitsha, Nigeria.
Tagline: *"Stay Connected. No Borders. No Swaps."*
Brand: Deep Teal `#0A6E6E`, Warm Gold `#D4A017`, Midnight Navy `#0D1B2A`.

---

## Tech stack (do not substitute)

- **Laravel 11** (PHP 8.2+), TALL stack. (Laravel 11 security support ends 12 Mar 2026 — plan a Laravel 12 upgrade later.)
- **Livewire 3** + **Alpine.js 3** + **Tailwind 3.4** (`darkMode: 'class'`)
- **MySQL 8** (utf8mb4, strict) · **Redis** (queue/cache/session) · **Horizon**
- **Sanctum** + **Fortify** (2FA/TOTP) + **Spatie Permission** (roles)
- **Wasabi S3** for all file storage (never local disk)
- Deploy: BOTH VPS and shared cPanel, installable like a CodeCanyon product
- Scale target: 1M+ users

---

## Providers (exact roles)

**eSIM (interchangeable -> failover chain):**
- eSIM Go (PRIMARY) · Airalo (SECONDARY — honor minimum_selling_price) · Quibity/eSIM.sm (TERTIARY)

**Numbers (NOT interchangeable -> capability routing by country + type, never blind failover):**
- Getatext -> US (SMS/OTP + long rental)
- 5sim -> GLOBAL, 180+ countries incl. Nigeria/Ghana/Kenya/South Africa (activation + hosting; rating discipline required)
- SMS-Activate -> global backup · Telnyx -> permanent/voice backup
- Twilio -> permanent numbers + voice (PRIMARY for calls)
- **Lane rule:** match country+type to the owning provider; fall back only WITHIN the same lane. Never cross lanes.

---

## MONEY-SAFETY RULES (non-negotiable — Sections 1 & 13)

1. **Golden Rule:** Retail = Provider Cost + Margin. User ALWAYS pays retail; NaaraSim ALWAYS pays cost. Never reversed.
2. **Never expose cost.** cost_price_usd / net_price never appears in ANY user-facing response, template, or payload.
3. **All prices flow through PricingEngine.** No price literals anywhere. Number/SMS costs fetched live before quoting.
4. **MarginGuard mandatory.** Retail never at/below cost + min profit. Auto-correct upward + log. Airalo min-price guard too.
5. **Wallet debits/credits atomic.** DB transaction + lockForUpdate(); write wallet_transactions with balance_before/after.
6. **Never charge without delivering.** Orphan-charge guard: charged but downstream save failed -> auto-refund + release.
7. **Money actions never blind-retry.** Failed order/debit -> refund + alert. Idempotency keys on order jobs.
8. **Every external API call is a queued job** (Horizon, retry+backoff) — never synchronous in the request cycle.
9. **Verify every webhook** with HMAC + hash_equals() before touching the payload. Idempotency on provider refs.
10. **No secrets in code.** Keys in .env via config(). Rotate provider/wallet keys every 90 days.

## UI RULES (non-negotiable)

- **SVG icons only — no emoji anywhere.** Inline sprite (Section 16) + admin custom-icon override.
- **Dark mode on every element.** Every bg/text/border/shadow has a `dark:` variant.
- **Every action shows a loading state.** Money actions disable their button while in flight.

## PLATFORM STANDARD (Sections 24–32)

- **S24 Splash screen:** admin-configurable, product logo + "from Supreme Ideas", auto dark/light, no theme flash.
- **S25 Admin at `/adminmaster`:** env-driven, plain 404 for non-admins, zero links from user UI.
- **S26 Account lifecycle (GDPR):** self-deactivate; data export (filters third-party PII); deletion = super-admin approval only; erasure reaches backups.
- **S27 Staff + scoped roles:** granular permissions. Staff can do almost anything EXCEPT delete users.
- **S28 DB backup:** spatie/laravel-backup to Wasabi; on shared cPanel where mysqldump is blocked, FALL BACK to ifsnop/mysqldump-php (needs SELECT + SHOW VIEW).
- **S29 Claude maintenance loop:** reads error log, proposes diff, on approval commits to a branch + PR (CI-gated). Never straight to prod; secrets never touched; one-click rollback.
- **S30 Security matrix:** every OWASP mistake + attack class mapped to a defense.
- **S31 UI kit:** star rating, ONE modal engine, theme toggle, server-anchored countdown, search+debounce — themed + accessible.
- **S32 Niche edge:** manual LPA install fallback, device-compat check BEFORE purchase, clear refund policy, live chat + WhatsApp, NaaraCredits loyalty, data estimator, i18n + multi-currency.
