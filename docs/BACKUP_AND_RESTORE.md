# Backup and Restoration Guide

## What is backed up

Each backup is a single ZIP archive containing:

| Entry | Content |
| --- | --- |
| `database/database.sql` | Full MySQL dump (`mysqldump --single-transaction --routines --triggers`) |
| `files/...` | Every uploaded file and document version (`storage/app/private`) |
| `manifest.json` | Creation time, database driver, migration count and a SHA-256 checksum for every entry |

Not included, and must be protected separately: the `.env` file (above all
`APP_KEY`, without which encrypted fields cannot be read) and the
application code (held in the Git repository).

## How backups run

- Automatically every day at 01:30 by the scheduler (`ndc:backup --prune`).
- Manually from **Administration → System Health → Backups → Run backup now**
  or `php artisan ndc:backup`.

The database password is passed to `mysqldump` through the environment, never
on the command line.

## Verification — completion is never assumed

After writing the archive, the system re-reads it from the backup disk and:

1. compares the archive's SHA-256 checksum with the value recorded at creation;
2. opens the ZIP with consistency checks;
3. verifies the SHA-256 of every entry against the manifest;
4. checks the database dump contains the core tables and mysqldump's
   completion marker (for SQLite, opens the copy and compares the migration
   count).

Only then is the run marked **Verified**. Otherwise it is **Failed** or
**Completed, not verified**, the reason is shown, and every user with the
`system.manage` permission receives a notification. Every run is recorded in
the backup log with its size, checksum and verification result. Any archive
can be re-verified from the Backups page.

## Storage and retention

- Archives are written to the `backups` disk (`storage/app/backups` by
  default). **In production, set `BACKUP_PATH` to a separate volume that is
  copied off the server daily**, or configure the disk as S3/SFTP storage, so
  that copies survive loss of the server.
- Archives older than the retention period (Settings → Retention, default 30
  days) are deleted by `--prune`. The most recent verified backup is always
  kept.
- Restrict access to backup storage: archives contain all institutional
  records. Downloads from the interface are logged in the audit trail.

## Periodic restoration test

Prove at least monthly (and after every upgrade) that a backup can actually be
restored:

```bash
# .env must define RESTORE_TEST_DB_DATABASE (a separate scratch database)
php artisan ndc:backup:test-restore          # latest verified backup
php artisan ndc:backup:test-restore 42       # a specific run
```

The command downloads the archive, imports the dump into the scratch
database, lists every table with its row count and records the result in the
audit log. It refuses to run against the live database.

Record each test (date, backup run, result, person) in the institution's
maintenance log.

## Full restoration procedure

Use this after data loss or when moving to a new server. Responsible
personnel: the System Administrator, with a second person verifying.

1. **Stop changes**: `php artisan down`; stop the queue worker
   (`sudo supervisorctl stop ndc-worker`).
2. **Choose the archive**: the most recent run marked *Verified*
   (Backups page, or the files on the backup volume).
3. **Check it**: `unzip -t ndc-backup-YYYYMMDD-HHMMSS-N.zip` and compare
   `sha256sum` with the checksum shown on the Backups page.
4. **Extract** to a temporary directory:
   ```bash
   mkdir /tmp/ndc-restore && cd /tmp/ndc-restore
   unzip /path/to/ndc-backup-....zip
   ```
5. **Restore the database** (this replaces all current data):
   ```bash
   mysql -u ndc_mis -p -e "DROP DATABASE ndc_mis; CREATE DATABASE ndc_mis CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u ndc_mis -p ndc_mis < database/database.sql
   ```
6. **Restore files**:
   ```bash
   rsync -a --delete files/ /var/www/ndc/storage/app/private/
   sudo chown -R deploy:www-data /var/www/ndc/storage/app/private
   ```
7. **Restore `.env`** from its secure copy if the server was rebuilt
   (the original `APP_KEY` is required).
8. **Bring the application up**:
   ```bash
   cd /var/www/ndc
   php artisan migrate --force      # applies any newer migrations
   php artisan optimize:clear && php artisan config:cache
   sudo supervisorctl start ndc-worker
   php artisan up
   ```
9. **Verify**: sign in, open several documents and download a file, check
   System Health, and run a new backup.
10. **Record** the incident and restoration in the maintenance log and delete
    `/tmp/ndc-restore`.

## Recovery objectives

With the default daily schedule, up to 24 hours of changes may be lost
(recovery point). Agree the acceptable recovery point and recovery time with
NDC management; for a shorter recovery point, schedule `ndc:backup` more often
in `routes/console.php` or add MySQL binary-log shipping.
