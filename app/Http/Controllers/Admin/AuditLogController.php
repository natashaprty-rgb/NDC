<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LoginActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Read-only views of the audit trail and sign-in activity. */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'user' => ['nullable', 'integer'],
            'event' => ['nullable', 'string', 'max:40'],
            'type' => ['nullable', 'string', 'max:120'],
            'q' => ['nullable', 'string', 'max:200'],
        ]);

        $logs = AuditLog::query()
            ->with('user')
            ->when($request->filled('user'), fn ($q) => $q->where('user_id', $request->integer('user')))
            ->when($request->filled('event'), fn ($q) => $q->where('event', $request->string('event')))
            ->when($request->filled('type'), fn ($q) => $q->where('auditable_type', $request->string('type')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->when($request->filled('q'), fn ($q) => $q->where('description', 'like', '%'.$request->string('q')->trim().'%'))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.audit.index', [
            'logs' => $logs,
            'users' => User::withTrashed()->orderBy('name')->pluck('name', 'id')->all(),
            'events' => AuditLog::query()->distinct()->orderBy('event')->pluck('event')->mapWithKeys(fn ($e) => [$e => status_label($e)])->all(),
            'types' => collect(Relation::morphMap())->keys()->sort()->mapWithKeys(fn ($t) => [$t => __('dashboard.entity.'.$t)])->all(),
        ]);
    }

    public function logins(Request $request): View
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'event' => ['nullable', 'string', 'max:30'], 'email' => ['nullable', 'string', 'max:255']]);

        $activities = LoginActivity::query()
            ->with('user')
            ->when($request->filled('event'), fn ($q) => $q->where('event', $request->string('event')))
            ->when($request->filled('email'), fn ($q) => $q->where('email', 'like', '%'.strtolower((string) $request->string('email')->trim()).'%'))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.audit.logins', [
            'activities' => $activities,
            'events' => enum_options(['login', 'logout', 'failed', 'lockout', 'mfa_failed', 'password_reset']),
        ]);
    }
}
