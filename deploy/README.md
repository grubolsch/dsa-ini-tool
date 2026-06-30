# Deploying to Laravel Forge (single domain)

This app is a monorepo: a **Symfony** API in `backend/` and a **React/Vite** SPA in `frontend/`,
plus a **Mercure** hub for real-time (SSE) sync. We deploy it as **one Forge site on one domain**:

```
                         ┌─────────────────────────── your-domain.com (Nginx, TLS) ──┐
  browser  ── https ──▶  │  /                      → frontend/dist  (React SPA)        │
                         │  /api, /uploads         → backend/public/index.php (PHP-FPM)│
                         │  /.well-known/mercure   → 127.0.0.1:3000 (Mercure daemon)   │
                         └──────────────────────────────────────────────────────────┘
```

Single origin ⇒ **no CORS** and **same-origin SSE**, and the frontend needs no code changes
(it already calls `/api` and subscribes to `/.well-known/mercure` relatively).

Files in this folder:
- `forge-deploy.sh` — the Forge deploy script
- `nginx-site.conf` — the Nginx site config to merge in
- `env.production.example` — the backend `.env.local` template

> Replace `DOMAIN` / `your-domain.com` and the PHP version (`php8.4`) everywhere to match.
> **Use PHP 8.4** for the site — the backend requires ≥ 8.4.1.

---

## 1. Create the site
1. Forge → Server → **New Site**. Project type **Static HTML / General PHP**.
   Set **Web Directory** to `/frontend/dist`.
2. **Git Repository**: `grubolsch/dsa-ini-tool`, branch `main`. (Don't enable "Install Composer
   dependencies" — our deploy script handles backend *and* frontend.)
3. Set the site's **PHP version to 8.4** (Site → Settings).
4. Create a **MySQL database + user** (Server → Database) and note the credentials.

## 2. Environment
Forge → Site → **Environment**. Paste `env.production.example`, then fill in:
- `APP_SECRET` and `MERCURE_JWT_SECRET` — strong random values:
  `php -r 'echo bin2hex(random_bytes(24)), PHP_EOL;'`
- `DATABASE_URL` — the DB name/user/password from step 1.
- `MERCURE_PUBLIC_URL` / `CORS_ALLOW_ORIGIN` — your real domain.

This file is written to `backend/.env.local` and overrides the committed `backend/.env`.

## 3. Mercure daemon (self-hosted)
Download the standalone Mercure binary once (SSH in as `forge`):

```bash
mkdir -p /home/forge/mercure && cd /home/forge/mercure
# grab the latest Linux x86_64 build from https://github.com/dunglas/mercure/releases
curl -L https://github.com/dunglas/mercure/releases/latest/download/mercure_Linux_x86_64.tar.gz | tar xz
# this provides the `mercure` binary + a Caddyfile that reads the env vars below
```

Then Forge → Server → **Daemons** → New Daemon:
- **Command:**
  ```
  SERVER_NAME=':3000' MERCURE_PUBLISHER_JWT_KEY='<MERCURE_JWT_SECRET>' MERCURE_SUBSCRIBER_JWT_KEY='<MERCURE_JWT_SECRET>' MERCURE_EXTRA_DIRECTIVES='anonymous' GLOBAL_OPTIONS='auto_https off' /home/forge/mercure/mercure run
  ```
- **Directory:** `/home/forge/mercure`  **User:** `forge`

Notes:
- Use the **same** secret as `MERCURE_JWT_SECRET` in the env file (both publisher & subscriber keys).
- `anonymous` lets player views subscribe without a token; `auto_https off` + `:3000` means plain
  HTTP on localhost — Nginx terminates TLS in front of it.
- No `cors_origins` needed (same origin). Add one only if you later split onto another domain.

## 4. Nginx
1. First enable TLS: Forge → Site → **SSL** → Let's Encrypt (so the `443` server block + cert exist).
2. Site → **Edit Files → Edit Nginx Configuration**. Merge in `nginx-site.conf`:
   keep Forge's managed lines (`server {`, `listen … ssl`, `server_name`, `ssl_certificate*`,
   and the `# FORGE CONFIG (DO NOT REMOVE!)` include); **replace** the `root`, `index`, and the
   default `location /` and `location ~ \.php$` blocks with the ones provided.
3. Save (Forge validates & reloads Nginx).

## 5. Deploy script
Forge → Site → **App** → Deploy Script: paste `forge-deploy.sh` and set `DOMAIN`.
It pulls, installs Composer deps (`--no-dev`), runs migrations, clears/warms cache, then
`npm ci && npm run build` (with the prod `VITE_*` URLs exported first), and reloads PHP-FPM.

Make sure **Node/npm** is available to the `forge` user (Forge installs Node; if the deploy
can't find `npm`, add the nvm `use` line or the absolute node path to the script).

## 6. First deploy
Click **Deploy Now**. Then visit `https://your-domain.com`:
- the home screen loads (SPA),
- "Manage party" / "Load encounter" work (`/api`),
- start an encounter and confirm the connection pill shows **🟢 live** (Mercure via `/.well-known/mercure`),
- open the player view on a phone via the QR code.

## Troubleshooting
- **502 on `/.well-known/mercure`** → the Mercure daemon isn't running / wrong port. Check the
  Daemon log in Forge; confirm it listens on `:3000`.
- **🔴 offline pill** → `MERCURE_PUBLIC_URL` mismatch, or Nginx not proxying the Mercure location
  with `proxy_buffering off`.
- **API 404 / blank** → the `@symfony` `fastcgi_param SCRIPT_FILENAME` path or PHP-FPM socket
  version is wrong for your server.
- **Refresh on `/run/:code` 404s** → the SPA `try_files … /index.html` fallback isn't in place.
- **Migrations fail** → check `DATABASE_URL` in the Environment file; run `php backend/bin/console
  doctrine:migrations:migrate` manually over SSH to see the error.
- **Uploads 404** → the `forge` user must be able to write `backend/public/uploads/` (it's created
  from the repo's `.gitkeep`; `chmod`/`chown` to `forge` if needed).
