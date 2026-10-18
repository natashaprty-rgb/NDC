# Production deployment (Linux)

This guide assumes Ubuntu 22.04/24.04, Nginx, PHP-FPM 8.3 and MySQL 8 on the same or a private host.
Adapt paths to your environment. **No production deployment has been performed as part of this delivery.**

## 1. Server preparation

```bash
sudo apt install nginx mysql-server php8.3-fpm php8.3-{mysql,mbstring,xml,curl,zip,gd,intl} unzip git supervisor
sudo adduser --system --group --home /var/www ndc
```

* Firewall: allow only 22 (restricted), 80, 443.
* Obtain a TLS certificate (e.g. Let's Encrypt `certbot --nginx`). HTTPS is mandatory.
* Keep the OS and PHP updated (`unattended-upgrades`).

## 2. Application

```bash
sudo -u ndc git clone <repository-url> /var/www/ndc-sms
cd /var/www/ndc-sms
sudo -u ndc composer install --no-dev --optimize-autoloader
sudo -u ndc cp .env.example .env && sudo -u ndc php artisan key:generate
```

Production `.env` (minimum):

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ndc-sms.your-domain.example
APP_TIMEZONE=Africa/Mogadishu
DB_DATABASE=ndc_sms
DB_USERNAME=ndc
DB_PASSWORD=<from the secrets store, never committed>
SESSION_DRIVER=database
SESSION_LIFETIME=60
SESSION_EXPIRE_ON_CLOSE=true
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
CACHE_STORE=database
MAIL_MAILER=smtp
MAIL_HOST=...  MAIL_PORT=587  MAIL_USERNAME=...  MAIL_PASSWORD=...  MAIL_FROM_ADDRESS=no-reply@...
LOG_LEVEL=warning
```

Permissions — only `storage/` and `bootstrap/cache/` are writable by PHP:

```bash
sudo chown -R ndc:www-data /var/www/ndc-sms
sudo find /var/www/ndc-sms -type d -exec chmod 750 {} \;
sudo find /var/www/ndc-sms -type f -exec chmod 640 {} \;
sudo chmod -R ug+rwX /var/www/ndc-sms/storage /var/www/ndc-sms/bootstrap/cache
sudo chmod 600 /var/www/ndc-sms/.env
```

Database and caches:

```bash
sudo -u ndc php artisan migrate --force
sudo -u ndc php artisan db:seed --force          # first install only
sudo -u ndc php artisan config:cache
sudo -u ndc php artisan route:cache
sudo -u ndc php artisan view:cache
```

## 3. Nginx

```nginx
server {
    listen 443 ssl http2;
    server_name ndc-sms.your-domain.example;
    root /var/www/ndc-sms/public;          # only public/ is exposed
    index index.php;
    client_max_body_size 25m;               # >= uploads.max_kb setting

    ssl_certificate     /etc/letsencrypt/live/ndc-sms.your-domain.example/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/ndc-sms.your-domain.example/privkey.pem;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
    location ~ /\.(?!well-known) { deny all; }
}
server { listen 80; server_name ndc-sms.your-domain.example; return 301 https://$host$request_uri; }
```

Uploaded files live in `storage/app/private` (outside `public/`) and are served only by the application after an authorisation check. Never create a `public/storage` symlink for them.

## 4. Queue worker (Supervisor)

`/etc/supervisor/conf.d/ndc-queue.conf`:

```ini
[program:ndc-queue]
command=php /var/www/ndc-sms/artisan queue:work --sleep=3 --tries=3 --max-time=3600
user=ndc
numprocs=1
autostart=true
autorestart=true
stopwaitsecs=3600
stdout_logfile=/var/www/ndc-sms/storage/logs/queue.log
```

```bash
sudo supervisorctl reread && sudo supervisorctl update
```

The queue delivers in-app notifications and email. If the worker stops, notifications wait in the `jobs` table (shown on *System health*).

## 5. Scheduler (cron)

```cron
* * * * * cd /var/www/ndc-sms && php artisan schedule:run >> /dev/null 2>&1
```
(as the `ndc` user: `sudo crontab -u ndc -e`)

Scheduled jobs (`routes/console.php`):

| Job | When |
|---|---|
| Scheduler heartbeat (shown on System health) | every minute |
| `ndc:send-reminders` — task deadlines/overdue, meetings, document/contract expiry, correspondence deadlines, approval digest | hourly (no duplicates) |
| `ndc:backup --files --keep=14` | daily 01:30 |
| `queue:prune-failed` | daily |

## 6. Updating (deployment) and rollback

```bash
cd /var/www/ndc-sms
sudo -u ndc php artisan ndc:backup --files          # 1. backup first
sudo -u ndc php artisan down --retry=60              # 2. maintenance mode
sudo -u ndc git fetch && sudo -u ndc git checkout <release-tag>
sudo -u ndc composer install --no-dev --optimize-autoloader
sudo -u ndc php artisan migrate --force
sudo -u ndc php artisan optimize:clear && sudo -u ndc php artisan optimize
sudo supervisorctl restart ndc-queue
sudo -u ndc php artisan up
```

Rollback:

1. `php artisan down`
2. `git checkout <previous-release-tag>` and `composer install --no-dev`
3. If the release ran migrations: either `php artisan migrate:rollback --step=<n>` (only when the migrations' `down()` is safe and no new data depends on them) **or** restore the pre-deployment database backup (see `07-backup-restore.md`).
4. `php artisan optimize:clear && php artisan optimize`, restart the queue, `php artisan up`.

## 7. Environment separation

Use separate servers or at least separate databases, `.env` files and `APP_KEY`s for development, testing/staging and production. Never copy production data into development without anonymisation and authorisation. The yellow banner at the top of every page appears whenever `APP_ENV` is not `production`.

## 8. Post-deployment checklist

- [ ] *Administration → System health* shows all checks OK (HTTPS, debug off, secure cookies, scheduler, queue, backup).
- [ ] First administrator changed the temporary password and enabled two-step verification.
- [ ] Organisation settings and reference prefixes reviewed; approved branding applied (`public/assets/img/README.md`).
- [ ] Email delivery tested (enable *Settings → Notifications → email*).
- [ ] A backup restoration test performed and recorded under *Backups*.
- [ ] `composer audit` shows no known vulnerabilities.
