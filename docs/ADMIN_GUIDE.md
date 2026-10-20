# Administrator Guide

For System Administrators and Super Administrators of the NDC Somalia
Management System. Everything here is done from the **Administration**
section of the sidebar, which is only visible to users holding the matching
permissions.

## First-time setup checklist

1. Sign in with the account created by `php artisan ndc:create-admin` and
   change the password if prompted.
2. **Security & sessions** (user menu, top right): enable two-factor
   authentication for your own account.
3. **Settings**: organisation name, default language, date format, currency,
   upload limits, reminder periods, retention, password length. Turn on
   e-mail notifications once outgoing mail has been tested.
4. **Departments**: create NDC's departments (English and Somali names,
   short code, parent department, head).
5. **Reference Data**: review document types, asset categories, expense
   categories, supplier categories and funding sources. Rename, add or
   deactivate entries to match NDC's approved lists.
6. **Roles & Permissions**: review the default roles against NDC's approved
   organisational structure and adjust (see below).
7. **Users**: create accounts and assign roles and departments.
8. **System Health**: confirm every check is OK and run a first backup.

## Users

*Administration → Users* (`users.manage`)

- **Create**: name, official e-mail, department, job title, roles and an
  initial password. The user must change that password at first sign-in.
  Tick *Also e-mail a password reset link* to let them choose their own.
- **Status**: *Active*, *Suspended* (temporary) or *Deactivated* (left the
  institution). Suspending or deactivating signs the user out of every
  session and revokes their API tokens immediately. Accounts are never
  deleted, so their history stays attributable.
- **Send password reset**: e-mails a reset link (account recovery).
- **Reset two-factor authentication**: use when a user lost their phone and
  their recovery codes. Confirm the person's identity first.
- You cannot suspend yourself or remove your own user-management access.
- Only a Super Administrator can grant or remove the Super Administrator
  role.

The user page shows the last sign-in time and IP address and the user's
recent activity.

## Roles and permissions

*Administration → Roles & Permissions* (`roles.manage`)

Default roles: Super Administrator, System Administrator, Director or
Management, Project Manager, Department Head, Project Officer, Document
Officer, Finance or Procurement Officer, General Staff, Read-Only Auditor.

Each role is a set of permissions grouped by module (view / create / update /
delete / approve, plus special ones such as `documents.view-confidential` or
`staff.view-sensitive`). Edit a role to tick or untick permissions; changes
apply immediately and are recorded in the audit log.

Good practice:

- **Least privilege** — give each person only the roles they need.
- **Separation of duties** — the system already prevents anyone approving
  their own request; additionally avoid giving one person both *create* and
  *approve* rights for budgets, expenses or purchases where staffing allows.
- The **Read-Only Auditor** role can see records, reports and the audit log
  but change nothing.
- Users can hold several roles; their permissions are combined.

## Reference data

*Administration → Reference Data* (`settings.manage`). Lists used in forms.
Deactivate entries instead of deleting them so that existing records keep
their values; inactive entries no longer appear in new forms.

## Settings

*Administration → Settings* (`settings.manage`). All changes are audited.

| Group | Settings |
| --- | --- |
| General | Organisation name, default language, date format, currency |
| Notifications & reminders | E-mail notifications on/off, days before a deadline to remind, document expiry/contract renewal reminder, warranty reminder |
| File uploads | Maximum size, permitted extensions (executable and script types are always refused) |
| Retention | Audit log, read notifications, backup archives |
| Security | Minimum password length, require two-factor authentication for administrators |

## Audit log

*Administration → Audit Log* (`audit.view`). Every sign-in, failed sign-in,
record change (with before/after values), approval, file upload/download,
export and administrative action. Filter by user, event, record type and date;
open an entry to see field-by-field changes. Export to CSV requires
`reports.export`. Entries older than the audit retention period are removed
weekly.

## System health and backups

*Administration → System Health* (`system.manage`) shows the database,
storage space, scheduler, background jobs, latest verified backup,
environment, HTTPS and e-mail status. Investigate anything marked *Attention*
or *Problem*.

*Backups*: run a backup on demand, re-verify an archive, download it. A backup
is only marked *Verified* after its contents were checked. If a scheduled
backup fails, every user with `system.manage` is notified. Restoration steps
are in [BACKUP_AND_RESTORE.md](BACKUP_AND_RESTORE.md).

Failed background jobs (for example an e-mail that could not be delivered)
are listed on the same page; retry them from the server with
`php artisan queue:retry all`.

## Integration API

Grant `api.access` (plus the relevant view permissions) to a dedicated
service account; that account issues read-only tokens from its own security
page. See [API.md](API.md). Revoke a token by suspending the account or from
the account's security page.

## Routine tasks

| When | Task |
| --- | --- |
| Daily | Glance at System Health; act on backup failure notifications |
| Weekly | Review failed jobs; review new users and role changes in the audit log |
| Monthly | Run `php artisan ndc:backup:test-restore`; review inactive accounts; review role assignments |
| Each release | Follow the update procedure in INSTALLATION.md (backup first) |
