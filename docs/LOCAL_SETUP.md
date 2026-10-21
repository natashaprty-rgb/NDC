# Running on a Local Computer (Windows + XAMPP)

For trying the system on your own computer. For a real server, use
[INSTALLATION.md](INSTALLATION.md).

## 1. Requirements

- **XAMPP** with PHP 8.3 or newer (check with `php -v` in a terminal).
- **Composer** (https://getcomposer.org/download/).
- In `C:\xampp\php\php.ini` (or `D:\xampp\php\php.ini`), make sure these lines
  are present **without** a leading `;`:
  `extension=fileinfo`, `extension=gd`, `extension=intl`, `extension=mbstring`,
  `extension=openssl`, `extension=pdo_mysql`, `extension=sodium`, `extension=zip`.

## 2. Start MySQL and create the database

1. Open the **XAMPP Control Panel** and click **Start** next to **MySQL**.
2. Open http://localhost/phpmyadmin → **New** → database name `ndc_mis`,
   collation `utf8mb4_unicode_ci` → **Create**.

## 3. Configure `.env`

In the project folder, copy `.env.example` to `.env` (if it is not there
yet) and change these lines:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ndc_mis
DB_USERNAME=root
DB_PASSWORD=

MAIL_MAILER=log
```

XAMPP's MySQL user is `root` with an **empty** password unless you changed it.
`MAIL_MAILER=log` writes e-mails to `storage/logs/laravel.log` instead of
sending them.

## 4. Install

Run these in the project folder (the one that contains `artisan`):

```bash
composer install
php artisan key:generate
php artisan migrate --seed
php artisan ndc:create-admin
php artisan ndc:doctor
```

`ndc:create-admin` asks for your name, e-mail and a password (at least 10
characters with upper- and lower-case letters, a number and a symbol).
`ndc:doctor` should end with *Everything needed to run the application is in
place.* If not, it tells you what to fix.

Optional sample data for exploring the system:

```bash
php artisan db:seed --class=DemoDataSeeder
```

## 5. Run

```bash
php artisan serve
```

Open http://127.0.0.1:8000 and sign in with the administrator account.

## Troubleshooting

| Symptom | Cause and fix |
| --- | --- |
| **500 Server Error** on every page | Usually the database: MySQL not started, database not created, wrong `DB_USERNAME`/`DB_PASSWORD`, or migrations not run. Run `php artisan ndc:doctor`. With `APP_DEBUG=true` the page shows the exact error. |
| **419 Page Expired** when signing in | `APP_URL` starts with `https://` while you use `http://`. Set `APP_URL=http://127.0.0.1:8000`, then run `php artisan config:clear`. |
| Changes to `.env` have no effect | Run `php artisan config:clear` (and `php artisan optimize:clear`). |
| `could not find driver` | `extension=pdo_mysql` is commented out in `php.ini`. |
| Page styles missing | Open the site through `php artisan serve` (http://127.0.0.1:8000), not through `http://localhost/...public`. |

The detailed error for any 500 is always written to
`storage/logs/laravel.log` (newest entries at the bottom).
