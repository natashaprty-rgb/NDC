# Module development conventions

Every NDC-SMS module follows the pattern of the reference module **Departments**
(`app/Http/Controllers/Organisation/DepartmentController.php`, `resources/views/departments/*`,
`tests/Feature/Organisation/DepartmentTest.php`). Read it before adding a module.

## File layout (one module = its own files)

| Concern | Location |
|---|---|
| Routes | `routes/modules/<module>.php` (auto-loaded inside the `auth`, `active`, `password.changed` group) |
| Controllers | `app/Http/Controllers/<Area>/<Name>Controller.php` |
| Validation | `app/Http/Requests/<Area>/<Name>Request.php` (`authorize()` calls the policy) |
| Multi-step logic | `app/Services/<Area>/<Name>Service.php`, wrapped in `DB::transaction()` |
| Authorisation | `app/Policies/<Model>Policy.php` (already written for every model — use, don't weaken) |
| Views | `resources/views/<module>/{index,form,show}.blade.php` using `<x-layout>` |
| Translations | `lang/en/<module>.php` **and** `lang/so/<module>.php` with identical keys |
| Tests | `tests/Feature/<Area>/<Name>Test.php` extending `Tests\TestCase` |
| Factories | `database/factories/<Model>Factory.php` |

Shared files (`status.php`, `common.php`, `fields.php`, `nav.php`, layout, components, CSS/JS,
`TestCase`, `AppServiceProvider`, policies) are changed only deliberately, in one place.

## Rules

1. **Authorisation twice.** Route group `->middleware('can:<module>.view')`, and every controller
   action calls `$this->authorize('<ability>', $model)`. Hiding a button is never sufficient.
2. **Lists use the same visibility rule as `view`.** Use model scopes such as
   `Document::visibleTo($user)` / `Correspondence::visibleTo($user)`; never list records the user
   cannot open (IDOR protection).
3. **Validation** through FormRequests; persist with `$request->validated()` or explicit arrays.
   Money: `numeric|min:0|max:9999999999999.99|decimal:0,2`; dates: `date`, `after_or_equal`.
4. **Reference numbers:** `ReferenceNumber::next('<type>')` inside the same `DB::transaction()`
   that inserts the record. Types are listed in `config/ndc.php` (`numbering.prefix.*`).
5. **Money:** never use floats. Use `App\Support\Money` (integer cents) and `money()` for display.
6. **Files:** never handle uploads directly. Use the shared `<x-attachments :model="$m" />`
   component (backed by `AttachmentController` + policy `attach`/`view`) or
   `FileStorage::store()` / `FileStorage::download()` for module-specific files (document versions).
7. **Auditing** is automatic for models using `Auditable`. For workflow actions that are not
   plain updates (approve, reject, forward, download), also call
   `AuditLogger::log('<event>', $model, 'description')`.
8. **Notifications:** `Notifier::send($users, 'kind', '<module>.notify.<key>', [...params], $url)`.
   Translation keys live in the module's language file.
9. **Status labels:** `status_label($value)` and `<x-badge :value="$status" />`.
10. **Empty states:** every list shows `<x-empty>` when there are no rows. Never display invented figures.
11. **Filters:** GET parameters (`q`, `status`, ...) via `<x-filter-bar>`; paginate 25 and `->withQueryString()`.
12. **Destructive actions:** `<x-delete-button>` (confirmation dialog). Prefer soft delete / archive.
13. **Tests must prove behaviour**: CRUD persists, validation rejects bad input, unauthorised users get 403
    (or 404), workflow rules (segregation of duties) hold, lists do not leak restricted records.
