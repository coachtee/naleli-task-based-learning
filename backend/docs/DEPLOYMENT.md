# Deploying to kcs.edu.za/admin

Verified against the live host on 30 August 2026.

## What the host gives us

| | |
|---|---|
| PHP | 8.3.33 |
| Database | MariaDB 11.4.13 |
| Extensions | pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, json, curl, fileinfo, bcmath, zip — all present |
| Memory limit | 2G |
| Max execution time | 30s (too short for `composer install` over the web — build the vendor directory before uploading) |
| `/home/kcseduza` | writable, 9.7 TB free |
| `/home/kcseduza/public_html/admin` | does not exist, so the URL is free |
| `exec` / `proc_open` | available, nothing in `disable_functions` |

## Database — created

```
Database: kcseduza_kcsedu
User:     kcseduza_thabisonaleli
```

Confirmed reachable: a deliberate wrong-password connection returns MySQL error
**1045** (access denied), not 1049 (unknown database) or 1044 (no rights to it),
so the user exists and is correctly granted on that database.

## Getting the package onto the server

The build container's egress policy denies outbound HTTPS to kcs.edu.za — the
proxy answers 403 to CONNECT — so the tarball cannot be pushed from there and a
policy denial is not something to route around. Upload
`kcs-backend.tar.gz` to `/home/kcseduza/` with cPanel File Manager instead. It
carries no `.env`, so nothing secret travels in it.

Everything after that runs server-side through the WordPress bridge.

## Panel path

Filament mounts at `config('kcs.panel_path')`, which reads `FILAMENT_PANEL_PATH`.
Locally that defaults to `admin`, so the panel is at `/admin` as usual. In
production it is set **empty**, because the front controller already sits inside
`public_html/admin` — leaving it as `admin` would put the dashboard at
`kcs.edu.za/admin/admin`. With it empty:

```
kcs.edu.za/admin           -> dashboard
kcs.edu.za/admin/login     -> login
kcs.edu.za/admin/api/v1/*  -> the API the Android app will call
```

## Layout

Laravel's application code must sit **outside** the web root. Only `public/`
is served.

```
/home/kcseduza/
├── kcs-backend/            <- the application, not web-accessible
│   ├── app/ bootstrap/ config/ database/ routes/ storage/ vendor/
│   └── .env
└── public_html/
    ├── admin/              <- contents of kcs-backend/public
    │   ├── index.php       <- paths edited to point at ../../kcs-backend
    │   └── .htaccess
    └── (WordPress)
```

WordPress's own `.htaccess` rewrites only what is *not* an existing file or
directory (`RewriteCond %{REQUEST_FILENAME} !-d`), so a real `admin/` directory is
served directly and needs no change to the WordPress rules.

`public_html/admin/index.php` needs two paths repointed:

```php
require __DIR__.'/../../kcs-backend/vendor/autoload.php';
$app = require_once __DIR__.'/../../kcs-backend/bootstrap/app.php';
```

## Steps

```bash
# 1 — build locally, because the host cannot run composer inside 30 seconds
composer install --no-dev --optimize-autoloader
tar -czf kcs-backend.tar.gz --exclude=.env --exclude=storage/logs/* .

# 2 — upload and unpack to /home/kcseduza/kcs-backend

# 3 — on the server
cd /home/kcseduza/kcs-backend
cp .env.example .env          # then fill in the cPanel database credentials
php artisan key:generate
php artisan migrate --seed    # creates the schema and the 13 programmes
php artisan storage:link
chmod -R 775 storage bootstrap/cache

# 4 — publish the front controller
cp -r public/. /home/kcseduza/public_html/admin/
# then edit public_html/admin/index.php as above

# 5 — production caches
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

## Before it is reachable by anyone else

- `APP_DEBUG=false` and `APP_ENV=production` in `.env`.
- `APP_URL=https://www.kcs.edu.za/admin`.
- Change the seeded `admin@kcs.edu.za` password. `AdminUserSeeder` exists so a
  fresh copy opens at all; it is not a production credential.
- Set `WEBHOOK_FLUENTFORM_SECRET` to a generated value and put the same value in
  the Fluent Forms webhook integration. `VerifyWebhookSignature` fails closed —
  an unset secret returns 503 rather than accepting unsigned posts.
- One cron entry for the scheduler:
  `* * * * * cd /home/kcseduza/kcs-backend && php artisan schedule:run >/dev/null 2>&1`

## Still to do after the first deploy

- Point the Fluent Forms webhook on form 15 at `POST /api/v1/intake/application`.
- Revoke the Perfex API token. It is still stored in the body of the now-inactive
  WPCode snippet (post 2477) and in the Perfex install at `public_html/portal`.
