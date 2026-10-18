# Automated testing — coverage and results

## Execution record

| Item | Value |
|---|---|
| Date | 2026-10-18 |
| Command | `php artisan test` |
| Environment | PHP 8.3.6, Laravel 12.70.0, PHPUnit 11, MySQL 8.0.46 (isolated database `ndc_sms_test`, rebuilt per run) |
| Result | **244 tests passed, 0 failed, 0 skipped — 1,727 assertions** (≈30 s) |

The backup test runs a real `mysqldump`; it is skipped automatically on machines without `mysqldump`. It ran (and passed) in this run.

## Coverage by area

| Area | Test class | Tests | What is verified |
|---|---|---|---|
| Authentication & account security | `Foundation\AuthenticationTest` | 14 | sign-in/out, failure logging, lockout, suspension (incl. mid-session), reset without enumeration, password policy, forced change, MFA codes and recovery codes, encrypted MFA secret, security headers |
| Roles & permissions | `Foundation\AuthorizationTest` | 9 | matrix integrity, role grants, super-admin scope, auditor has no write permission, suspended users, 403 middleware, super-admin protection |
| Audit, localisation | `Foundation\AuditAndLocaleTest` | 8 | old/new values, redaction, append-only logs, language switching & persistence, EN/SO key parity, Somali month names |
| Reference numbers | `Foundation\ReferenceNumberTest` | 3 | sequence per type/year, settings-driven format, no duplicates |
| Dashboard | `Foundation\DashboardTest` | 3 | empty states, counts from DB, permission-hidden blocks |
| Money / MFA units | `Unit\MoneyTest`, `Unit\TotpTest` | 9 | exact cents arithmetic, rounding, RFC 6238 vectors |
| Departments / staff | `Organisation\*` | 12 | CRUD, validation, archive rules, sensitive fields, 403s |
| Users, roles, reviews, audit UI, settings, system, backups | `Admin\*` | 39 | super-admin/self-modification guards, role matrix edit, CSV export, read-only audit, settings validation, lookups, no secrets on health page, backup dump + checksum |
| Projects & tasks | `Projects\*` | 33 | CRUD, manager-only edit, archive, scoped child records, assignment notifications, no self-approval, rejection, visibility & scopes, overdue filter, derived progress |
| Documents | `Documents\DocumentTest` | 15 | upload type/content checks, private random storage, confidentiality, grants, approval rules, versions, audited download |
| Correspondence | `Correspondence\CorrespondenceTest` | 9 | registers, unique references, confidentiality, forwarding rights, overdue filter |
| Meetings | `Meetings\MeetingTest` | 11 | participants & notifications, visibility, minutes, action → task, calendar |
| Assets | `Assets\AssetTest` | 10 | unique serial/tag, movements, retirement lock, maintenance status, exact cost |
| Procurement | `Procurement\*` | 24 | exact totals, own-request visibility, segregation of duties, PO quantity caps, partial/full receipt, over-receipt, contract expiry; every page in EN and SO |
| Budgets & expenses | `Finance\*` | 22 | allocations, preparer ≠ approver, allocation cap to the cent, summary figures, settlement rules, totals |
| Reports | `Reports\ReportsTest` | 14 | permitted reports only, export permission, exact CSV rows, formula neutralisation, confidentiality, exact budget figures, PDF, audit, rate limit |
| Notifications | `Notifications\ReminderTest` | 7 | reminder de-duplication, meeting/expiry reminders, suspended users excluded, safe links, own notifications only, email channel toggle, delivery log |
| Security regressions | `Security\SecurityRegressionTest` | 2 | issues found in the independent security review (below) |

## Additional verification (not part of the automated suite)

* **Full UI crawl** (Playwright, 83 pages × English and Somali): as Super Administrator 156 pages returned 200 and 10 returned the expected 403 (editing locked records such as approved budgets), **no 500 errors**, no browser console errors other than those 403s, no duplicate element IDs and no untranslated keys. As General Staff: 42 pages allowed, 124 correctly refused.
  Found and fixed: `/assets` shadowed by the static folder; Carbon's Somali month list missing March; duplicate form IDs; mobile top bar overflow.
* **Independent security review** of all module code: 2 confirmed issues fixed with regression tests — (1) a user able to upload a new document version could then approve it; (2) the staff profile page showed tasks and assets the viewer was not allowed to see.
* **Backup → restore** performed and verified (see `07-backup-restore.md`).
* `composer audit`: no known vulnerabilities.

## Not covered / could not run

* Real SMTP delivery (mail is tested with Laravel's fake transport; production SMTP not available).
* Browser compatibility beyond Chromium; formal accessibility audit (WCAG) with assistive technology.
* Load / concurrency testing under production traffic (reference number locking is covered functionally, not under parallel load).
* Production deployment and HTTPS configuration (no production server was provided).
