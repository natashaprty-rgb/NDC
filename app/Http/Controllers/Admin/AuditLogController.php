<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\CsvExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.audit.index', [
            'logs' => $this->query($request)->with('user')->paginate($this->perPage($request, 50))->withQueryString(),
            'users' => User::query()->orderBy('name')->pluck('name', 'id'),
            'events' => AuditLog::query()->distinct()->orderBy('event')->pluck('event', 'event'),
            'types' => AuditLog::query()->whereNotNull('auditable_type')->distinct()->orderBy('auditable_type')->pluck('auditable_type', 'auditable_type'),
        ]);
    }

    public function show(AuditLog $log): View
    {
        return view('admin.audit.show', ['log' => $log->load('user')]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can('reports.export'), 403);

        return CsvExport::download('audit-log', ['Date', 'User', 'Event', 'Record type', 'Record ID', 'Description', 'IP address'],
            $this->query($request)->with('user')->lazy(500)->map(fn (AuditLog $log) => [
                $log->created_at?->toDateTimeString(), $log->user?->email, $log->event, $log->auditable_type,
                $log->auditable_id, $log->description, $log->ip_address,
            ]));
    }

    private function query(Request $request): Builder
    {
        return AuditLog::query()
            ->when($request->query('user'), fn ($q, $u) => $q->where('user_id', $u))
            ->when($request->query('event'), fn ($q, $e) => $q->where('event', $e))
            ->when($request->query('type'), fn ($q, $t) => $q->where('auditable_type', $t))
            ->when($request->query('record'), fn ($q, $id) => $q->where('auditable_id', $id))
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($q) => $q->where('description', 'like', "%{$t}%")->orWhere('ip_address', 'like', "%{$t}%")))
            ->latest('created_at')->latest('id');
    }
}
