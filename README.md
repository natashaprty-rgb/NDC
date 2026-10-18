# NDC Somalia Management System (NDC-SMS)

A secure, bilingual (English / Somali) institutional management platform for the National Data Center (NDC) Somalia, built with Laravel 12, Blade, MySQL 8, plain CSS and vanilla JavaScript.

Version 1 supports the organisation's **current administrative and project-management work**. It does not simulate data-centre infrastructure that is still under development; extension points for future modules are documented in [`docs/09-future-modules.md`](docs/09-future-modules.md).

## Modules

| # | Module | Highlights |
|---|---|---|
| 1 | Executive dashboard | Live, permission-scoped figures with links to filtered lists; empty states instead of invented numbers |
| 2 | Projects | Reference numbers, objectives, manager & department, milestones, stakeholders/contractors/consultants, risks & issues, derived progress, budget summary, attachments, history, printable report, archive |
| 3 | Staff & departments | Organisational units, staff directory and profiles, sensitive fields restricted |
| 4 | Tasks & workflow | Assignment, priorities, deadlines, progress, comments, evidence, reassignment, approval/rejection (no self-approval), overdue detection, notifications |
| 5 | Documents | Private versioned storage, MIME-verified uploads, confidentiality levels, access grants, review/approval, expiry & retention, audited downloads |
| 6 | Correspondence | Incoming/outgoing registers, concurrency-safe references, assignment, forwarding, follow-ups, deadlines, scans |
| 7 | Meetings & calendar | Scheduling, participants, agenda, minutes, attendance, decisions, action points → tasks, month calendar |
| 8 | Assets & inventory | Asset register, assignment/transfer history, maintenance, retirement, specifications for future data-centre equipment |
| 9 | Procurement & suppliers | Suppliers & contracts, purchase requests, approvals, quotations, purchase orders, goods received — with segregation of duties |
| 10 | Budgets & expenses | Allocations by category, exact decimal arithmetic, recorded vs approved vs paid vs reconciled, allocation limits (monitoring only — no payments) |
| 11 | Reports | 11 reports respecting permissions, CSV (formula-safe) and PDF export, audited |
| 12 | Notifications | In-app + optional email (queued), reminders for deadlines, meetings, expiries, approvals; delivery status |
| 13 | Users, roles & permissions | 52 granular permissions, 10 default roles, MFA (TOTP), suspension, access reviews, login activity |
| 14 | Settings & administration | Organisation, formats, numbering, upload policy, notifications, retention, categories, system health, backups, audit logs |

## Quick start (development)

```bash
composer install
cp .env.example .env && php artisan key:generate
# create MySQL database `ndc_sms` and set DB_* in .env
php artisan migrate --seed
php artisan serve
```

Sign in with `NDC_ADMIN_EMAIL` / `NDC_ADMIN_PASSWORD` from `.env` (or the generated password printed by the seeder). Optional demo data: `php artisan db:seed --class=DemoDataSeeder` (non-production only; all records are labelled `[DEMO]`).

Full instructions: [`docs/05-installation.md`](docs/05-installation.md).

## Tests

```bash
php artisan test
```

The suite runs against a separate MySQL database (`ndc_sms_test`). Results are recorded in [`docs/12-testing.md`](docs/12-testing.md).

## Documentation

| Document | Content |
|---|---|
| [01 Assessment & plan](docs/01-assessment-and-plan.md) | Environment, architecture, phases |
| [02 Database design](docs/02-database-design.md) | ERD, tables, business rules |
| [03 Roles & permissions](docs/03-roles-permissions.md) | Permission matrix and record-level rules |
| [04 Security](docs/04-security.md) | Controls and how they are verified |
| [05 Installation](docs/05-installation.md) | Local / XAMPP setup |
| [06 Deployment](docs/06-deployment.md) | Linux production, queue, cron, updates, rollback |
| [07 Backup & restore](docs/07-backup-restore.md) | Procedure and verification record |
| [08 Administrator guide](docs/08-user-guide-administrators.md) / [Staff guide](docs/08-user-guide-staff.md) | User manuals |
| [09 Future modules](docs/09-future-modules.md) | Extension points |
| [10 Status & known issues](docs/10-status-and-known-issues.md) | Deliverables status, limitations, outstanding work |
| [11 Endpoints](docs/11-endpoints.md) | All HTTP routes with permissions |
| [12 Testing](docs/12-testing.md) | Test coverage and execution results |
| [Module conventions](docs/dev/module-conventions.md) | How to build or change a module |

## Important notes

* **Branding:** no approved NDC logo was supplied; a neutral placeholder is used (`public/static/img/README.md`).
* **Somali translations** were prepared by the development team and must be reviewed by a native speaker before production use.
* **No official data** (staff, projects, budgets, contracts, equipment) is included.
