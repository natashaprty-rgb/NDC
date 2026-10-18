# Roles and permissions matrix

Generated from `config/ndc_permissions.php` (the single source of truth used by the seeder).
Roles are **defaults**: a Super Administrator can adjust them in *Administration → Roles & permissions*.
Re-running `php artisan db:seed --class=RolesAndPermissionsSeeder` adds missing permissions but never removes ones an administrator granted.

## Roles

| Slug | Role | Purpose |
|---|---|---|
| `super-admin` | Super Administrator | Full access to every function. Keep to a minimum number of accounts. |
| `system-admin` | System Administrator | Manages users, settings, backups and system health. No financial approval rights. |
| `director` | Director / Management | Organisation-wide visibility and approvals. |
| `project-manager` | Project Manager | Creates and runs projects, assigns and approves project tasks. |
| `department-head` | Department Head | Oversees a department: staff, tasks, documents and requests. |
| `project-officer` | Project Officer | Works on project tasks and records project information. |
| `document-officer` | Document Officer | Maintains the document repository and correspondence registers. |
| `finance-officer` | Procurement / Finance Officer | Suppliers, purchase orders, goods receipt, budgets and expense recording. Cannot approve. |
| `staff` | General Staff | Own tasks, directory, internal documents, meetings. |
| `auditor` | Read-Only Auditor | Read-only access to records, reports and audit logs. Cannot change data. |

## Matrix

✔ = granted by default.

### Dashboard

| Permission | Meaning | SA | SysA | Dir | PM | DH | PO | DO | FO | Staff | Aud |
|---|---|---|---|---|---|---|---|---|---|---|---|
| `dashboard.view` | View the executive dashboard | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |

### Administration

| Permission | Meaning | SA | SysA | Dir | PM | DH | PO | DO | FO | Staff | Aud |
|---|---|---|---|---|---|---|---|---|---|---|---|
| `users.view` | View user accounts | ✔ | ✔ |  |  |  |  |  |  |  | ✔ |
| `users.manage` | Create, edit, activate and suspend user accounts | ✔ | ✔ |  |  |  |  |  |  |  |  |
| `roles.manage` | Create and edit roles and their permissions | ✔ |  |  |  |  |  |  |  |  |  |
| `permissions.review` | Record periodic permission reviews | ✔ | ✔ |  |  |  |  |  |  |  |  |
| `settings.manage` | Change organisation and system settings | ✔ | ✔ |  |  |  |  |  |  |  |  |
| `audit.view` | View audit and login activity logs | ✔ | ✔ | ✔ |  |  |  |  |  |  | ✔ |
| `system.health` | View application health and error logs | ✔ | ✔ |  |  |  |  |  |  |  |  |
| `backups.manage` | View and record backup status | ✔ | ✔ |  |  |  |  |  |  |  |  |

### Organisation

| Permission | Meaning | SA | SysA | Dir | PM | DH | PO | DO | FO | Staff | Aud |
|---|---|---|---|---|---|---|---|---|---|---|---|
| `departments.view` | View departments | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| `departments.manage` | Create and edit departments | ✔ | ✔ |  |  |  |  |  |  |  |  |
| `staff.view` | View the staff directory | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| `staff.view_sensitive` | View full staff records (employment details, notes) | ✔ | ✔ | ✔ |  | ✔ |  |  |  |  | ✔ |
| `staff.manage` | Register and edit staff records | ✔ | ✔ |  |  |  |  |  |  |  |  |

### Projects

| Permission | Meaning | SA | SysA | Dir | PM | DH | PO | DO | FO | Staff | Aud |
|---|---|---|---|---|---|---|---|---|---|---|---|
| `projects.view` | View projects | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| `projects.create` | Create projects | ✔ |  | ✔ | ✔ |  |  |  |  |  |  |
| `projects.update` | Edit projects the user manages | ✔ |  | ✔ | ✔ |  |  |  |  |  |  |
| `projects.manage` | Edit and archive any project | ✔ |  | ✔ |  |  |  |  |  |  |  |

### Tasks

| Permission | Meaning | SA | SysA | Dir | PM | DH | PO | DO | FO | Staff | Aud |
|---|---|---|---|---|---|---|---|---|---|---|---|
| `tasks.view_all` | View all tasks (not only own / own projects) | ✔ |  | ✔ |  | ✔ |  |  |  |  | ✔ |
| `tasks.create` | Create and assign tasks | ✔ |  | ✔ | ✔ | ✔ | ✔ |  |  |  |  |
| `tasks.manage` | Edit and reassign any task | ✔ |  | ✔ |  |  |  |  |  |  |  |
| `tasks.approve` | Approve or reject task completion | ✔ |  | ✔ | ✔ | ✔ |  |  |  |  |  |

### Documents

| Permission | Meaning | SA | SysA | Dir | PM | DH | PO | DO | FO | Staff | Aud |
|---|---|---|---|---|---|---|---|---|---|---|---|
| `documents.view` | View public and internal documents | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| `documents.create` | Register documents and upload versions | ✔ |  | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |  |  |
| `documents.manage` | Edit, archive and manage access of any document | ✔ |  |  |  |  |  | ✔ |  |  |  |
| `documents.approve` | Approve or reject documents | ✔ |  | ✔ |  | ✔ |  |  |  |  |  |
| `documents.view_confidential` | View confidential documents of any department | ✔ |  | ✔ |  |  |  |  |  |  | ✔ |
| `documents.view_restricted` | View restricted documents | ✔ |  | ✔ |  |  |  |  |  |  |  |

### Correspondence

| Permission | Meaning | SA | SysA | Dir | PM | DH | PO | DO | FO | Staff | Aud |
|---|---|---|---|---|---|---|---|---|---|---|---|
| `correspondence.view` | View the correspondence registers | ✔ |  | ✔ | ✔ | ✔ |  | ✔ |  |  | ✔ |
| `correspondence.manage` | Register, assign and forward correspondence | ✔ |  | ✔ |  | ✔ |  | ✔ |  |  |  |
| `correspondence.view_confidential` | View confidential correspondence | ✔ |  | ✔ |  |  |  |  |  |  | ✔ |

### Meetings

| Permission | Meaning | SA | SysA | Dir | PM | DH | PO | DO | FO | Staff | Aud |
|---|---|---|---|---|---|---|---|---|---|---|---|
| `meetings.view` | View meetings and calendar | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |  | ✔ | ✔ |
| `meetings.manage` | Schedule meetings and record minutes | ✔ |  | ✔ | ✔ | ✔ |  | ✔ |  |  |  |

### Assets

| Permission | Meaning | SA | SysA | Dir | PM | DH | PO | DO | FO | Staff | Aud |
|---|---|---|---|---|---|---|---|---|---|---|---|
| `assets.view` | View asset inventory | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |  | ✔ |  | ✔ |
| `assets.manage` | Register, assign, transfer, maintain and retire assets | ✔ |  |  |  |  |  |  | ✔ |  |  |

### Procurement

| Permission | Meaning | SA | SysA | Dir | PM | DH | PO | DO | FO | Staff | Aud |
|---|---|---|---|---|---|---|---|---|---|---|---|
| `suppliers.view` | View suppliers and contracts | ✔ |  | ✔ | ✔ | ✔ |  |  | ✔ |  | ✔ |
| `suppliers.manage` | Register and edit suppliers and contracts | ✔ |  |  |  |  |  |  | ✔ |  |  |
| `procurement.view` | View purchase requests and orders | ✔ |  | ✔ | ✔ | ✔ | ✔ |  | ✔ | ✔ | ✔ |
| `procurement.request` | Create and submit purchase requests | ✔ |  |  | ✔ | ✔ | ✔ |  | ✔ | ✔ |  |
| `procurement.approve` | Approve or reject purchase requests | ✔ |  | ✔ |  | ✔ |  |  |  |  |  |
| `procurement.order` | Record quotations and purchase orders | ✔ |  |  |  |  |  |  | ✔ |  |  |
| `procurement.receive` | Record goods received | ✔ |  |  |  |  |  |  | ✔ |  |  |

### Finance

| Permission | Meaning | SA | SysA | Dir | PM | DH | PO | DO | FO | Staff | Aud |
|---|---|---|---|---|---|---|---|---|---|---|---|
| `budgets.view` | View budgets and allocations | ✔ |  | ✔ | ✔ | ✔ |  |  | ✔ |  | ✔ |
| `budgets.manage` | Create and edit budgets and allocations | ✔ |  |  |  |  |  |  | ✔ |  |  |
| `budgets.approve` | Approve budgets | ✔ |  | ✔ |  |  |  |  |  |  |  |
| `expenses.view` | View recorded expenses | ✔ |  | ✔ | ✔ | ✔ |  |  | ✔ |  | ✔ |
| `expenses.record` | Record expenses | ✔ |  |  | ✔ |  |  |  | ✔ |  |  |
| `expenses.approve` | Approve or reject expenses | ✔ |  | ✔ |  |  |  |  |  |  |  |
| `expenses.settle` | Mark expenses as paid / reconciled (record only, no payment) | ✔ |  |  |  |  |  |  | ✔ |  |  |

### Reports

| Permission | Meaning | SA | SysA | Dir | PM | DH | PO | DO | FO | Staff | Aud |
|---|---|---|---|---|---|---|---|---|---|---|---|
| `reports.view` | View operational reports | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |  | ✔ |
| `reports.financial` | View budget, expense and procurement reports | ✔ |  | ✔ |  |  |  |  | ✔ |  | ✔ |
| `reports.export` | Export reports to CSV / PDF | ✔ |  | ✔ | ✔ | ✔ |  | ✔ | ✔ |  | ✔ |

## Record-level rules (apply to every role, including Super Administrator)

Permissions open a module; **policies** (`app/Policies`) decide what a user may do with a specific record:

- **Projects:** `projects.update` edits only projects the user manages; `projects.manage` edits any. Archived projects are read-only.
- **Tasks:** visible to participants (assignee, creator, assigner, approver), the project manager, heads of the task department, or holders of `tasks.view_all`. Only the assignee updates progress / submits. The assignee can never approve their own task; a designated approver, if set, is the only approver.
- **Documents:** `public`/`internal` — any holder of `documents.view`; `confidential` — owner, registrar, members of the document department, explicit grants, or `documents.view_confidential`; `restricted` — owner, registrar, explicit grants, or `documents.view_restricted`. Owners/registrars cannot approve their own documents.
- **Correspondence:** confidential items — assigned officer, registrar, responsible department or `correspondence.view_confidential`.
- **Purchase requests:** requesters see their own; department heads see their department; approvers/procurement/finance see all. Requester ≠ approver ≠ goods receiver; requester cannot raise the purchase order.
- **Budgets & expenses:** preparer ≠ approver; recorder ≠ approver; approver ≠ person recording payment. Expense approval cannot exceed the category allocation.
- **Users:** only a Super Administrator can create, modify or grant the Super Administrator role. Nobody can suspend, archive or change the roles of their own account.
- **Staff:** employment details are visible only with `staff.view_sensitive` / `staff.manage`, or to the person themselves.
