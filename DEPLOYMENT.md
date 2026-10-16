# Deployment — sh.damatech.sy

Production runbook for the accounting system. Written at phase 18.

## Environment

| | |
|---|---|
| Host | `sh.damatech.sy` |
| Web server | nginx → `/etc/nginx/sites-available/sh.damatech.sy` |
| Document root | `/var/www/sh.damatech.sy/htdocs/public` (**must** be `public/`, never the project root) |
| PHP-FPM socket | `/run/php/php8.4-fpm-shdamatechsy.sock` |
| Database | MySQL `shdamatechsy_db` |
| App user | `shdamatechsy:www-data` |

`.env` is `640 shdamatechsy:www-data`. Keep it that way — it holds `APP_KEY` and the
database and Redis credentials.

## Deploying a change

```bash
cd /var/www/sh.damatech.sy/htdocs

php artisan down                        # optional, for schema changes

git pull                                # or however the code arrives
composer install --optimize-autoloader  # add --no-dev once this box stops running tests
npm ci && npm run build                 # rebuild the Vue/Vite assets

php artisan migrate --force

# Caches must be rebuilt after ANY change to .env, config/, or routes/.
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

chown -R shdamatechsy:www-data storage bootstrap/cache

php artisan up
```

### Rolling the caches back

`php artisan optimize:clear` drops all four caches. Do this before editing `.env`
locally, or the change will not take effect.

## The config cache and `env()`

With `bootstrap/cache/config.php` present, Laravel **does not read `.env`**, and
`env()` returns its default everywhere outside `config/`. Two consequences:

- Any change to `.env` needs `php artisan config:cache` to take effect.
- `database/seeders/AdminUserSeeder.php` reads `ADMIN_EMAIL`, `ADMIN_NAME` and
  `ADMIN_INITIAL_PASSWORD` through `env()`. Run it with the cache cleared:

  ```bash
  php artisan config:clear
  ADMIN_INITIAL_PASSWORD='...' php artisan db:seed --class=AdminUserSeeder
  php artisan config:cache
  ```

  Without `ADMIN_INITIAL_PASSWORD` the seeder generates a random password and
  prints it once. It is not recoverable afterwards.

## Running the tests on this box

**Use `composer test`.** It clears the config cache, runs the suite, and rebuilds
the cache:

```bash
composer test
```

Running `php artisan test` directly against a config-cached app points the whole
suite — including `RefreshDatabase`'s `migrate:fresh` — at the **live MySQL
database**, because `phpunit.xml`'s `<env>` entries are ignored once
`bootstrap/cache/config.php` exists. `tests/TestCase.php` now aborts with an
explanatory error rather than letting that proceed, but `composer test` avoids
the situation entirely.

(`APP_ENV=production` is a second line of defence: Laravel refuses destructive
migrations in production without `--force`.)

## Seeding a fresh install

```bash
php artisan migrate --force
php artisan db:seed --class=SystemConfigSeeder   # roles, permissions, settings, numbering prefixes
php artisan db:seed --class=AdminUserSeeder      # see the note above
```

`SystemConfigSeeder` is idempotent (`updateOrInsert`) and safe to re-run after
adding settings or permissions.

### `repairs.settle`

The repair settlement migration adds this permission and grants it to `admin`
and `accountant` automatically. It covers taking a deposit, issuing the
settlement invoice and collecting the balance — so grant it by hand to any other
role whose staff handle money at the maintenance counter.

## Background work

There is no queue worker and no scheduler entry, because the application
currently dispatches no jobs and schedules no commands. `QUEUE_CONNECTION` is
`database`, so if a job is ever dispatched it will sit in the `jobs` table
unprocessed. Before adding the first job, add a worker:

```ini
# /etc/supervisor/conf.d/sh-damatech-worker.conf
[program:sh-damatech-worker]
command=php /var/www/sh.damatech.sy/htdocs/artisan queue:work --sleep=3 --tries=3 --max-time=3600
user=shdamatechsy
autostart=true
autorestart=true
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/sh.damatech.sy/htdocs/storage/logs/worker.log
```

and, if a scheduled command is ever added:

```
* * * * * cd /var/www/sh.damatech.sy/htdocs && php artisan schedule:run >> /dev/null 2>&1
```

## Logging

`LOG_STACK=daily`, `LOG_LEVEL=warning`. Laravel rotates daily and keeps 14 files
by default (`config/logging.php`). `storage/logs` is `750`; it is outside the
document root and must stay there.

`storage/logs/laravel-preproduction-*.log` is the log from before the document
root was corrected, when it was publicly readable. Review and delete it.

## TLS

The site is served over HTTPS with a Let's Encrypt certificate for
`sh.damatech.sy`. Port 80 serves only the ACME challenge and 301-redirects
everything else.

| | |
|---|---|
| Certificate | `/etc/letsencrypt/live/sh.damatech.sy/` |
| Lineage | `sh.damatech.sy` (obtained with `certonly --nginx`) |
| Renewal | `certbot.timer`, twice daily |
| Reload after renewal | `/etc/letsencrypt/renewal-hooks/deploy/nginx-reload.sh` (global, covers every lineage) |

The vhost was written by hand rather than by `certbot --nginx`, so the hardened
configuration — front-controller-only PHP, the dotfile deny, the `public/` root —
survives renewals. **Do not run `certbot --nginx` against this domain**; it would
rewrite the server block. Use `certonly` if the certificate ever needs reissuing:

```bash
certbot certonly --nginx -d sh.damatech.sy --cert-name sh.damatech.sy
```

`ssl_stapling` is deliberately absent: Let's Encrypt certificates no longer carry
an OCSP responder URL, so nginx ignores the directive and warns on every reload.

HSTS is asserted for one year including subdomains, via the `$damatech_hsts` map
in `/etc/nginx/conf.d/damatech-hsts-map.conf`, which emits the header only when
`$scheme` is https. Because browsers cache HSTS, **reverting this domain to plain
HTTP would break it for anyone who has already visited** until the max-age
expires.

`APP_URL=https://sh.damatech.sy` and `SESSION_SECURE_COOKIE=true` are set, so the
session cookie is issued with the `Secure` flag.

## Health check

`GET /up` returns 200 when the framework boots. Point uptime monitoring at it.

## Still outstanding

- **Mail is not configured.** `MAIL_MAILER=log`, so nothing is actually sent.
  Fine while the app sends no mail; set it up before adding password resets.

- **No backups.** There is no dump of `shdamatechsy_db` scheduled. For an
  accounting system this is the largest remaining operational gap.

## Multi-tenancy (repair.damatech.sy)

With `TENANCY_ENABLED=true` the app serves three kinds of host (`config/tenancy.php`):

| Host | Serves | Database |
|------|--------|----------|
| `repair.damatech.sy` | Landing page + "find your shop" | `repair_central` |
| `admin.repair.damatech.sy` | Platform admin: create / suspend / archive shops, subscriptions, modules | `repair_central` |
| `<slug>.repair.damatech.sy` | One shop's system | `repair_t_<slug>` |

The original single-shop data lives on as the `main` shop (`repair_t_main`). The
pre-tenancy database `repair_db` is left untouched as a fallback; a dump is in
`/var/backups/repair/`.

**Deploy** — plain `php artisan migrate` is refused while tenancy is on (it would
build shop tables in the central database). Use:

```bash
php artisan tenants:migrate          # central first, then every shop
```

**Shops**

```bash
php artisan tenancy:admin admin@damatech.sy             # create a platform admin / reset its password
php artisan tenants:create <slug> "<name>" <owner-email> # same as the admin panel's "new shop"
php artisan tenants:certificate <slug> | --pending      # issue/retry HTTPS for shops
```

**HTTPS** — each shop gets its own Let's Encrypt certificate (webroot) and a
server block in `/etc/nginx/repair-tenants/`, issued the moment the shop is
created (or from the admin panel's retry button). The shared vhost body is
`/etc/nginx/snippets/repair-app.conf`; DNS already has `*.repair.damatech.sy`.

PHP-FPM runs with `ProtectSystem=full`, so nothing it spawns can write
`/etc/letsencrypt`. The app therefore calls
`sudo -n /usr/local/sbin/repair-tenant-site-request <host>` (the only command
`/etc/sudoers.d/repair-tenant-site` allows the `repair` user), which validates the
host and starts the one-shot unit `repair-tenant-site@<host>.service`. That unit
runs `/usr/local/sbin/repair-tenant-site <host>` outside the sandbox: certbot
(3 attempts), the server block, `nginx -t`, reload, and a wait until the new
certificate is served. Logs: `journalctl -u 'repair-tenant-site@*'`.
`php artisan tenants:certificate <slug>` does the same from the shell, and the
scheduler retries `--pending` shops hourly if its cron is installed.

**Self-service sign-up** — `repair.damatech.sy/register` lets a visitor create
their own shop on a free trial (`plan` "تجريبية", `expires_at` = today +
`TENANCY_SIGNUP_TRIAL_DAYS`, default 14); it closes automatically on expiry until
an admin extends it. Guards: 3 sign-ups/hour and 10/day per IP, a site-wide
`TENANCY_SIGNUP_DAILY_LIMIT` (default 20 — each sign-up costs a Let's Encrypt
certificate, 50/week per domain), one sign-up per email, and a honeypot field.
`TENANCY_SIGNUP_ENABLED=false` turns it off; the landing page then falls back to
"sign in". Sign-ups show a "تسجيل ذاتي" badge in the admin panel.

The `.env` keys involved: `TENANCY_*`, `DB_DATABASE`/`DB_CENTRAL_DATABASE`
(`repair_central`), `SESSION_DOMAIN=null` (cookies stay per host),
`SESSION_COOKIE`, and `SANCTUM_STATEFUL_DOMAINS` including `*.repair.damatech.sy`.
