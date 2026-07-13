# NaaraSim — Deployment

NaaraSim installs like a CodeCanyon product: upload, open the site, and the web
installer runs (requirements → database → application → provider keys →
finalize). Provider keys left blank show **Coming Soon** and flip to **Active**
the moment a real key is saved — so you can launch one product at a time (e.g.
Getatext SMS first) with everything else parked. (Blueprint Sections 22 & 17.4.)

## 1. Web installer (both VPS and cPanel)

1. Upload the build and point the document root at **`public/`**.
2. Make `storage/` and `bootstrap/cache/` writable.
3. Visit the site — you'll be redirected to **`/install`**:
   - **Requirements** — PHP 8.2+, required extensions, writable paths, Redis.
   - **Database** — host / port / name / user / password (runs `migrate --seed`).
   - **Application** — app name, URL, and your admin email + password.
   - **Providers** — paste keys (blank = Coming Soon).
   - **Finalize** — writes `.env`, generates `APP_KEY`, caches config/routes/
     views/icons, creates the `storage/installed` lock, and sends you to the
     admin login.

To re-run the installer, delete `storage/installed`.

## 2. Shared cPanel

- Point the domain document root to `public/` (or use "Setup Node/PHP App").
- **Redis:** use cPanel Redis if available, else managed Redis (Upstash / Redis
  Cloud) via `.env`. On a very small plan you can fall back to the database
  queue/cache with a clear performance trade-off — Redis is strongly recommended.
- **Cron:** add the single scheduler entry:

  ```
  * * * * * cd /home/USER/naarasim && php artisan schedule:run >> /dev/null 2>&1
  ```

- **Queue worker:** run Horizon via Supervisor if available; otherwise a cron
  entry running `php artisan queue:work --stop-when-empty` each minute.
- **Storage:** Wasabi keeps large files off the shared disk (important on cPanel
  quotas) — set the `WASABI_*` keys.

## 3. VPS (scale path)

Nginx + PHP-FPM 8.2, MySQL 8, Redis, Supervisor running Horizon, Soketi for
WebSockets, Cloudflare in front.

- **Supervisor** — one program running `php artisan horizon`.
- **Cron** — the single `schedule:run` entry above.
- **Scheduled tasks** (via `schedule:run`): `providers:health-check` every 15
  min; `esim:sync` daily.

## 4. CI/CD (`.github/workflows/deploy.yml`)

On push to `main`: `composer install --no-dev -o` → `npm ci && npm run build` →
PHPUnit (fails the build on any failure) → SSH deploy. The deploy step runs only
when these repo secrets are set (otherwise the build still passes):

`DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY`, `DEPLOY_PATH`.

The deploy runs: `artisan down` → `git pull` → install → build →
`migrate --force` → `config:cache` + `route:cache` + `view:cache` +
`icons:cache` → `horizon:terminate` → `artisan up`.

## 5. Scaling to millions

Queues absorb every provider call, so spikes never block requests; Horizon
scales workers per queue. Catalogue/price caches (Redis, tagged) keep provider
APIs off the hot path. Stateless app nodes + shared Redis/DB = add nodes
horizontally; pre-signed Wasabi/CDN URLs offload all file delivery.
