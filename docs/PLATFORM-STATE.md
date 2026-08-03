# Platform State — notable fixes & their root causes

A running log of platform-level fixes that are worth *understanding*, not just
patching — so the next person (or the next Claude session) knows why a file
exists and must not be removed again.

---

## Media pipeline build (BUILD-11) — 2026-08-03

### §3 Server-side WebP compression — Done
- **`CompressImageJob` (queued, never inline).** `MediaStorage::storePublic()`
  stores the original and returns its URL immediately, then dispatches the job —
  zero added latency on the upload response. The job converts to WebP with raw
  **GD** (`imagewebp`), no new Composer dependency: GD-with-WebP is universal on
  cPanel, and shelling out to a binary (which the spec warned against) is avoided.
- **~80KB target, quality floor 40.** Steps WebP quality 82 → 40; stops the
  moment it lands at/under 80KB, and accepts a slightly larger file at the floor
  rather than compressing a complex image into mush. Resizes to the upload
  context's real display cap FIRST (avatars/logos 512px, brand/splash 1024px,
  heroes/banners/covers 1600px) — no 4000px avatars.
- **Overwrites in place → the saved URL stays valid.** WebP bytes replace the
  same path, so none of the ~30 `storePublic` callers that persist the URL need
  to change. Cloud disks get an explicit `image/webp` content-type; on the local
  disk `<img>` decodes by bytes, so the `.png`/`.jpg` extension is harmless. Only
  overwrites when the WebP is actually smaller — a rare image WebP can't beat
  keeps its original.
- **Failure-safe (§3.4).** No GD/WebP, an unreadable file, or a decode error →
  the job logs and returns, leaving the original upload exactly as it was. Proven
  by a test that feeds it non-image bytes and asserts they're untouched.
- **KYC/quality-sensitive contexts excluded** (`documents`/`identity`/`kyc`/
  `exports`) and **GIFs skipped** (GD would flatten animation). KYC docs don't
  currently flow through `storePublic` at all — the exclusion is belt-and-braces.
- Applies uniformly to every existing image upload surface (heroes, banners,
  avatars, gift-card logos, blog covers, chat/MMS attachments, …) because the
  hook lives in `storePublic`, not in each caller.

### §4 Cloudflare R2 as a third storage option — Done
- **R2 disk added** (`config/filesystems.php`) — same `s3` driver, `region: auto`,
  endpoint `https://<ACCOUNT_ID>.r2.cloudflarestorage.com`. The bucket's S3
  endpoint needs auth, so a separate public URL (`R2_PUBLIC_URL` → disk `url`)
  is what SERVES files; R2 can hold PRIVATE files (signed URLs) with no public
  URL at all.
- **Runtime disk resolution** (`MediaStorage::resolveDisk()`) — one code path on
  cPanel and VPS, nothing environment-specific. Priority is **R2 → Wasabi →
  server** ("auto"), or an explicit admin choice via the `media.primary_disk`
  Setting. A public context requires R2's public URL before R2 can win; a chosen
  store that isn't actually configured safely falls through to auto, so uploads
  can never break. `disk()` (public) and `privateDisk()` both route through it.
- **Admin, no file editing** — R2 credentials are pasted in Admin → API keys →
  *Media storage* (encrypted at rest, overlaid onto `filesystems.disks.r2.*` at
  boot via `ProviderKeys::applyToConfig()`), and a *Primary media store* chooser
  writes the `media.primary_disk` Setting. The panel shows what media actually
  resolves to right now ("Serving from: …").
- Tests: `MediaStorageTest` (R2 wins by default, admin can pin Wasabi, R2 with no
  public URL serves private-only) + `ProviderKeysTest` (pasted R2 creds configure
  the disk without echoing the secret; primary-store pin is audited).

---

## Payments build (BUILD-2) — 2026-08-02

### Done
- **Crypto signature verification, doc-verified + fixed.** Confirmed each
  provider's current scheme against live docs. Found + fixed a real money bug:
  **Cryptomus** was signing with `JSON_UNESCAPED_SLASHES`, but the canonical is
  slash-escaped — this broke payment creation (the `sign` we send includes
  `url_callback`/`url_return`) and webhook verification for any payload with a
  `/`. Fixed to the documented flags; verification now also accepts the
  unescaped variant defensively. **NOWPayments** verification made tolerant to
  the same slash-escaping nuance. **CoinPayments** HMACs the raw body — correct
  as-is. Regression test: a slash-containing Cryptomus webhook now credits.
- **Paystack credit proven end-to-end against the real `database` queue** —
  queued (not inline), drains to a single credit + ledger row, idempotent on a
  retried delivery.
- **Global sandbox/test-mode indicator** — `PaymentSandbox` detects test keys
  (Stripe/Paystack `sk_test_`, Flutterwave `FLWSECK_TEST-`, PayPal/NOWPayments
  sandbox host); admin-dashboard banner + honest per-gateway checkout tag; never
  falsely flags a gateway it can't tell.
- **`docs/PAYMENT-GATEWAYS.md`** — per-gateway base URLs, signature methods,
  inline-vs-redirect state, refund/dispute status, and the Paystack sandbox test
  steps. All nine gateways confirmed wired to the unified webhook controller.

### The reported "top-up didn't credit" (SEV-1) — root cause is operational
The credit path is proven correct in code. The live failure is therefore
configuration: **(a)** the Paystack **webhook URL must be registered** on the
Paystack dashboard (`/webhooks/payments/paystack`) — no registered webhook means
no credit; and **(b)** the **queue worker/cron must run** on the cPanel host. The
admin dashboard's env-guard banner already warns if the queue is misconfigured.
Frank: verify both on the live host.

### Refunds & disputes — built 2026-08-02 (BUILD-2 §7)
- **Admin-triggered refunds** (Admin → Payments → Refunds & Disputes):
  `RefundService` is provider-first, ledger-reversing, idempotent, and refuses to
  refund already-spent funds (no silent loss). `RefundableGateway` contract wired
  for **Paystack**; the other card gateways drop in by implementing it (Stripe,
  Flutterwave, PayPal endpoints noted in docs/PAYMENT-GATEWAYS.md). Crypto →
  manual task + alert.
- **Dispute/chargeback handling**: `DisputeService` freezes the disputed amount
  from the wallet on open (reserve earmark → unspendable + unwithdrawable),
  releases on win, releases + debits on loss; idempotent. Wired for **Paystack**
  via the shared signed webhook; `DisputeAwareGateway::parseDispute` is the seam
  for Stripe/Flutterwave/PayPal (payloads differ — verify each before wiring).
  The USD reserve is the only wallet earmark, so non-USD disputes are recorded +
  alerted for manual handling.

### Card-gateway refunds/disputes extended — 2026-08-02
- **Refunds** now wired for **Stripe** (`/v1/refunds` on the payment_intent),
  **Flutterwave** (`/v3/transactions/{id}/refund`), and **PayPal**
  (`/v2/payments/captures/{id}/refund`) — alongside Paystack. Each needs the
  provider's own charge id, so it's captured at webhook time into a new
  `payment_charges` table and handed to the refund call.
- **Disputes** now wired for **Stripe** (`charge.dispute.created/closed`) and
  **PayPal** (`CUSTOMER.DISPUTE.CREATED/RESOLVED`), which cite the provider charge
  id — mapped back to our reference + user via `payment_charges`. Freeze/claw-back
  reuses the same money-safe `DisputeService`.

### Still flagged (the remaining payments follow-up — money-moving, do with care)
- **Flutterwave chargeback webhooks** — an on-request, variable-payload feature;
  the `DisputeAwareGateway` seam is ready but the payload must be verified against
  a real event before wiring it onto the fund-freezing path.
- **Per-gateway admin schema (§3) — DONE 2026-08-02.** Admin → Payments →
  Gateways: sandbox/live mode toggle (swaps the base URL for PayPal/NOWPayments;
  informational + key-prefix detection for the rest), read-only webhook + callback
  URLs with copy buttons, and a live test-connection ping per gateway. Keys stay
  on Provider Keys (not duplicated). Remaining nicety (not built): storing
  separate sandbox AND live key sets simultaneously so the toggle swaps keys too —
  today the operator pastes the matching key. `PaymentGatewayConfig::applyToConfig`
  swaps hosts at boot.
- **Inline/embedded checkout** where supported (Paystack Inline, Stripe Payment
  Element, Flutterwave modal). Everything is redirect/hosted today.
These are grouped so they get one careful, reviewed pass rather than being rushed
onto a live money path.

---

## Foundation build + provider-timeout hotfix — 2026-08-02

### Done
- **Provider HTTP timeouts (root cause of the "whole app froze" reports).** All
  13 provider integrations now set `connectTimeout(3)` + `timeout(8)` on their
  client builder (purchase confirmations `timeout(15)`); Twilio/Telnyx keep
  their longer voice/permanent ceilings but gained `connectTimeout(3)`. A hung
  provider now fails over within ~11s instead of pinning a worker for 30–60s+.
  Proven by a test that injects a `ConnectionException` and asserts lane failover.
- **Session-lock contention.** `SESSION_DRIVER=database` (documented WHY `file`
  is forbidden: its per-request lock lets one slow request freeze a user's whole
  session). Provider timeouts cap how long the lock can be held at all.
- **Wasabi-safe uploads sitewide.** Livewire's temp-upload disk is pinned at boot
  to `MediaStorage::disk()` (Wasabi-or-local), so uploads work with zero Wasabi
  keys and upgrade automatically once keys are set. `.env.example`
  `FILESYSTEM_DISK` default changed `wasabi → public`.
- **Queue.** `QUEUE_CONNECTION=database` by default; verified `queue:work
  --stop-when-empty` drains cleanly. A production `sync`/debug-on misconfig is
  now flagged (see EnvironmentGuard).
- **Brand error pages** 404/419/500/503 — one shared shell, zero technical leak.
- **Migration collision + installer drift** — fixed earlier the same day (see the
  next section); the duplicate-timestamp guard lives in
  `bin/check-install-integrity.php` (a CI gate).
- **Admin never locked out of login** — super_admin/admin get 200/min, everyone
  else 5/min, off the same role source of truth.
- **cPanel installer footguns documented** (username truncation; non-transactional
  DDL).

### Flagged but not yet built (BUILD-1 §3 admin-auth — remaining, needs a focused pass)
These are the heavier/riskier admin-hardening items from BUILD-1 §3. They are
deliberately **not** rushed in alongside the foundation work because several can
lock the owner out or break the production build if done carelessly, and they
deserve their own reviewed pass:
- **`SecurityLog` table + auto-temp-ban** on repeated failed logins/403s/429s
  (§3.7). Admin accounts must be ban-exempt — get this wrong and you lock out the
  owner, so it needs care + tests.
- **`anti-inspect.js`** devtools/context-menu blocking that **skips admins**, with
  `window.isAdmin` exposed to JS (§3.4).
- **`StripSecrets` middleware** for `_secret`-prefixed Livewire props + signed
  routes for sensitive admin actions (§3.6).
- **Vite production obfuscation** via `rollup-plugin-obfuscator` (§3.5) — build-
  time; verify it doesn't break the bundle before committing.
- **`X-Frame-Options`** is currently `SAMEORIGIN` (blocks cross-origin clickjacking
  already); BUILD-1 §3.2 requests `DENY`. Confirm nothing uses same-origin iframes
  before tightening.
- **SecurityHeaders / rate limiting already exist** (CSP, HSTS, nosniff, frame-
  options; `api`/`orders`/`admin` limiters) — extend, don't duplicate.

### Needs Frank's input / can't be done from here
- **Live `.env` audit (BUILD-1 §2.3).** I can't read the deployed server's `.env`
  from this environment. Frank: confirm the LIVE `.env` has `QUEUE_CONNECTION=`
  `database` (or `redis`) and `APP_DEBUG=false`. If the queue was on `sync`, that
  is a likely contributor to any past "payment didn't credit" symptom. The admin
  dashboard now shows a banner if either is misconfigured in production.
- **SupportChat voice-recording upload (BUILD-1 §4.5).** When that feature is
  built, re-verify its upload against the Wasabi-safe temp-disk fix above.

### Admin-configurable settings map (this batch)
- **Production misconfig banner** — automatic, no setting: shows on the admin
  dashboard (Overview) whenever `QUEUE_CONNECTION=sync` or `APP_DEBUG=true` in a
  production environment. Also logged at boot.

### Standing rule (do not regress)
- **Every provider integration MUST set an HTTP timeout from day one** —
  `connectTimeout(3)` + a bounded `timeout()` on its client builder, purchase
  calls no more than ~15s. No `Http::` call to an external provider may ever be
  unbounded. A timed-out provider must fail over (be caught), never bubble up and
  freeze the request.

---

## 2026-08-02 — Installer drift: four install-critical files re-synced with the known-working package

### What was wrong
A file-by-file diff between the GitHub repo and a known-working 28 MB installable
package (1,645 files compared) found four concrete, install-blocking differences.
Every one of them broke a **fresh** install while leaving the running dev/test
environment perfectly healthy — which is exactly why they went unnoticed.

### The four fixes (each its own commit)

1. **Migration timestamp collision.** `create_partners_table` and
   `create_partner_earnings_table` both sat at `2026_07_27_130955`.
   `partner_earnings` has a foreign key into `partners`; with equal timestamps
   the order falls back to alphabetical, which runs `partner_earnings` **first**
   → hard foreign-key failure on the first `php artisan migrate`. Fixed by
   re-timestamping the earnings migration to `2026_07_27_130956` (contents
   unchanged). The unrelated `2026_07_12_141334` pair (two-factor columns +
   personal access tokens) is order-independent with no cross-FK and is left as
   the one allowed collision.

2. **Missing DB-free install layout.** The four installer steps had been switched
   to `<x-layouts.app>` and the dedicated `<x-layouts.install>` layout was gone.
   `x-layouts.app` renders the splash screen, brand preloader, toast stack, PWA
   manifest link and tracking pixels — all backed by the `settings` table, which
   does not exist during the pre-migration install steps. (Every lookup is
   try/catch-guarded, so it degrades rather than 500-ing, but it fires failed
   queries and paints the wizard with post-install chrome.) Restored the minimal
   `resources/views/components/layouts/install.blade.php` and repointed the four
   views to it — they now match the known-working package byte-for-byte.

3. **Missing `config/view.php`.** Without it Laravel uses the framework default,
   whose compiled path is `realpath(storage_path('framework/views'))`.
   `realpath()` returns `false` when that directory doesn't exist yet — the state
   of a fresh clone/export — leaving Blade with no valid cache path and failing
   **every** render with "Please provide a valid cache path". Restored
   `config/view.php`, which uses `storage_path(...)` **without** `realpath()`, so
   the path resolves before the directory exists. `VIEW_COMPILED_PATH` stays
   env-overridable for locked-down cPanel hosts.

4. **Runtime directory placeholders — already covered on GitHub.** The reference
   package ships `.keep` files in `bootstrap/cache` and `storage/framework/*`.
   The GitHub repo already preserves every one of those directories with tracked
   **`.gitignore`** files (the standard Laravel convention), verified by a real
   fresh clone — so they are *not* missing and no `.keep` files were added
   (redundant). `public/storage` is intentionally **not** committed: it is the
   symlink created by `php artisan storage:link` at install, and materialising it
   as a real directory would break that symlink and stop uploaded media from
   being served. The reference's committed `public/storage/.gitignore` is a
   build-time artifact, not a source-repo file (the root `.gitignore` ignores
   `/public/storage` in both repos).

### How it was verified
- Fresh `git clone` of the patched repo: all six runtime dirs present,
  `public/storage` correctly absent, `config/view.php` + install layout present,
  partner migrations correctly ordered.
- Fresh `migrate` on a clean database: 92 migrations, 0 failures, `partners`
  before `partner_earnings`.
- `php artisan view:cache` compiles every Blade view (the install layout
  resolves), and the install `welcome`/`setup` views render through the DB-free
  layout with **no** splash, preloader, manifest or toast markup.

### Why it happened, and what stops it recurring
Install-critical files can drift out of the repo without breaking day-to-day
development, so nothing surfaces the loss until someone runs a true fresh install.
Two guards now close that gap:

- **`bin/check-install-integrity.php`** — a pure-filesystem check (wired into CI
  as the *Install integrity* job in `.github/workflows/tests.yml`) that fails if
  any of these files is missing, if an install view stops using
  `x-layouts.install`, or if a new migration timestamp collision appears.
- **`scripts/package-release.sh`** — a first-party, repeatable packager that
  builds a cPanel-ready ZIP from a clean checkout (integrity check → `composer
  install --no-dev` → `npm ci && npm run build` → ensure runtime dirs → zip), so
  a working installable package can always be produced from GitHub without a
  third-party tool. Documented in `docs/CPANEL-INSTALL.md`.
