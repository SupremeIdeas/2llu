# Platform State — notable fixes & their root causes

A running log of platform-level fixes that are worth *understanding*, not just
patching — so the next person (or the next Claude session) knows why a file
exists and must not be removed again.

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
