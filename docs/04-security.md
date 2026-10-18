# Security design

| Requirement | Implementation | Verified by |
|---|---|---|
| Password hashing | bcrypt (Laravel `hashed` cast, 12 rounds). Policy: ≥10 chars, mixed case, number, symbol. | `AuthenticationTest` |
| Brute force | Per-account lockout after 5 failures for 5 minutes (`LoginController`) + IP limit 20/min; `throttle` on reset, MFA, uploads, exports. Lockouts are logged. | `AuthenticationTest::test_repeated_failures…` |
| Account enumeration | Password reset always returns the same message. | `AuthenticationTest` |
| MFA | Optional TOTP (RFC 6238) with single-use recovery codes; secrets encrypted at rest. | `TotpTest`, `AuthenticationTest` |
| Sessions | DB sessions, encrypted, 60-minute lifetime, expire on browser close, regenerated at login, other sessions logged out on password change; `SESSION_SECURE_COOKIE=true` in production. | config |
| Suspension | Suspended/archived users are signed out on their next request and hold no permissions. | `AuthenticationTest`, `AuthorizationTest` |
| Server-side authorisation | Route `can:` middleware **and** a Policy check in every controller action; buttons hidden in Blade are a convenience only. | every module test (403 cases) |
| IDOR | Record-level policies; lists use the same visibility scopes (`visibleTo`) as detail pages; child records use scoped route bindings. | module access tests |
| Segregation of duties | No self-approval for tasks, documents, purchase requests, budgets, expenses; separate receiver; settle ≠ approve. Applies to Super Administrators too. | workflow tests |
| CSRF | Laravel CSRF tokens on every POST/PUT/DELETE; logout is POST only. | framework, `AuthenticationTest` |
| XSS | Blade `{{ }}` escaping everywhere; Content-Security-Policy `script-src 'self'` (no inline scripts); no user HTML rendered. | CSP header test |
| SQL injection | Eloquent / query builder bindings only. | code review |
| File uploads | Extension allow-list (settings ∩ known MIME map) **and** content MIME detection (finfo); random UUID storage names; private disk outside `public/`; size limit; downloads only via authorised controllers with `nosniff`, `attachment` disposition (inline only for PDF/images/text) and a sandbox CSP. | `DocumentTest` |
| CSV injection | Exported cells starting with `= + - @` are neutralised. | `ReportsTest`, `PermissionReviewTest` |
| Open redirect | Notification links only follow relative in-app paths. | `ReminderTest` |
| Security headers | `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, CSP, HSTS on HTTPS, `no-store` for authenticated pages. | `AuthenticationTest` |
| Audit | Append-only `audit_logs` (create/update/delete, approvals, downloads, exports, role and settings changes, login activity). Passwords/MFA secrets redacted. | `AuditAndLocaleTest`, `AuditLogTest` |
| Secrets | Only in `.env` (git-ignored). System health page shows no secrets. Seeded admin password comes from `.env` or is generated. | `SystemAndBackupTest` |
| Error disclosure | `APP_DEBUG=false` in production → generic error pages; system log view strips paths/context. | System health check |
| Least privilege | Dedicated MySQL user limited to the application schema; ten default roles; auditor role has no write permission. | `AuthorizationTest` |
| Dependencies | `composer audit` — no advisories at delivery (2026-10-18). Run on every update. | — |

## Operational recommendations

1. Enforce MFA for Super/System Administrators and Directors (organisational policy; the system supports it per user).
2. Keep the number of Super Administrator accounts to two named people; use *Access reviews* every 90 days.
3. Terminate TLS with modern ciphers only; redirect HTTP to HTTPS.
4. Store backups encrypted and off-server; test restoration quarterly and record it.
5. Review *Audit logs → Login activity* for repeated failures and lockouts.
6. Apply OS, PHP, MySQL and Composer security updates monthly.
