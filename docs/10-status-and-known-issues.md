# Delivery status, known issues and outstanding work

_Status as of 2026-10-18, branch `claude/eloquent-lamport-uozi95`._

## Deliverables

| # | Deliverable | Status |
|---|---|---|
| 1 | Source code for the agreed scope (14 modules) | **Complete** — committed and pushed |
| 2 | Functional Laravel application | **Complete & tested** in development (MySQL 8) |
| 3 | MySQL migrations and seeders | **Complete** — 15 migrations / 59 tables; production-safe seeder + `[DEMO]` seeder |
| 4 | ERD and schema documentation | **Complete** — `02-database-design.md` |
| 5 | Roles & permissions matrix | **Complete** — `03-roles-permissions.md` (generated from config) |
| 6 | English and Somali localisation | **Complete in code**; Somali text **requires native-speaker review** |
| 7 | Responsive user interface | **Complete** — checked at desktop and phone widths |
| 8 | Automated tests | **Complete** — 244 tests |
| 9 | Test execution results | **Complete** — `12-testing.md` (244 passed) |
| 10 | Installation & local development guide | **Complete** — `05-installation.md` (XAMPP notes included; not tested on Windows/XAMPP) |
| 11 | Production deployment guide | **Written; not executed** — no production server was available |
| 12 | Backup & restoration instructions | **Complete; verified on development data** — production restore test outstanding |
| 13 | Administrator & staff manuals | **Complete** — `08-user-guide-*.md` (English only) |
| 14 | Endpoint documentation | **Complete** — `11-endpoints.md` (web routes; no JSON API exists) |
| 15 | Known issues & outstanding work | This document |
| 16 | Source repository | **Complete** — GitHub `natashaprty-rgb/NDC` |
| 17 | Handover documentation | **Complete** — README + docs folder; handover review meeting still to be held |

## Known limitations

1. **Somali translations** were written by the development team. Terminology must be reviewed by a native Somali speaker (ideally NDC staff) before go-live. Somali is left-to-right, so no RTL layout was needed. Some rarely used validation rules fall back to English.
2. **Branding:** neutral placeholder logo/colours; replace with approved assets (`public/static/img/README.md`).
3. **MFA** is per-user and optional; there is no setting to force MFA for specific roles yet. Setup uses a manually entered key / `otpauth` URI (no QR code image).
4. **Email** delivery depends on SMTP configuration on the server; only the in-app channel is exercised end-to-end here.
5. **Excel export** is provided as UTF-8 CSV (opens in Excel); there is no native `.xlsx` export.
6. **Session timeout setting** in *Settings → Security* is informational; the effective value is `SESSION_LIFETIME` in `.env`.
7. **Budget module** is a monitoring tool, not an accounting ledger; multi-currency totals are shown per currency and never converted.
8. **High-trust permissions:** `users.manage` and `staff.manage` together let an administrator place their own account in any department (gaining that department's confidential documents). This is by design of the admin role; keep these permissions with System/Super Administrators and review them in access reviews.
9. **Retention:** retention periods are recorded and reported; nothing is deleted or anonymised automatically.
10. **Attachments** removed by users are soft-deleted; their files remain on disk for audit/retention and are not purged automatically.
11. Attachment *category* labels and some audit event names display with the shared status vocabulary; a few new technical events may appear in plain English if added later without translation.

## Outstanding work / recommended next steps

1. Native-speaker review of `lang/so/*`.
2. Provision staging and production servers; follow `06-deployment.md`; run the post-deployment checklist.
3. Configure SMTP and test email notifications.
4. Perform and record a backup restoration test on production infrastructure.
5. Supply approved branding; confirm the official organisation name and numbering prefixes.
6. Decide MFA policy (recommended: mandatory for administrators and directors) and, if required, add role-based MFA enforcement.
7. User acceptance testing with NDC staff for each module; adjust default role permissions to NDC's actual structure.
8. Accessibility review with screen-reader users; cross-browser checks (Firefox, Safari, Edge).
9. Load testing before organisation-wide rollout.
10. Future data-centre modules per `09-future-modules.md` once the facility is operational.
