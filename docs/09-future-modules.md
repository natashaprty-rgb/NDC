# Extending NDC-SMS for future data-centre modules

Version 1 deliberately contains **no** placeholder screens for infrastructure that does not yet exist
(server racks, monitoring, power/cooling, virtualisation, DR, SIEM, service portal, provisioning).
The following extension points let those modules be added without rebuilding the system.

| Future module | Extension point already in place |
|---|---|
| Server, rack, network and storage inventory | Asset categories with class `data_centre`; `assets.specifications` JSON holds technical attributes (rack unit, power draw, IPs…). A dedicated `racks`/`rack_units` table can reference `assets.id`. Asset movements and maintenance history already apply. |
| Network / power / cooling monitoring | Add a module under `app/Http/Controllers/Infrastructure`, its route file `routes/modules/infrastructure.php` (auto-loaded) and a permission group in `config/ndc_permissions.php` (e.g. `monitoring.view`). Ingest metrics through a queued job; store time-series in a separate table or external TSDB and only summaries in MySQL. |
| Backup & disaster recovery management | `backup_records` already records backups and restoration tests; extend with DR site and RPO/RTO fields. |
| Cybersecurity / SIEM integration | `audit_logs` and `login_activities` are structured and can be forwarded (syslog/HTTP) by a scheduled command; Laravel logging channels can ship to a SIEM. |
| Government institution service portal | Add a separate guard/user type for institution users; reuse `Notifier`, `FileStorage`, reference numbering and approvals. |
| Service provisioning & usage tracking | Model services as catalogue items linked to institutions and budgets; reuse procurement and expense tables for cost tracking. |

## How to add a module (checklist)

1. Migration(s) with foreign keys, indexes and userstamps (`$table->userstamps()`).
2. Model using `Auditable`, `HasUserstamps` (and `HasAttachments` if files are needed); add a morph alias in `AppServiceProvider`.
3. Permissions in `config/ndc_permissions.php`; re-run `RolesAndPermissionsSeeder` (additive).
4. Policy in `app/Policies`, FormRequests, controller, `routes/modules/<module>.php`.
5. Views using the shared components; `lang/en/<module>.php` and `lang/so/<module>.php` with identical keys.
6. Navigation entry in `app/Support/Navigation.php` (hidden automatically until the route exists and the user has the permission).
7. Feature tests covering CRUD, validation, 403 for unauthorised users and visibility of lists.
8. Optional: a report class in `app/Reports` registered in `ReportRegistry`.

Follow `docs/dev/module-conventions.md`.
