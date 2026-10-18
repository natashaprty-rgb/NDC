# Installation and local development

## Requirements

| Component | Version | Notes |
|---|---|---|
| PHP | 8.2, 8.3 or 8.4 | Extensions: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `intl`, `xml`, `curl`, `zip`, `gd`, `tokenizer`, `ctype`. `bcmath` is **not** required. |
| Composer | 2.x | |
| MySQL | 8.0+ (MariaDB 10.6+ also works) | InnoDB, `utf8mb4` |
| Node.js | not required | The interface uses plain CSS/JS from `public/static`. |
| `mysqldump`, `gzip`, `tar` | any | Only for `php artisan ndc:backup`. |

Tested in development with PHP 8.3.6, Composer 2.8.12, MySQL 8.0.46 on Ubuntu 24.04 (Laravel 12.70).

## 1. Get the code and dependencies

```bash
git clone <repository-url> ndc-sms && cd ndc-sms
composer install
cp .env.example .env
php artisan key:generate
```

## 2. Create the database and a least-privilege account

```sql
CREATE DATABASE ndc_sms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ndc'@'localhost' IDENTIFIED BY '<strong password>';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES, LOCK TABLES ON ndc_sms.* TO 'ndc'@'localhost';
FLUSH PRIVILEGES;
```

Set `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` in `.env`.

## 3. Migrate and seed

Set the first administrator in `.env` (the password is only used once; the account must change it at first login):

```dotenv
NDC_ADMIN_NAME="System Administrator"
NDC_ADMIN_EMAIL=admin@your-domain.example
NDC_ADMIN_PASSWORD=          # leave empty to generate and print a random one
```

```bash
php artisan migrate
php artisan db:seed            # roles, permissions, generic categories, first administrator
```

Optional demonstration data (never in production — the seeder refuses to run there):

```bash
php artisan db:seed --class=DemoDataSeeder
```

All demo records are prefixed `[DEMO]` and use `example.test` addresses.

## 4. Run

```bash
php artisan serve              # http://127.0.0.1:8000
php artisan queue:work         # in a second terminal: sends notifications/emails
php artisan schedule:work      # in a third terminal: reminders, heartbeat, backups
```

## XAMPP (Windows) notes

1. Use XAMPP with PHP 8.2 or newer. Enable `extension=intl`, `extension=fileinfo`, `extension=zip`, `extension=gd` in `php.ini`.
2. Put the project outside `htdocs` if possible and point an Apache virtual host's `DocumentRoot` at the project's **`public`** folder (never the project root):
   ```apache
   <VirtualHost *:80>
       ServerName ndc.local
       DocumentRoot "C:/projects/ndc-sms/public"
       <Directory "C:/projects/ndc-sms/public">
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```
3. Create the database in phpMyAdmin using the SQL above, then run the Composer and Artisan commands from the XAMPP shell.
4. `ndc:backup` needs `mysqldump`, `gzip` and `tar` on the PATH (XAMPP ships `mysqldump`; use Git Bash for `gzip`/`tar`), or record backups manually under *Administration → Backups*.

## Running the automated tests

Tests run against a separate MySQL database (`ndc_sms_test`, configured in `phpunit.xml`) and are rebuilt on every run.

```sql
CREATE DATABASE ndc_sms_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL ON ndc_sms_test.* TO 'ndc'@'localhost';
```

```bash
php artisan test                        # whole suite
php artisan test --filter=DocumentTest  # one class
DB_DATABASE=other_test_db php artisan test   # override the test database
```

Never point the test suite at the live database: it drops and recreates every table.
