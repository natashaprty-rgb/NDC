# NDC-SMS — Initial Assessment and Implementation Plan

_Document status: Phase 1 output. Written 2026-10-18 after inspecting the repository and the development container._

## 1. Current project condition

| Item | Finding |
|---|---|
| Repository | `natashaprty-rgb/NDC`, one commit containing only a two-line `README.md`. No application code, no prior schema, nothing to preserve. |
| Approved branding | None supplied. A neutral, clearly replaceable placeholder mark ("NDC" text badge) is used. See `public/assets/img/README.md`. |
| Official data | None supplied. No staff, project, budget or asset data has been invented. Seeders create **only** roles, permissions, reference categories and one bootstrap administrator account; optional demo data is gated behind `--class=DemoDataSeeder` and is clearly labelled `[DEMO]`. |

## 2. Available tools (development container)

| Tool | Version | Notes |
|---|---|---|
| OS | Ubuntu 24.04.5 LTS | |
| PHP | 8.3.6 (CLI) | Extensions present: pdo_mysql, mbstring, openssl, fileinfo, intl, gd, zip, xml, curl, sodium, redis. **`bcmath` is NOT installed** (see money handling below). |
| Composer | 2.8.12 | GitHub dist downloads are blocked by the container's network policy; packages were installed with `--prefer-source` (git clone). This does not affect the application. |
| MySQL | 8.0.46 | Not pre-installed; installed from Ubuntu repositories for local development and testing. |
| Node | 22 | Not required. The UI uses plain CSS and vanilla JavaScript served from `public/assets` — no build step. |

**Selected framework: Laravel 12.x** — supports PHP 8.2–8.4, so it runs on current XAMPP builds (PHP 8.2) and on Linux production servers with PHP 8.2+.

## 3. Missing dependencies / blockers

1. No approved NDC logo, colours or official organisation name formatting — placeholders used.
2. No SMTP server details — mail defaults to the `log` driver; configuration is documented.
3. Somali translations were written by the developer and **must be reviewed by a native Somali speaker** before production use.
4. `bcmath` is not available, so money arithmetic is done with integer minor units (cents) in PHP (`App\Support\Money`) and with `DECIMAL(15,2)` sums in MySQL. No floats are used for money.
5. Multi-factor authentication is implemented as optional TOTP (RFC 6238) without a QR-code library: users enter the secret key manually in an authenticator app. Enforcing MFA for all users is an organisational decision (setting `security.mfa_required_roles`).

## 4. Recommended architecture

Modular monolith (single Laravel application, single MySQL database):

```
app/
  Http/Controllers/<Module>/...      thin controllers
  Http/Requests/<Module>/...         validation (FormRequest)
  Models/                            Eloquent models
  Policies/                          record-level authorisation (IDOR protection)
  Services/                          multi-step operations in DB transactions
  Support/                           Money, ReferenceNumber, Settings helpers
  Notifications/                     queued notifications (database + mail)
  Console/Commands/                  reminders, backups, overdue detection
routes/web.php + routes/modules/*.php  one route file per module
lang/en, lang/so                     one translation file per module
resources/views/<module>/...         Blade views + components/
```

Cross-cutting rules applied to every module:

* **Authorisation:** route middleware `can:<permission>` for coarse access + a Policy per model for record-level checks. Buttons are hidden in Blade with `@can`, but the server always re-checks.
* **Validation:** FormRequest classes; never `$request->all()` mass-assignment.
* **Audit:** `Auditable` trait writes `audit_logs` rows (old/new values, user, IP) for create/update/delete; sensitive fields are excluded.
* **Reference numbers:** `number_sequences` table, incremented inside a transaction with `SELECT … FOR UPDATE`, so concurrent requests cannot obtain the same number. Unique indexes are the last line of defence.
* **Files:** private `local` disk (`storage/app/private`), random storage names, extension + MIME (finfo) validation, size limit from settings, downloads only via an authorised controller action.
* **Money:** `DECIMAL(15,2)` columns, integer-cent arithmetic in PHP.
* **Future modules** (servers/racks, monitoring, DR, SIEM, service portal): not built. Extension points are documented in `docs/09-future-modules.md` (asset `specifications` JSON, asset categories, permission groups, module route files).

## 5. Implementation phases

| Phase | Content |
|---|---|
| 1 | This assessment, database design / ERD (`02-database-design.md`), roles & permissions matrix (`03-roles-permissions.md`), security & testing requirements. |
| 2 | Laravel skeleton, MySQL connectivity, auth (login, logout, reset, rate limiting, MFA, suspension), RBAC, departments, settings, audit log, layout and components, test harness. |
| 3 | Dashboard, staff, projects (+milestones, stakeholders, risks), tasks (+approval workflow), documents (+versions, access), correspondence, meetings. |
| 4 | Assets, suppliers & procurement, budgets & expenses, notifications & reminders, reports & exports, localisation completion. |
| 5 | Migrations on MySQL, full automated test run, permission boundary and file security tests, report verification, backup/restore test. |
| 6 | Deployment, operations, user manuals, known-issues report, handover. |

Progress against each phase is tracked in `docs/10-status-and-known-issues.md`.
