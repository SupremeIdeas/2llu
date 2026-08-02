# Platform State — notable fixes & their root causes

A running log of platform-level fixes that are worth *understanding*, not just
patching — so the next person (or the next Claude session) knows why a file
exists and must not be removed again.

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
