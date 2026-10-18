# Backup and restoration

## What must be backed up

| Item | Location | How |
|---|---|---|
| Database | MySQL schema `ndc_sms` | `php artisan ndc:backup` (mysqldump, `--single-transaction`, gzip) |
| Uploaded files | `storage/app/private` (documents, attachments) | `php artisan ndc:backup --files` (tar.gz) |
| Configuration | `.env` (contains secrets, incl. `APP_KEY`) | Copy to the organisation's secrets store **once and after every change**. Without the same `APP_KEY`, encrypted values (MFA secrets, sessions) cannot be read. |

Backups are written to `storage/app/private/backups/` (`ndc-db-YYYYmmdd-HHMMSS.sql.gz`, `ndc-files-….tar.gz`), keeping the newest 14 of each by default. Each run is recorded under *Administration → Backups* with size and SHA-256 checksum. **Copy these files off the server daily** (e.g. `rsync` to a backup host or encrypted external storage) — a backup on the same disk does not protect against disk loss.

Schedule: daily at 01:30 via the Laravel scheduler (see `06-deployment.md`). Manual run:

```bash
php artisan ndc:backup --files
```

## Restoration procedure

1. Put the application in maintenance mode: `php artisan down`.
2. Verify the backup file's checksum against the value shown under *Backups*: `sha256sum ndc-db-….sql.gz`.
3. Restore the database (into a new empty schema first if you want to inspect before switching):
   ```bash
   mysql -e "CREATE DATABASE ndc_sms_restore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   gunzip -c ndc-db-YYYYmmdd-HHMMSS.sql.gz | mysql ndc_sms_restore
   ```
   Then either point `DB_DATABASE` at `ndc_sms_restore` or drop/recreate `ndc_sms` and load the dump into it.
4. Restore files:
   ```bash
   tar -xzf ndc-files-YYYYmmdd-HHMMSS.tar.gz -C storage/app/private
   chown -R ndc:www-data storage/app/private
   ```
5. Make sure `.env` (same `APP_KEY`) is in place, then `php artisan migrate:status` (all migrations should show *Ran*), `php artisan optimize:clear`.
6. `php artisan up`, sign in, open a document version download and a few records to confirm.
7. Record the test or recovery under *Backups → Record test*.

## Verification performed during development (2026-10-18)

On the development server (MySQL 8.0.46):

* `php artisan ndc:backup --files` produced `ndc-db-20261018-195000.sql.gz` (16 KB) and a files archive; both recorded as *successful* with checksums.
* The dump was restored into an empty schema `ndc_sms_restore_test`: all **59 tables** were recreated; row counts matched the live database for `users`, `roles`, `permissions`, `permission_role`, `departments` and `migrations`. `audit_logs` had 3 fewer rows — exactly the entries written by the backup command itself after the snapshot.
* The application connected to the restored schema: `migrate:status` reported every migration as *Ran*, and the administrator's password hash verified.
* The automated test `SystemAndBackupTest::test_backup_command_creates_compressed_dump_and_record` repeats the dump + checksum check on every test run.

This verified the procedure on development data only. A restoration test on the production infrastructure must be carried out and recorded after go-live.
