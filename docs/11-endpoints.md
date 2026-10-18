# HTTP endpoint reference

NDC-SMS is a server-rendered web application; all endpoints below return HTML pages or redirects (exports return CSV/PDF, downloads return files). There is no public JSON API in Version 1.

All endpoints except sign-in, password reset, language switch and `/up` require an authenticated, active session. State-changing requests (POST/PUT/DELETE) require the CSRF token that every form includes. *Permission* lists the route-level permission middleware; record-level policy checks are additionally applied inside controllers (see `03-roles-permissions.md`).

Regenerate with `php docs/dev/generate-endpoints.php` (or inspect `php artisan route:list`).

## //

| Method | URI | Name | Permission | Rate limit |
|---|---|---|---|---|
| GET/POST/PUT/PATCH/DELETE/OPTIONS | `/` |  | — |  |

## /account

| Method | URI | Name | Permission | Rate limit |
|---|---|---|---|---|
| POST | `/account/mfa` | `profile.mfa.enable` | — | sensitive |
| DELETE | `/account/mfa` | `profile.mfa.disable` | — | sensitive |
| POST | `/account/mfa/start` | `profile.mfa.start` | — |  |
| GET | `/account/password` | `password.change` | — |  |
| PUT | `/account/password` | `password.change.update` | — | sensitive |
| PUT | `/account/preferences` | `profile.preferences` | — |  |
| GET | `/account/profile` | `profile.show` | — |  |

## /admin/audit

| Method | URI | Name | Permission | Rate limit |
|---|---|---|---|---|
| GET | `/admin/audit` | `admin.audit.index` | `audit.view` |  |
| GET | `/admin/audit/logins` | `admin.audit.logins` | `audit.view` |  |

## /admin/backups

| Method | URI | Name | Permission | Rate limit |
|---|---|---|---|---|
| GET | `/admin/backups` | `admin.backups.index` | `backups.manage` |  |
| POST | `/admin/backups` | `admin.backups.store` | `backups.manage` |  |
| PUT | `/admin/backups/{backup}/restore-test` | `admin.backups.restore-test` | `backups.manage` |  |

## /admin/lookups

| Method | URI | Name | Permission | Rate limit |
|---|---|---|---|---|
| GET | `/admin/lookups/{type}` | `admin.lookups.index` | `settings.manage` |  |
| POST | `/admin/lookups/{type}` | `admin.lookups.store` | `settings.manage` |  |
| GET | `/admin/lookups/{type}/create` | `admin.lookups.create` | `settings.manage` |  |
| PUT | `/admin/lookups/{type}/{id}` | `admin.lookups.update` | `settings.manage` |  |
| GET | `/admin/lookups/{type}/{id}/edit` | `admin.lookups.edit` | `settings.manage` |  |

## /admin/permission-reviews

| Method | URI | Name | Permission | Rate limit |
|---|---|---|---|---|
| GET | `/admin/permission-reviews` | `admin.permission-reviews.index` | `permissions.review` |  |
| POST | `/admin/permission-reviews` | `admin.permission-reviews.store` | `permissions.review` |  |
| GET | `/admin/permission-reviews/create` | `admin.permission-reviews.create` | `permissions.review` |  |
| GET | `/admin/permission-reviews/sheet` | `admin.permission-reviews.sheet` | `permissions.review` |  |
| GET | `/admin/permission-reviews/sheet.csv` | `admin.permission-reviews.export` | `permissions.review` | exports |

## /admin/roles

| Method | URI | Name | Permission | Rate limit |
|---|---|---|---|---|
| GET | `/admin/roles` | `admin.roles.index` | — |  |
| POST | `/admin/roles` | `admin.roles.store` | — |  |
| GET | `/admin/roles/create` | `admin.roles.create` | — |  |
| GET | `/admin/roles/{role}` | `admin.roles.show` | — |  |
| PUT/PATCH | `/admin/roles/{role}` | `admin.roles.update` | — |  |
| DELETE | `/admin/roles/{role}` | `admin.roles.destroy` | — |  |
| GET | `/admin/roles/{role}/edit` | `admin.roles.edit` | — |  |

## /admin/settings

| Method | URI | Name | Permission | Rate limit |
|---|---|---|---|---|
| GET | `/admin/settings` | `admin.settings.edit` | `settings.manage` |  |
| PUT | `/admin/settings/{group}` | `admin.settings.update` | `settings.manage` |  |

## /admin/system

| Method | URI | Name | Permission | Rate limit |
|---|---|---|---|---|
| GET | `/admin/system` | `admin.system.index` | `system.health` |  |

## /admin/users

| Method | URI | Name | Permission | Rate limit |
|---|---|---|---|---|
| GET | `/admin/users` | `admin.users.index` | `users.view` |  |
| POST | `/admin/users` | `admin.users.store` | `users.view` |  |
| GET | `/admin/users/create` | `admin.users.create` | `users.view` |  |
| GET | `/admin/users/{user}` | `admin.users.show` | `users.view` |  |
| PUT/PATCH | `/admin/users/{user}` | `admin.users.update` | `users.view` |  |
| DELETE | `/admin/users/{user}` | `admin.users.destroy` | `users.view` |  |
| POST | `/admin/users/{user}/activate` | `admin.users.activate` | `users.view` |  |
| GET | `/admin/users/{user}/edit` | `admin.users.edit` | `users.view` |  |
| POST | `/admin/users/{user}/reset-password` | `admin.users.reset-password` | `users.view` | sensitive |
| POST | `/admin/users/{user}/suspend` | `admin.users.suspend` | `users.view` |  |

## /approvals

| Method | URI | Name | Permission | Rate limit |
|---|---|---|---|---|
| GET | `/approvals` | `approvals.index` | — |  |

## /assets

| Method | URI | Name | Permission | Rate limit |
|---|---|---|---|---|
| GET | `/assets` | `assets.index` | `assets.view` |  |
| POST | `/assets` | `assets.store` | `assets.view` |  |
| GET | `/assets/create` | `assets.create` | `assets.view` |  |
| GET | `/assets/{asset}` | `assets.show` | `assets.view` |  |
| PUT/PATCH | `/assets/{asset}` | `assets.update` | `assets.view` |  |
| DELETE | `/assets/{asset}` | `assets.destroy` | `assets.view` |  |
| GET | `/assets/{asset}/edit` | `assets.edit` | `assets.view` |  |
| POST | `/assets/{asset}/maintenance` | `assets.maintenance.store` | `assets.view` |  |
| PUT | `/assets/{asset}/maintenance/{maintenance}` | `assets.maintenance.update` | `assets.view` |  |
| POST | `/assets/{asset}/movements` | `assets.movements.store` | `assets.view` |  |
| POST | `/assets/{asset}/retire` | `assets.retire` | `assets.view` |  |

## /attachments

| Method | URI | Name | Permission | Rate limit |
|---|---|---|---|---|
| GET | `/attachments/{attachment}` | `attachments.download` | — |  |
| DELETE | `/attachments/{attachment}` | `attachments.destroy` | — |  |
| POST | `/attachments/{type}/{id}` | `attachments.store` | — | uploads |

## /budgets

| Method | URI | Name | Permission | Rate limit |
|---|---|---|---|---|
| GET | `/budgets` | `budgets.index` | `budgets.view` |  |
| POST | `/budgets` | `budgets.store` | `budgets.view` |  |
| GET | `/budgets/create` | `budgets.create` | `budgets.view` |  |
| GET | `/budgets/{budget}` | `budgets.show` | `budgets.view` |  |
| PUT/PATCH | `/budgets/{budget}` | `budgets.update` | `budgets.view` |  |
| DELETE | `/budgets/{budget}` | `budgets.destroy` | `budgets.view` |  |
| POST | `/budgets/{budget}/allocations` | `budgets.allocations.store` | `budgets.view` |  |
| PUT | `/budgets/{budget}/allocations/{allocation}` | `budgets.allocations.update` | `budgets.view` |  |
| DELETE | `/budgets/{budget}/allocations/{allocation}` | `budgets.allocations.destroy` | `budgets.view` |  |
| POST | `/budgets/{budget}/approve` | `budgets.approve` | `budgets.view` |  |
| POST | `/budgets/{budget}/close` | `budgets.close` | `budgets.view` |  |
| GET | `/budgets/{budget}/edit` | `budgets.edit` | `budgets.view` |  |
| POST | `/budgets/{budget}/reject` | `budgets.reject` | `budgets.view` |  |
| POST | `/budgets/{budget}/submit` | `budgets.submit` | `budgets.view` |  |

## /contracts

| Method | URI | Name | Permission | Rate limit |
|---|---|---|---|---|
| GET | `/contracts` | `contracts.index` | `suppliers.view` |  |
| GET | `/contracts/{contract}` | `contracts.show` | `suppliers.view` |  |
| PUT/PATCH | `/contracts/{contract}` | `contracts.update` | `suppliers.view` |  |
| DELETE | `/contracts/{contract}` | `contracts.destroy` | `suppliers.view` |  |
| GET | `/contracts/{contract}/edit` | `contracts.edit` | `suppliers.view` |  |

## /correspondence

| Method | URI | Name | Permission | Rate limit |
|---|---|---|---|---|
| GET | `/correspondence` | `correspondence.index` | `correspondence.view` |  |
| POST | `/correspondence` | `correspondence.store` | `correspondence.view` |  |
| GET | `/correspondence/create` | `correspondence.create` | `correspondence.view` |  |
| GET | `/correspondence/{correspondence}` | `correspondence.show` | `correspondence.view` |  |
| PUT/PATCH | `/correspondence/{correspondence}` | `correspondence.update` | `correspondence.view` |  |
| DELETE | `/correspondence/{correspondence}` | `correspondence.destroy` | `correspondence.view` |  |
| GET | `/correspondence/{correspondence}/edit` | `correspondence.edit` | `correspondence.view` |  |
| POST | `/correspondence/{correspondence}/follow-up` | `correspondence.follow-up` | `correspondence.view` |  |
| POST | `/correspondence/{correspondence}/forward` | `correspondence.forward` | `correspondence.view` |  |
| POST | `/correspondence/{correspondence}/status` | `correspondence.status` | `correspondence.view` |  |

## /dashboard

| Method | URI | Name | Permission | Rate limit |
|---|---|---|---|---|
| GET | `/dashboard` | `dashboard` | `dashboard.view` |  |

## /departments

| Method | URI | Name | Permission | Rate limit |
|---|---|---|---|---|
| GET | `/departments` | `departments.index` | `departments.view` |  |
| POST | `/departments` | `departments.store` | `departments.view` |  |
| GET | `/departments/create` | `departments.create` | `departments.view` |  |
| GET | `/departments/{department}` | `departments.show` | `departments.view` |  |
| PUT/PATCH | `/departments/{department}` | `departments.update` | `departments.view` |  |
| DELETE | `/departments/{department}` | `departments.destroy` | `departments.view` |  |
| GET | `/departments/{department}/edit` | `departments.edit` | `departments.view` |  |

## /documents

| Method | URI | Name | Permission | Rate limit |
|---|---|---|---|---|
| GET | `/documents` | `documents.index` | `documents.view` |  |
| POST | `/documents` | `documents.store` | `documents.view` |  |
| GET | `/documents/create` | `documents.create` | `documents.view` |  |
| GET | `/documents/{document}` | `documents.show` | `documents.view` |  |
| PUT/PATCH | `/documents/{document}` | `documents.update` | `documents.view` |  |
| POST | `/documents/{document}/access` | `documents.access.store` | `documents.view` |  |
| DELETE | `/documents/{document}/access/{accessGrant}` | `documents.access.destroy` | `documents.view` |  |
| POST | `/documents/{document}/approve` | `documents.approve` | `documents.view` |  |
| POST | `/documents/{document}/archive` | `documents.archive` | `documents.view` |  |
| GET | `/documents/{document}/edit` | `documents.edit` | `documents.view` |  |
| POST | `/documents/{document}/reject` | `documents.reject` | `documents.view` |  |
| POST | `/documents/{document}/restore` | `documents.restore` | `documents.view` |  |
| POST | `/documents/{document}/submit` | `documents.submit` | `documents.view` |  |
| POST | `/documents/{document}/versions` | `documents.versions.store` | `documents.view` | uploads |
| GET | `/documents/{document}/versions/{version}/download` | `documents.versions.download` | `documents.view` |  |

## /expenses

| Method | URI | Name | Permission | Rate limit |
|---|---|---|---|---|
| GET | `/expenses` | `expenses.index` | `expenses.view` |  |
| POST | `/expenses` | `expenses.store` | `expenses.view` |  |
| GET | `/expenses/create` | `expenses.create` | `expenses.view` |  |
| GET | `/expenses/{expense}` | `expenses.show` | `expenses.view` |  |
| PUT/PATCH | `/expenses/{expense}` | `expenses.update` | `expenses.view` |  |
| DELETE | `/expenses/{expense}` | `expenses.destroy` | `expenses.view` |  |
| POST | `/expenses/{expense}/approve` | `expenses.approve` | `expenses.view` |  |
| GET | `/expenses/{expense}/edit` | `expenses.edit` | `expenses.view` |  |
| POST | `/expenses/{expense}/paid` | `expenses.paid` | `expenses.view` |  |
| POST | `/expenses/{expense}/reconcile` | `expenses.reconcile` | `expenses.view` |  |
| POST | `/expenses/{expense}/reject` | `expenses.reject` | `expenses.view` |  |

## /forgot-password

| Method | URI | Name | Permission | Rate limit |
|---|---|---|---|---|
| GET | `/forgot-password` | `password.request` | — |  |
| POST | `/forgot-password` | `password.email` | — | sensitive |

## /locale

| Method | URI | Name | Permission | Rate limit |
|---|---|---|---|---|
| POST | `/locale` | `locale.switch` | — |  |

## /login

| Method | URI | Name | Permission | Rate limit |
|---|---|---|---|---|
| GET | `/login` | `login` | — |  |
| POST | `/login` |  | — | login |
| GET | `/login/verify` | `mfa.challenge` | — |  |
| POST | `/login/verify` | `mfa.verify` | — | login |

## /logout

| Method | URI | Name | Permission | Rate limit |
|---|---|---|---|---|
| POST | `/logout` | `logout` | — |  |

## /meetings

| Method | URI | Name | Permission | Rate limit |
|---|---|---|---|---|
| GET | `/meetings` | `meetings.index` | — |  |
| POST | `/meetings` | `meetings.store` | — |  |
| GET | `/meetings/calendar` | `meetings.calendar` | — |  |
| GET | `/meetings/create` | `meetings.create` | — |  |
| GET | `/meetings/{meeting}` | `meetings.show` | — |  |
| PUT/PATCH | `/meetings/{meeting}` | `meetings.update` | — |  |
| DELETE | `/meetings/{meeting}` | `meetings.destroy` | — |  |
| GET | `/meetings/{meeting}/edit` | `meetings.edit` | — |  |
| POST | `/meetings/{meeting}/items` | `meetings.items.store` | — |  |
| PUT | `/meetings/{meeting}/items/{item}` | `meetings.items.update` | — |  |
| POST | `/meetings/{meeting}/items/{item}/task` | `meetings.items.task` | — |  |
| GET | `/meetings/{meeting}/minutes` | `meetings.minutes.edit` | — |  |
| PUT | `/meetings/{meeting}/minutes` | `meetings.minutes.update` | — |  |
| POST | `/meetings/{meeting}/status` | `meetings.status` | — |  |

## /notifications

| Method | URI | Name | Permission | Rate limit |
|---|---|---|---|---|
| GET | `/notifications` | `notifications.index` | — |  |
| POST | `/notifications/read-all` | `notifications.read-all` | — |  |
| GET | `/notifications/{id}/open` | `notifications.open` | — |  |

## /projects

| Method | URI | Name | Permission | Rate limit |
|---|---|---|---|---|
| GET | `/projects` | `projects.index` | `projects.view` |  |
| POST | `/projects` | `projects.store` | `projects.view` |  |
| GET | `/projects/create` | `projects.create` | `projects.view` |  |
| GET | `/projects/{project}` | `projects.show` | `projects.view` |  |
| PUT/PATCH | `/projects/{project}` | `projects.update` | `projects.view` |  |
| POST | `/projects/{project}/archive` | `projects.archive` | `projects.view` |  |
| GET | `/projects/{project}/edit` | `projects.edit` | `projects.view` |  |
| POST | `/projects/{project}/milestones` | `projects.milestones.store` | `projects.view` |  |
| PUT | `/projects/{project}/milestones/{milestone}` | `projects.milestones.update` | `projects.view` |  |
| DELETE | `/projects/{project}/milestones/{milestone}` | `projects.milestones.destroy` | `projects.view` |  |
| GET | `/projects/{project}/report` | `projects.report` | `projects.view` |  |
| POST | `/projects/{project}/restore` | `projects.restore` | `projects.view` |  |
| POST | `/projects/{project}/risks` | `projects.risks.store` | `projects.view` |  |
| PUT | `/projects/{project}/risks/{risk}` | `projects.risks.update` | `projects.view` |  |
| DELETE | `/projects/{project}/risks/{risk}` | `projects.risks.destroy` | `projects.view` |  |
| POST | `/projects/{project}/stakeholders` | `projects.stakeholders.store` | `projects.view` |  |
| PUT | `/projects/{project}/stakeholders/{stakeholder}` | `projects.stakeholders.update` | `projects.view` |  |
| DELETE | `/projects/{project}/stakeholders/{stakeholder}` | `projects.stakeholders.destroy` | `projects.view` |  |

## /purchase-orders

| Method | URI | Name | Permission | Rate limit |
|---|---|---|---|---|
| GET | `/purchase-orders` | `purchase-orders.index` | `procurement.view` |  |
| GET | `/purchase-orders/{purchase_order}` | `purchase-orders.show` | `procurement.view` |  |
| POST | `/purchase-orders/{purchase_order}/cancel` | `purchase-orders.cancel` | `procurement.view` |  |
| POST | `/purchase-orders/{purchase_order}/receipts` | `purchase-orders.receipts.store` | `procurement.view` | uploads |

## /purchase-requests

| Method | URI | Name | Permission | Rate limit |
|---|---|---|---|---|
| GET | `/purchase-requests` | `purchase-requests.index` | `procurement.view` |  |
| POST | `/purchase-requests` | `purchase-requests.store` | `procurement.view` |  |
| GET | `/purchase-requests/create` | `purchase-requests.create` | `procurement.view` |  |
| GET | `/purchase-requests/{purchase_request}` | `purchase-requests.show` | `procurement.view` |  |
| PUT/PATCH | `/purchase-requests/{purchase_request}` | `purchase-requests.update` | `procurement.view` |  |
| DELETE | `/purchase-requests/{purchase_request}` | `purchase-requests.destroy` | `procurement.view` |  |
| POST | `/purchase-requests/{purchase_request}/approve` | `purchase-requests.approve` | `procurement.view` |  |
| POST | `/purchase-requests/{purchase_request}/cancel` | `purchase-requests.cancel` | `procurement.view` |  |
| GET | `/purchase-requests/{purchase_request}/edit` | `purchase-requests.edit` | `procurement.view` |  |
| POST | `/purchase-requests/{purchase_request}/orders` | `purchase-orders.store` | `procurement.view` |  |
| GET | `/purchase-requests/{purchase_request}/orders/create` | `purchase-orders.create` | `procurement.view` |  |
| POST | `/purchase-requests/{purchase_request}/quotations` | `purchase-requests.quotations.store` | `procurement.view` | uploads |
| POST | `/purchase-requests/{purchase_request}/reject` | `purchase-requests.reject` | `procurement.view` |  |
| POST | `/purchase-requests/{purchase_request}/submit` | `purchase-requests.submit` | `procurement.view` |  |

## /quotations

| Method | URI | Name | Permission | Rate limit |
|---|---|---|---|---|
| POST | `/quotations/{quotation}/select` | `quotations.select` | `procurement.view` |  |

## /reports

| Method | URI | Name | Permission | Rate limit |
|---|---|---|---|---|
| GET | `/reports` | `reports.index` | `reports.view` |  |
| GET | `/reports/{report}` | `reports.show` | `reports.view` |  |
| GET | `/reports/{report}/export` | `reports.export` | `reports.view` | exports |

## /reset-password

| Method | URI | Name | Permission | Rate limit |
|---|---|---|---|---|
| POST | `/reset-password` | `password.update` | — | sensitive |
| GET | `/reset-password/{token}` | `password.reset` | — |  |

## /staff

| Method | URI | Name | Permission | Rate limit |
|---|---|---|---|---|
| GET | `/staff` | `staff.index` | `staff.view` |  |
| POST | `/staff` | `staff.store` | `staff.view` |  |
| GET | `/staff/create` | `staff.create` | `staff.view` |  |
| GET | `/staff/{staff}` | `staff.show` | `staff.view` |  |
| PUT/PATCH | `/staff/{staff}` | `staff.update` | `staff.view` |  |
| DELETE | `/staff/{staff}` | `staff.destroy` | `staff.view` |  |
| GET | `/staff/{staff}/edit` | `staff.edit` | `staff.view` |  |

## /suppliers

| Method | URI | Name | Permission | Rate limit |
|---|---|---|---|---|
| GET | `/suppliers` | `suppliers.index` | `suppliers.view` |  |
| POST | `/suppliers` | `suppliers.store` | `suppliers.view` |  |
| GET | `/suppliers/create` | `suppliers.create` | `suppliers.view` |  |
| GET | `/suppliers/{supplier}` | `suppliers.show` | `suppliers.view` |  |
| PUT/PATCH | `/suppliers/{supplier}` | `suppliers.update` | `suppliers.view` |  |
| DELETE | `/suppliers/{supplier}` | `suppliers.destroy` | `suppliers.view` |  |
| POST | `/suppliers/{supplier}/contracts` | `contracts.store` | `suppliers.view` |  |
| GET | `/suppliers/{supplier}/contracts/create` | `contracts.create` | `suppliers.view` |  |
| GET | `/suppliers/{supplier}/edit` | `suppliers.edit` | `suppliers.view` |  |

## /tasks

| Method | URI | Name | Permission | Rate limit |
|---|---|---|---|---|
| GET | `/tasks` | `tasks.index` | — |  |
| POST | `/tasks` | `tasks.store` | — |  |
| GET | `/tasks/create` | `tasks.create` | — |  |
| GET | `/tasks/{task}` | `tasks.show` | — |  |
| PUT/PATCH | `/tasks/{task}` | `tasks.update` | — |  |
| DELETE | `/tasks/{task}` | `tasks.destroy` | — |  |
| POST | `/tasks/{task}/approve` | `tasks.approve` | — |  |
| POST | `/tasks/{task}/cancel` | `tasks.cancel` | — |  |
| POST | `/tasks/{task}/comments` | `tasks.comments.store` | — |  |
| GET | `/tasks/{task}/edit` | `tasks.edit` | — |  |
| POST | `/tasks/{task}/progress` | `tasks.progress` | — |  |
| POST | `/tasks/{task}/reject` | `tasks.reject` | — |  |
| POST | `/tasks/{task}/submit` | `tasks.submit` | — | uploads |

## /up

| Method | URI | Name | Permission | Rate limit |
|---|---|---|---|---|
| GET | `/up` |  | — |  |
