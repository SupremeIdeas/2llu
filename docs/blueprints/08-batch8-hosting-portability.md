# 2LLU — Batch 8: Hosting Portability (Namecheap cPanel → Hostinger/Cloudways VPS)
### The plan you pasted is good. This batch fixes two things it gets slightly wrong and adds what's missing. Give this whole file to Claude Code as one prompt.

---

## Two corrections to the plan you pasted, before anything else

**1. "Whitelist Cloudways IP in Cloudflare so it doesn't block your own
webhooks" — this doesn't do what you think it does.** Your server's
*outbound* calls (2LLU calling Paystack's API) never touch Cloudflare at
all — Cloudflare only sits in front of *inbound* traffic to `2llu.com`.
The thing that actually needs whitelisting is the **reverse**: Paystack,
Flutterwave, and Stripe sending webhook POSTs *into* your domain
(`2llu.com/webhooks/payments/paystack`) — those requests pass through
Cloudflare same as any user request, and Cloudflare's Bot Fight Mode or
rate limiting can silently block or challenge them. A blocked webhook
means a contribution that was actually paid never gets marked `paid` in
your system — which is exactly the kind of silent drift Batch 6's
reconciliation job exists to catch, but you'd rather never cause it.
**Fix**: pull each gateway's current published webhook-source IP ranges
from their own docs at deploy time (don't hardcode a list here — IP ranges
change, and a stale list is worse than no list) and add them as Cloudflare
**IP Access Rules → Allow**, plus exclude `/webhooks/*` from Bot Fight
Mode entirely under `Security > Bots`.

**2. Cache bypass needs to cover more than `/dashboard/*`.** Add `/admin/*`,
`/api/*`, and `/webhooks/*` to the bypass list too — a cached webhook
response or a cached admin payout-approval page is a much worse bug than a
cached dashboard.

---

## What's missing — the part that actually determines whether migration is stress-free

### 1. The guiding rule: nothing environment-specific in code, ever

Every host-dependent thing — storage disk, queue driver, cache/session
store, mail driver — must be a `.env` value, never hardcoded. Migration
should mean "swap the `.env` file," not "find and change code." This is
the single principle everything below implements.

### 2. Storage: build for R2 from day one, even while running local disk on Namecheap

Cloudflare R2 is S3-compatible, so Laravel's built-in `s3` filesystem
driver works against it unchanged — just different endpoint config:
```php
// config/filesystems.php
'r2' => [
    'driver' => 's3',
    'key' => env('R2_ACCESS_KEY_ID'),
    'secret' => env('R2_SECRET_ACCESS_KEY'),
    'endpoint' => env('R2_ENDPOINT'), // https://<account_id>.r2.cloudflarestorage.com
    'bucket' => env('R2_BUCKET'),
    'use_path_style_endpoint' => true,
    'region' => 'auto',
],
```
**The rule for Claude Code**: every single file write in this build — KYC
selfies, bank statement PDFs, chat images, brand assets — must go through
`Storage::disk(config('filesystems.default'))`, never a raw local path.
Launch on Namecheap with `FILESYSTEM_DISK=local` if disk quota allows, but
switch to `FILESYSTEM_DISK=r2` the moment bank-statement volume grows —
since R2 has zero egress fees, this is also the cheapest option once admin
and Guardians start regularly viewing uploaded documents, not just the
most portable one.

### 3. Queues: shared hosting can't run persistent workers — design for that now

cPanel shared hosting generally doesn't allow long-running background
processes (`php artisan queue:work` staying alive forever). Cloudways/VPS
can. Don't build assuming Horizon/Redis is always there.

```php
// .env — Namecheap (shared)
QUEUE_CONNECTION=database
// cron entry (cPanel Cron Jobs panel), every minute:
// * * * * * php /home/2llu/artisan queue:work --stop-when-empty --max-time=50

// .env — Cloudways (VPS)
QUEUE_CONNECTION=redis
// Supervisor-managed, persistent: php artisan horizon
```
Every job in this build (`ProcessCircleContributionsJob`,
`ReconcileLedgerJob`, `SyncFxRatesJob`, etc.) needs to be safe under both
models — the idempotency work already done in Batch 2 (firstOrCreate
guards) is exactly what makes `--stop-when-empty` cron-driven queues safe;
don't undo that discipline later.

### 4. The classic cPanel gotcha: Laravel's `public/` folder vs `public_html`

cPanel serves a domain from `public_html` by default, but Laravel expects
its own `public/` folder to be the web root. Two options, pick one and
document it in the deploy runbook so nobody rediscovers this under
pressure on launch day:
- **Preferred**: in cPanel's domain settings, set the document root
  directly to `2llu/public` (most modern cPanel/WHM setups allow this).
- **Fallback** (if the host doesn't allow a custom document root): symlink
  or copy `public/*` into `public_html`, edit `index.php`'s `require`
  paths to point up one level to `../2llu/bootstrap/app.php`. Uglier, but
  works on older shared setups.

### 5. HTTPS detection behind Cloudflare — the other classic gotcha

Cloudflare terminates SSL at its edge; your origin server (Namecheap or
Cloudways) may receive plain HTTP internally even with `Full (Strict)` on
Cloudflare's side, unless you also proxy correctly. If Laravel doesn't
know the original request was HTTPS, it'll generate `http://` URLs and
mixed-content warnings, and OTP/payment redirect links can break.
```php
// app/Http/Middleware/TrustProxies.php
protected $proxies = '*'; // trust Cloudflare's forwarded headers
protected $headers = Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST
    | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO;
```
Set this once, works identically on Namecheap and Cloudways — one less
thing to remember on migration day.

### 6. Verify these before launch, don't assume

- **Database engine**: confirm the forked NaaraSim codebase runs on
  MySQL/MariaDB, not Postgres — shared cPanel hosting almost never offers
  Postgres, and this would block the Namecheap launch entirely if it were
  assumed wrong. Check `config/database.php`'s default connection now.
- **PHP version**: Laravel 12 needs a specific minimum PHP version — verify
  Namecheap's cPanel "MultiPHP Manager" actually offers it before launch,
  not after.
- **`exec()`/`shell_exec()`**: often disabled on shared hosting; anything
  in this build that shells out (image processing, PDF handling) needs a
  pure-PHP fallback path that doesn't depend on it.
- **Upload limits**: `upload_max_filesize`/`post_max_size` in shared
  hosting's `php.ini` are often lower than your 2MB bank-statement cap
  needs breathing room for — check via an `.htaccess` override or a
  support ticket to the host, don't discover this from a failed upload.

### 7. Log rotation — this is what actually causes the "hosting suspended
for overload" scenario you're worried about

Uncapped log files on a disk-quota-limited shared plan are a very common,
very avoidable way to get suspended.
```php
// config/logging.php
'daily' => ['driver' => 'daily', 'days' => 14], // not unlimited
```
Combine with a scheduled job that ships old logs to R2 before deleting
them locally, so you keep the history without keeping the disk pressure.

### 8. Backups that travel with you, not tied to the host's panel

Use `spatie/laravel-backup` (or equivalent) to push DB dumps + critical
files to R2 on a schedule, independent of Namecheap's or Cloudways'
built-in backup tools. This is what makes the actual migration day
low-stress — your last-known-good backup already lives somewhere neither
host controls, and it's the same restore process regardless of which
server you're restoring to (ties directly into Batch 6's quarterly restore
drill — same backup, same drill, any host).

### 9. `.env.example`, structured per environment, checked into the repo

```
# ── Shared (Namecheap cPanel) ──
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database

# ── VPS (Cloudways) — uncomment when migrating ──
# FILESYSTEM_DISK=r2
# QUEUE_CONNECTION=redis
# CACHE_STORE=redis
# SESSION_DRIVER=redis
```
Migration day becomes "comment one block, uncomment the other," not a
research project under time pressure.

## Migration-day runbook (your DNS flip, plus what has to happen around it)

1. Provision Cloudways server, deploy the same codebase via the same CI
   pipeline you already use — never a manual copy.
2. Run migrations on the new server against a **fresh** database, then
   restore the latest R2-backed backup into it (proves the backup works,
   satisfies Batch 6's restore-drill requirement in the same motion).
3. Point `QUEUE_CONNECTION`/`CACHE_STORE`/`SESSION_DRIVER` to Redis, start
   Horizon under Supervisor.
4. Smoke-test webhooks against the new server directly by IP (bypassing
   Cloudflare temporarily) before flipping DNS — you want to know Paystack/
   Flutterwave/Stripe webhooks work *before* real traffic depends on it.
5. Flip the Cloudflare A record to the Cloudways IP, exactly as you
   planned. Because Cloudflare's in the middle, this is genuinely the
   low-risk part of the day — everything above it is what makes it stay
   that way.
6. Leave the old Namecheap server running, untouched, for 48h as a
   rollback target before decommissioning it.

## Done when
- Every file write in the codebase goes through `Storage::disk()`, zero
  raw local paths, verified by grep, not just spot-checked
- Switching `FILESYSTEM_DISK` from `local` to `r2` requires no code change,
  tested against an actual R2 bucket before launch
- Every scheduled job proven safe under both `database` (cron-driven,
  shared hosting) and `redis` (persistent worker, VPS) queue connections
- `TrustProxies` correctly detects HTTPS behind Cloudflare on both hosts
- Webhook source IPs are allowlisted in Cloudflare and excluded from Bot
  Fight Mode — verified with an actual test webhook delivery, not assumed
- Log rotation is capped, with old logs shipping to R2 before local deletion
- A full migration-day dry run (steps 1-4 above) completes successfully
  against a staging Cloudways server before it's ever done for real
