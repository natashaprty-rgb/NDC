# Database design and ERD

MySQL 8 (InnoDB, `utf8mb4_unicode_ci`). The schema is defined only by the Laravel
migrations in `database/migrations`; never change tables by hand.

## Conventions

| Convention | Rule |
|---|---|
| Keys | `id` BIGINT UNSIGNED auto-increment; foreign keys are real InnoDB constraints. |
| References | Human reference numbers (`reference`, `asset_tag`, `code`) are `UNIQUE` and produced by `App\Support\ReferenceNumber` from `number_sequences` under a row lock. |
| Money | `DECIMAL(15,2)`; quantities `DECIMAL(12,2)`. No floating-point columns are used for money. |
| Userstamps | `created_by`, `updated_by` → `users.id` (`ON DELETE SET NULL`). |
| Deletion | Business records use soft deletes (`deleted_at`) or an explicit archive flag/status. Users are never hard-deleted. |
| History | `audit_logs` (append-only) stores old/new values for every audited model; workflow tables (`asset_movements`, `correspondence_actions`, `document_versions`, `goods_receipts`) keep domain history. |
| Polymorphic | `attachments`, `audit_logs`, `notifications`, `sent_reminders` use short morph aliases (`project`, `task`, …) defined in `AppServiceProvider`. |

## Entity relationship diagram

The diagram is split by area for readability (Mermaid syntax — renders on GitHub).

### Identity, organisation, cross-cutting

```mermaid
erDiagram
    users ||--o{ role_user : has
    roles ||--o{ role_user : grants
    roles ||--o{ permission_role : includes
    permissions ||--o{ permission_role : in
    users ||--o{ login_activities : records
    users ||--o{ audit_logs : performs
    users ||--o| staff : "linked to"
    departments ||--o{ staff : employs
    departments ||--o{ departments : "parent of"
    users ||--o{ departments : heads
    users ||--o{ permission_reviews : reviews
    users ||--o{ notifications : receives
    users ||--o{ notification_logs : "delivery status"
    users ||--o{ backup_records : records
    attachments }o--|| users : "uploaded by"
```

### Projects, tasks, meetings

```mermaid
erDiagram
    projects ||--o{ project_milestones : has
    projects ||--o{ project_stakeholders : has
    projects ||--o{ project_risks : has
    projects ||--o{ tasks : contains
    project_milestones ||--o{ tasks : groups
    users ||--o{ projects : manages
    departments ||--o{ projects : "responsible for"
    users ||--o{ tasks : "assigned to"
    tasks ||--o{ task_comments : has
    suppliers ||--o{ project_stakeholders : "may be"
    meetings ||--o{ meeting_participants : invites
    meetings ||--o{ meeting_items : records
    meeting_items |o--o| tasks : "converted to"
    projects ||--o{ meetings : "related to"
    users ||--o{ meetings : organises
```

### Documents and correspondence

```mermaid
erDiagram
    document_categories ||--o{ documents : classifies
    documents ||--o{ document_versions : "has versions"
    documents ||--o{ document_access : "access grants"
    users ||--o{ documents : owns
    departments ||--o{ documents : "belongs to"
    projects ||--o{ documents : "supports"
    correspondences ||--o{ correspondence_actions : "forwarded / followed up"
    correspondences ||--o{ correspondences : "in reply to"
    departments ||--o{ correspondences : "responsible"
    users ||--o{ correspondences : "assigned officer"
```

### Assets, procurement, finance

```mermaid
erDiagram
    asset_categories ||--o{ assets : classifies
    locations ||--o{ assets : houses
    staff ||--o{ assets : "assigned to"
    assets ||--o{ asset_movements : history
    assets ||--o{ asset_maintenances : maintenance
    suppliers ||--o{ assets : supplied
    suppliers ||--o{ supplier_contracts : has
    purchase_requests ||--o{ purchase_request_items : lists
    purchase_requests ||--o{ quotations : receives
    suppliers ||--o{ quotations : quotes
    purchase_requests ||--o{ purchase_orders : "fulfilled by"
    purchase_orders ||--o{ purchase_order_items : lists
    purchase_orders ||--o{ goods_receipts : "received via"
    goods_receipts ||--o{ goods_receipt_items : lists
    purchase_order_items ||--o{ goods_receipt_items : "received as"
    funding_sources ||--o{ budgets : funds
    budgets ||--o{ budget_allocations : "split into"
    expense_categories ||--o{ budget_allocations : category
    budgets ||--o{ expenses : "charged to"
    expense_categories ||--o{ expenses : category
    purchase_orders ||--o{ expenses : "may relate"
    budgets ||--o{ purchase_requests : "may fund"
```

## Tables by area

| Area | Tables |
|---|---|
| Identity & access | `users`, `roles`, `permissions`, `role_user`, `permission_role`, `login_activities`, `audit_logs`, `permission_reviews`, `password_reset_tokens`, `sessions` |
| Organisation | `departments`, `staff` |
| Projects & tasks | `projects`, `project_milestones`, `project_stakeholders`, `project_risks`, `tasks`, `task_comments` |
| Documents | `document_categories`, `documents`, `document_versions`, `document_access` |
| Correspondence | `correspondences`, `correspondence_actions` |
| Meetings | `meetings`, `meeting_participants`, `meeting_items` |
| Assets | `asset_categories`, `locations`, `assets`, `asset_movements`, `asset_maintenances` |
| Suppliers & procurement | `suppliers`, `supplier_contracts`, `purchase_requests`, `purchase_request_items`, `quotations`, `purchase_orders`, `purchase_order_items`, `goods_receipts`, `goods_receipt_items` |
| Finance | `funding_sources`, `expense_categories`, `budgets`, `budget_allocations`, `expenses` |
| Cross-cutting | `attachments`, `settings`, `number_sequences`, `notifications`, `notification_logs`, `sent_reminders`, `backup_records` |
| Framework | `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations` |

## Important business rules (enforced in code and tests)

1. **Reference numbers** are unique (`UNIQUE` index) and sequential per type and year: the
   `number_sequences` row is locked (`SELECT … FOR UPDATE`) inside the inserting transaction.
2. **Project progress** is derived from completed vs. non-cancelled tasks (fallback: milestones). It cannot be typed in.
3. **Tasks:** only the assignee updates progress or submits completion; tasks requiring approval move to
   `awaiting_approval`; the assignee can never approve their own task.
4. **Documents:** every file upload creates an immutable `document_versions` row; confidentiality
   (`public`, `internal`, `confidential`, `restricted`) is enforced by `Document::visibleTo()` for lists and
   `Document::canBeViewedBy()` for single records. Owners/registrars cannot approve their own documents.
5. **Procurement:** requester ≠ approver; purchase orders are raised only from approved requests by a
   procurement officer who is not the requester; goods are received by someone who is neither requester nor approver;
   received quantity cannot exceed ordered quantity.
6. **Finance:** remaining balance = allocated − approved expenditure (approved + paid + reconciled).
   An expense cannot be approved if it would exceed its category allocation (checked under a row lock).
   The preparer of a budget cannot approve it; the recorder of an expense cannot approve it; the approver cannot mark it paid.
   "Paid" and "reconciled" only record facts — the system performs no payments.
7. **Assets:** every assignment/transfer/return/retirement writes `asset_movements`; retired assets are read-only.
8. **Audit:** `audit_logs` rows are never updated or deleted by the application; passwords and MFA secrets are redacted.
9. **Retention:** nothing is deleted automatically. Document categories carry `retention_years`; `documents.retain_until` is computed at registration.

## Sensitive data held

Only work-related staff data is stored: name, staff number, job title, official email/phone, office,
employment status/type and dates. No national ID numbers, home addresses, salaries, bank details or
health data are collected. Employment fields are visible only with `staff.view_sensitive`.
MFA secrets and recovery codes are encrypted at rest with the application key.
