# Development Guide

Conventions for working on the NDC Somalia Management System. Read this before
adding or changing a module.

## Stack

- PHP 8.3+, Laravel 13, Blade templates, MySQL 8 (SQLite is used for the test suite).
- No front-end build step. Styles live in `public/css/ndc.css`, behaviour in
  `public/js/ndc.js`. Do not add npm, Vite, Tailwind, CDNs or external fonts.
- Packages: `spatie/laravel-permission` (roles/permissions), `laravel/sanctum`
  (API tokens), `barryvdh/laravel-dompdf` (PDF export), `pragmarx/google2fa`
  and `bacon/bacon-qr-code` (two-factor authentication).

## Layout of the code

| Concern | Location |
| --- | --- |
| Permission catalogue, default roles, sidebar, settings defaults | `config/ndc.php` |
| Every status vocabulary (values, labels, badge tones) | `config/statuses.php`, read with `App\Support\Status` |
| Schema (all modules) | `database/migrations/` |
| Models | `app/Models/` (traits in `app/Models/Concerns/`) |
| Shared services | `app/Services/` (`ApprovalService`, `BackupService`, `TwoFactorService`) |
| Helpers | `app/Support/` (`Audit`, `FileStore`, `ReferenceNumber`, `Settings`, `CsvExport`), `app/helpers.php` (`setting()`, `fmt_date()`, `money()`, `format_bytes()`) |
| Module routes | `routes/modules/{module}.php` (auto-loaded inside the `auth` middleware group) |
| API routes | `routes/api/{module}.php` (auto-loaded under `/api/v1`, Sanctum + `api.access`) |
| Policies | `app/Policies/{Model}Policy.php` (auto-discovered) |
| Views | `resources/views/{module}/` |
| Reusable Blade components | `resources/views/components/` |
| Somali translations | `lang/modules/{module}/so.json` |
| Tests | `tests/Feature/{Module}/` |

## Rules

### Security

- Every route is behind `auth`. Use `permission:` middleware for coarse access
  and a policy (`$this->authorize(...)`) for each record action.
- Record-level visibility: `Task::visibleTo()`, `Document::visibleTo()`,
  `Correspondence::visibleTo()` scopes and their `isVisibleTo()` methods. List
  pages must apply the scope; show pages must check the policy.
- Validate all input server-side (`$request->validate()` or Form Requests).
  Use `Rule::in(Status::keys('domain'))` for status fields and
  `FileStore::rules()` for uploads.
- Never mass-assign from `$request->all()`; use validated data.
- Blade escapes output with `{{ }}`. `{!! !!}` is only allowed for trusted,
  server-generated markup (icons, QR code).
- The Content Security Policy forbids inline JavaScript. No `<script>` blocks
  and no `onclick=` attributes; use the data-attributes in `public/js/ndc.js`
  (`data-confirm`, `data-repeater`, `data-autosubmit`, `data-print`).
- Files are stored on the private `local` disk via `FileStore` and downloaded
  only through controllers that authorise first.

### Workflow and audit

- Models using the `Auditable` trait log create/update/delete automatically.
  Log workflow actions explicitly: `Audit::log('submitted', $model, 'note')`.
- Approvals go through `ApprovalService::request()` / `decide()`; it enforces
  the permission and that requesters never approve their own requests. The
  model implements `approvalDecided()`. Show `<x-approval-panel :model="$x" />`.
- Nothing is approved, paid or closed automatically.
- Notify people with `GenericNotification` using English translation keys and
  replacements (translated per recipient at delivery).

### Interface

- Wrap pages in `<x-layouts.app :title="...">`, start with `<x-page-header>`.
- Lists: `<x-filters>` (with `<x-search-field>`, `<x-filter-select>`), a
  `.table` inside `.table-wrap`, `<x-sort-link>` headings,
  `$this->applySort()` and `->paginate($this->perPage($request))->withQueryString()`,
  `<x-empty>` when there are no rows, `{{ $rows->links() }}`.
- Forms: `<x-form.input|select|textarea|checkbox>` inside `.form-grid`.
- Status values: `<x-status domain="task" :value="$task->status" />`.
- Detail pages: `.grid.grid-main` with a `.card` of `<dl class="dl">`, plus
  `<x-attachments>`, `<x-approval-panel>` and `<x-audit-trail>` where relevant.
- Dates `fmt_date($date)`, money `money($amount, $currency)`.
- All interface text goes through `__('English text')`. Add the Somali
  translation of every new string to `lang/modules/{module}/so.json`.

### Data

- Do not edit existing migrations once deployed; add a new migration.
- Statistics and reports are computed from database records only. Never
  hard-code or invent operational figures.

## Running

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan ndc:create-admin
php artisan serve
```

Tests and code style:

```bash
php artisan test
vendor/bin/pint
```
