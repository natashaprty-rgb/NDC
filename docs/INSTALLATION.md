# Installation and Deployment

This guide installs the NDC Somalia Management System on a Linux server
(Ubuntu 24.04 LTS is assumed; adapt package names for other distributions).

Keep **development, testing and production** on separate servers or at least
separate databases, `.env` files and storage directories.

## 1. Requirements

| Component | Version / notes |
| --- | --- |
| PHP | 8.3 or newer, with `bcmath, ctype, curl, dom, fileinfo, gd, intl, mbstring, openssl, pdo_mysql, sodium, tokenizer, xml, zip` |
| Composer | 2.x |
| Database | MySQL 8.0+ (or MariaDB 10.11+), `utf8mb4` |
| Web server | Nginx (or Apache) with PHP-FPM |
| TLS | A valid certificate. HTTPS is mandatory in production. |
| Tools | `mysqldump` and `mysql` client (backups and restore tests), `cron`, `supervisor` |

No Node.js build step is needed: CSS and JavaScript are served as static files.

## 2. Get the code

```bash
sudo mkdir -p /var/www/ndc && sudo chown deploy:www-data /var/www/ndc
git clone <repository-url> /var/www/ndc
cd /var/www/ndc
composer install --no-dev --optimize-autoloader
```

## 3. Database

```sql
CREATE DATABASE ndc_mis CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ndc_mis'@'localhost' IDENTIFIED BY '<strong password>';
GRANT ALL PRIVILEGES ON ndc_mis.* TO 'ndc_mis'@'localhost';

-- Optional: scratch database for automated restore tests
CREATE DATABASE ndc_restore_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON ndc_restore_test.* TO 'ndc_mis'@'localhost';
```

## 4. Configure the environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` and set at least: `APP_URL` (https), `DB_*`, `MAIL_*`, and the
backup settings (`BACKUP_PATH` or a remote disk). Keep `APP_DEBUG=false` and
`APP_ENV=production`. `SESSION_SECURE_COOKIE=true` requires HTTPS.

> `.env` holds secrets. Restrict it: `chmod 640 .env`, owned by the deploy
> user with group `www-data`. It must never be committed to Git.

## 5. Install the schema and first administrator

```bash
php artisan migrate --force
php artisan db:seed --force          # permissions, default roles, reference lists
php artisan ndc:create-admin         # prompts for name, e-mail and password
php artisan storage:link             # not required: files are private; safe to skip
```

Optional (training / demonstration servers only, **never production**):

```bash
php artisan db:seed --class=DemoDataSeeder
```

## 6. Permissions on disk

```bash
sudo chown -R deploy:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 2775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;
```

Uploaded files are stored in `storage/app/private` and are only served
through the application after an authorisation check. Do not expose the
`storage` directory through the web server.

## 7. Web server (Nginx)

```nginx
server {
    listen 443 ssl http2;
    server_name mis.example.gov.so;
    root /var/www/ndc/public;

    ssl_certificate     /etc/ssl/certs/ndc.crt;
    ssl_certificate_key /etc/ssl/private/ndc.key;

    client_max_body_size 25m;   # keep >= the upload limit in Settings
    index index.php;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
    location ~ /\.(?!well-known) { deny all; }
}

server {
    listen 80;
    server_name mis.example.gov.so;
    return 301 https://$host$request_uri;
}
```

Also set `upload_max_filesize` and `post_max_size` in `php.ini` to at least
the upload limit configured in **Administration → Settings**.

## 8. Background processing

**Scheduler** — one cron entry runs reminders, backups and retention:

```cron
* * * * * cd /var/www/ndc && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled jobs (see `routes/console.php`):

| Time | Job |
| --- | --- |
| every 5 min | scheduler heartbeat (shown on System Health) |
| 01:30 daily | `ndc:backup --prune` — verified backup + retention |
| 07:00 daily | `ndc:send-reminders` — deadlines, overdue items, meetings, expiries |
| Sun 03:00 | `ndc:prune` — audit/notification retention |

**Queue worker** — notifications (in-app and e-mail) are sent on the queue.
`/etc/supervisor/conf.d/ndc-worker.conf`:

```ini
[program:ndc-worker]
command=php /var/www/ndc/artisan queue:work --sleep=3 --tries=3 --max-time=3600
user=deploy
numprocs=1
autostart=true
autorestart=true
stopwaitsecs=3600
stdout_logfile=/var/www/ndc/storage/logs/worker.log
```

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start ndc-worker
```

## 9. Optimise

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 10. Verify

1. Sign in as the administrator and open **Administration → System Health**.
   Every check should be OK (the scheduler check turns OK within five
   minutes of the cron entry being active).
2. Open **Backups → Run backup now** and confirm the run shows *Verified*.
3. Run `php artisan ndc:backup:test-restore` once the scratch database is
   configured.
4. Under **Settings**, enable e-mail notifications once mail delivery is
   confirmed, and consider requiring two-factor authentication for
   administrators.

## Updating to a new release

```bash
cd /var/www/ndc
php artisan down
php artisan ndc:backup                 # verified backup before every update
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force   # adds new permissions
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
php artisan up
```

New permissions introduced by a release are created by the seeder but not
granted to existing roles automatically; grant them under
**Administration → Roles & Permissions**.

## Migrating to another server

1. Install the application on the new server (steps 1–7) without seeding.
2. Restore the latest verified backup there (see
   [BACKUP_AND_RESTORE.md](BACKUP_AND_RESTORE.md)).
3. Copy the `APP_KEY` from the old `.env` — it is required to decrypt
   encrypted fields (two-factor secrets and sensitive staff data).
4. Point DNS to the new server and re-run the verification steps.
