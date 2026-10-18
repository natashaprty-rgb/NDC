# Administrator guide

For System Administrators and Super Administrators. Menu items appear only when your role grants them.

## First steps after installation

1. Sign in with the seeded administrator account and set a new password (forced on first sign-in).
2. *My profile → Two-step verification*: enable it.
3. *Settings → Organisation*: enter the approved official name, short name and contact details.
4. *Settings → Reference numbering*: confirm the prefixes (e.g. `NDC-PRJ-2026-0001`). Changes affect new records only.
5. *Settings → Categories & lists*: review document, expense and asset categories, locations and funding sources. The seeded entries are generic examples — rename, add or deactivate them. Entries are deactivated, never deleted, so old records keep their category.
6. *Departments*: register the organisational units and their heads.
7. *Staff directory*: register staff (only work-related details).
8. *User accounts*: create an account for each person, assign roles, link it to the staff record (from the staff form).

## User accounts

* **Create:** enter name, email, language and roles. Leave the password empty to generate a temporary one; it is shown **once** — give it to the user securely. Users must change it at first sign-in.
* **Suspend / activate:** suspension (with a reason) immediately blocks sign-in and ends active sessions on the next request. Use suspension instead of deletion when someone leaves.
* **Reset password:** generates a new temporary password and forces a change.
* Only a Super Administrator can grant or remove the Super Administrator role or edit a Super Administrator. Nobody can change their own roles or suspend themselves.

## Roles & permissions

Ten default roles are provided (see `docs/03-roles-permissions.md`). You can edit a role's permission matrix or create a custom role. The Super Administrator role always has everything and cannot be edited. Changes are audit-logged.

Note: permissions open a module; record rules still apply (e.g. nobody approves their own request).

## Access reviews

*Access reviews → Review sheet* lists every active account with roles, last sign-in, MFA status and warning flags (no sign-in for 90 days, Super Administrator). Export it as CSV, review with management, make changes, then *Record review* with findings and the next due date. A banner warns when the last review is older than the configured interval (default 90 days).

## Audit logs

*Audit logs* shows who changed what and when (with old and new values), approvals, downloads, exports and settings changes. *Login activity* shows sign-ins, failures, lockouts and MFA failures. Logs cannot be edited or deleted from the application.

## System health and backups

* *System health* shows database, storage, scheduler (cron), queue, last backup, HTTPS/debug/secure-cookie checks and recent errors. Anything marked *Needs attention* should be fixed by the hosting team.
* *Backups* lists automatic backups (daily) and lets you record backups made elsewhere and **restoration tests**. Perform and record a restoration test at least quarterly (procedure: `docs/07-backup-restore.md`).

## Notifications and email

In-app notifications work without configuration. To also send email, the hosting team configures SMTP in `.env`, then enable *Settings → Notifications → Also send notifications by email*. Reminder timing (deadlines, meetings, expiry) is set on the same page.

## Language

Each user chooses English or Soomaali from the top bar; the choice is remembered. *Settings → Language & formats* sets the default for new users and the date/currency format.
