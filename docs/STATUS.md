# Version 1 Status: Features, Test Results and Known Limitations

Status of the NDC Somalia Management System against the *System Requirements
& Scope of Work, Version 1.0*. Per section 13 of the SRS, nothing below is
described as completed or tested unless it was implemented and verified.

**Overall:** the 14 Version 1 modules are implemented with automated tests.
The work covers development phases 2–5 and the automated part of phase 6.
User acceptance testing, independent security testing and production
deployment have **not** been done yet (see *Outstanding before go-live*).

## Completed features

| SRS | Module | Implemented |
| --- | --- | --- |
| 3.1 | Dashboard | Figures per module counted live from the database and limited to what the user may see; projects by status; active projects with progress; overdue tasks; the user's own tasks; meetings and deadlines for the next 14 days; budget position; pending-approval count; notifications; recent activity |
| 3.2 | Projects | Create, edit, archive, restore and delete; objectives, location, dates, manager, department; contractors, consultants and contacts; team members; milestones and deliverables; risks, challenges and delays with corrective actions; documents and evidence; planned budget against recorded spending; printable progress report |
| 3.3 | Staff & departments | Staff profiles (sensitive fields encrypted and visible only with permission), directory with filters, printable directory, training records, departments with heads and hierarchy, link to user accounts |
| 3.4 | Tasks & workflow | Assign to a person or department; priority, deadline, deliverable; progress; comments and completion evidence; submit for review, approve, return or reassign; overdue detection; notifications; full change history |
| 3.5 | Documents | Register with automatic reference numbers; types, department, project, four confidentiality levels with per-user grants; versions with checksums; approval, then publish, then archive; review and expiry dates; access and download log; printable register |
| 3.6 | Correspondence | Incoming and outgoing registers with automatic `NDC/IN` and `NDC/OUT` numbers; scanned copies; assign, forward, respond (linked reply), mark sent, close; response deadlines and overdue tracking; follow-up timeline; confidential letters |
| 3.7 | Meetings & calendar | Meetings, appointments and events; internal and external participants; agenda and attachments; minutes, decisions and action points with assignees and deadlines; attendance; outstanding action points page; month calendar; printable minutes |
| 3.8 | Assets | Register with asset tags; serial number, model, supplier, warranty; assign, return and transfer with history; maintenance from scheduling to completion; disposal and retirement approval; technical specifications for future data center equipment; printable inventory and allocation reports |
| 3.9 | Procurement & suppliers | Suppliers and contractors with performance ratings; purchase requests with line items and approval; quotations; purchase orders; goods receipts by item with outstanding quantities; status from request to delivery; printable registers. No automatic approval or payment |
| 3.10 | Budgets & expenses | Budgets by project, department or funding source with approval; planned amounts by category; expenses against approved budgets with receipts and approval; remaining balance and percentage used; planned vs actual; over-budget warning; printable summaries |
| 3.11 | Reports | 11 reports (projects, milestones, task completion, documents, correspondence, meeting actions, assets, procurement, budgets, pending/overdue, user activity) with filters, print, **PDF** and **CSV (Excel)** export, all respecting permissions and record visibility |
| 3.12 | Notifications | In-app inbox; e-mail (switch in Settings); notifications for assignments, approvals and decisions; daily reminders for deadlines, overdue items, meetings, document review/expiry, contract renewal, warranty and maintenance |
| 3.13 | Users & access | 10 default roles and 58 permissions, editable in the interface; create, activate, suspend and deactivate accounts; password policy; optional TOTP two-factor authentication (can be required for administrators); throttled sign-in; password reset; session list and sign-out of other sessions; audit log of sign-ins and changes; separation of duties in approvals |
| 3.14 | Administration | Departments, reference lists, settings (language, dates, currency, notifications, upload limits, retention, security), audit log viewer and export, system health (database, storage, scheduler, queue, backups, environment), backups with verification |
| 4 | Language & UI | English and Somali throughout, chosen per user and on the sign-in page; responsive layout for desktop and mobile; NDC branding; search, filters, sorting and pagination; status badges; accessible forms; right-to-left support available through configuration |
| 5 | Technical | Laravel 13, MySQL, HTML5/CSS3/JavaScript with no build step; modular structure; server-side validation and authorisation; read-only token API; queued notifications |
| 6 | Security | See ARCHITECTURE.md › Security measures |
| 7 | Database | Migrations for every table, with foreign keys, indexes and soft deletes; seeders for permissions, roles and reference lists; optional demo data |
| 8 | Backup | Daily verified backup of the database and files, failure notifications, restore-test command, retention, documented restore procedure |

## Test results

Run on 20 October 2026 with `php artisan test`:

| Area | Tests |
| --- | ---: |
| Core (authentication, administration, reminders, backups, translation coverage) | 34 |
| Projects | 26 |
| Tasks & workflow | 26 |
| Budgets & expenses | 25 |
| Documents | 20 |
| Procurement & suppliers | 17 |
| Assets | 16 |
| Staff | 15 |
| Correspondence | 14 |
| Meetings | 12 |
| API | 9 |
| Reports & exports | 8 |
| Unit | 2 |
| **Total** | **224 passed, 0 failed (1,658 assertions)** |

What the tests cover: sign-in, throttling, two-factor and password rules;
authorisation per role (allowed and forbidden); record-level visibility
(confidential documents and correspondence, other departments' tasks);
every approval workflow, including that requesters cannot approve their own
requests; upload validation, rejection of executable files, and that downloads
are authorised; generation and uniqueness of reference numbers; status changes;
budget arithmetic; report contents and exports, including protection against
CSV injection; API permissions and that sensitive staff fields are never
returned; Somali translation of every interface string.

Other verification:

- **MySQL**: the whole suite also passes on MariaDB 10.11 (223 passed, 1
  SQLite-only test skipped). That run found two MySQL-only SQL errors, which
  were fixed. CI (`.github/workflows/tests.yml`) runs the suite on SQLite and
  MySQL 8, along with code-style checks and `composer audit`.
- **Backup and restore**: tested end-to-end on SQLite and on MariaDB. The
  dump is created, the archive is verified, and the restore test loaded it
  into a scratch database and restored 57 tables.
- **Dependency scan**: `composer audit` reported no known vulnerabilities.
- **Interface**: every main page was opened in a headless browser in English
  and Somali, at desktop and phone width, with no JavaScript errors.

## Outstanding before go-live (SRS phases 1 and 6)

1. **Requirements confirmation**: the SRS's final requirement is that the
   authorised NDC representative confirms scope, architecture, phases,
   deliverables, testing and acceptance criteria. This build should be
   reviewed on that basis.
2. **Somali language review**: the Somali text was produced during
   development and has been checked for completeness and placeholders, not by
   a native-speaker editor. NDC staff should review the files in
   `lang/modules/*/so.json` and `lang/so/*.php`.
3. **User acceptance testing** with designated NDC staff for each role (use
   `DemoDataSeeder` on a test server).
4. **Independent security testing** (penetration test) of the deployed test
   environment.
5. **Roles and permissions** confirmed against NDC's approved organisational
   structure. The defaults in `config/ndc.php` are a starting point.
6. **Production infrastructure**: HTTPS certificate, SMTP server, off-server
   backup storage, scheduler and queue worker (INSTALLATION.md).
7. **Performance and availability targets** agreed (SRS 10). No load testing
   has been done.
8. **Handover and training** of designated NDC personnel.

## Known limitations

- **E-mail delivery** has only been tested with Laravel's test mailer, not a
  real SMTP server.
- **Documents**: uploading a new version of an approved or published
  document keeps its status; re-approval is not forced. The expiry date also
  serves as the renewal date. Archiving replaces deletion, so documents are
  never permanently deleted from the interface.
- **Retention settings** cover audit logs, read notifications and backup
  archives. Records (documents, letters and so on) are kept indefinitely and
  are never deleted automatically.
- **Budgets**: a closed budget cannot be reopened. The dashboard adds amounts
  across currencies; budget pages and reports show each currency separately.
- **Procurement**: one active purchase order per request.
- **Assets**: no barcode or QR label printing. On MySQL, technical specifications may be displayed in a different order from the one entered, because MySQL's JSON column type re-sorts keys.
- **Exports**: module print views are browser-printable HTML. PDF and CSV
  files come from the central Reports module.
- **API** is read-only in Version 1.
- **SMS and messaging apps** are not included, as allowed by SRS 3.12.
- **Future modules** (SRS 9, phase 7: data center infrastructure, racks,
  monitoring, SIEM, cloud, MDA portal, accounting integration) are not built.
  The architecture leaves room for them (ARCHITECTURE.md › Future expansion).
  No equipment, infrastructure or services are invented in the data.
