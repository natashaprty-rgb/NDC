# Integration API (v1)

A read-only JSON API for approved integrations (for example a government
reporting portal or a future data center monitoring system).

## Authentication

1. An administrator grants the integration's service account the
   **`api.access`** permission plus the view permission of each module it may
   read (e.g. `projects.view`). Use a dedicated user account per integration.
2. Signed in as that user, open **My profile → Security & sessions → API
   access tokens**, give the token a name and an expiry (30–365 days) and
   copy it. It is shown once only.
3. Send it as a bearer token over HTTPS:

```http
GET /api/v1/projects HTTP/1.1
Host: mis.example.gov.so
Accept: application/json
Authorization: Bearer 12|AbCdEf...
```

Tokens are read-only (`read` ability), act with the permissions of their
user, stop working when they expire, when they are revoked, or when the user
is suspended or deactivated. Creation and revocation are audit-logged.
Requests are limited to 60 per minute per user (HTTP 429 beyond).

## Conventions

- All list endpoints are paginated: `?page=2&per_page=50` (max 100). The
  response has `data`, `meta` (`current_page`, `per_page`, `total`,
  `last_page`) and `links` (`next`, `prev`).
- Dates are `YYYY-MM-DD`; timestamps are ISO 8601.
- `updated_since=2026-10-01` (where supported) returns records changed since
  that date, for incremental synchronisation.
- Errors: `401` missing/invalid token, `403` missing permission, `404` not
  found, `422` invalid parameter, `429` rate limited.
- Record-level rules are identical to the web interface: confidential
  documents and other people's tasks are not returned unless the user may see
  them. Sensitive staff data and file contents are never returned.

## Endpoints

| Method & path | Permission | Filters |
| --- | --- | --- |
| `GET /api/v1/me` | `api.access` | — |
| `GET /api/v1/projects` | `projects.view` | `status`, `updated_since` |
| `GET /api/v1/projects/{id}` | `projects.view` | — (includes milestones) |
| `GET /api/v1/tasks` | `tasks.view` | `status`, `project_id`, `overdue=1`, `updated_since` |
| `GET /api/v1/documents` | `documents.view` | `status`, `updated_since` (metadata only) |
| `GET /api/v1/assets` | `assets.view` | `status`, `updated_since` |
| `GET /api/v1/staff` | `staff.view` | `employment_status` (directory fields only) |
| `GET /api/v1/meetings` | `meetings.view` | `from`, `to` (date range on start) |

### Example response — `GET /api/v1/projects`

```json
{
  "data": [
    {
      "id": 1,
      "code": "NDC/PRJ/2026/0001",
      "title": "Data center facility construction",
      "status": "in_progress",
      "priority": "high",
      "progress": 35,
      "department": "Infrastructure",
      "manager": "…",
      "location": "Mogadishu",
      "start_date": "2026-01-15",
      "expected_end_date": "2027-06-30",
      "actual_end_date": null,
      "planned_budget": 1500000.0,
      "currency": "USD",
      "is_delayed": false,
      "updated_at": "2026-10-19T08:12:44+03:00"
    }
  ],
  "meta": { "current_page": 1, "per_page": 25, "total": 1, "last_page": 1 },
  "links": { "next": null, "prev": null }
}
```

## Extending the API

Endpoints live in `app/Http/Controllers/Api/ApiController.php` and
`routes/api/*.php`. Any new endpoint must check the module permission, apply
the module's `visibleTo()` scope where one exists, and never expose encrypted
or sensitive fields. Write operations are deliberately not offered in
version 1; add them only for an approved integration, with their own token
ability (for example `write:tasks`).
