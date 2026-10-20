<?php

namespace App\Reports;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditActivityReport extends Report
{
    public function key(): string
    {
        return 'audit';
    }

    public function title(): string
    {
        return __('User activity and administrative audit');
    }

    public function description(): string
    {
        return __('Activity per user: sign-ins, failed sign-ins, changes made and administrative actions.');
    }

    public function permission(): string
    {
        return 'audit.view';
    }

    public function filters(): array
    {
        return $this->dateRange('Date');
    }

    public function columns(): array
    {
        return ['User', 'E-mail', 'Sign-ins', 'Records created', 'Records updated', 'Records archived/deleted', 'Approvals decided', 'Administrative actions', 'Last activity'];
    }

    public function numericColumns(): array
    {
        return [2, 3, 4, 5, 6, 7];
    }

    public function rows(Request $request, User $user): iterable
    {
        $admin = ['roles_changed', 'role_created', 'role_updated', 'role_deleted', 'settings_updated', 'password_reset_sent', 'two_factor_reset', 'backup_run', 'backup_downloaded'];

        $logs = AuditLog::query()->whereNotNull('user_id')
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->selectRaw('user_id, event, COUNT(*) as total, MAX(created_at) as last_at')
            ->groupBy('user_id', 'event')->get()->groupBy('user_id');

        $users = User::query()->whereIn('id', $logs->keys())->orderBy('name')->get();

        return $users->map(function (User $u) use ($logs, $admin) {
            $events = $logs[$u->id]->pluck('total', 'event');

            return [
                $u->name, $u->email, (int) ($events['login'] ?? 0), (int) ($events['created'] ?? 0), (int) ($events['updated'] ?? 0),
                (int) ($events['archived'] ?? 0) + (int) ($events['deleted'] ?? 0),
                (int) ($events['approved'] ?? 0) + (int) ($events['rejected'] ?? 0),
                collect($admin)->sum(fn ($e) => (int) ($events[$e] ?? 0)),
                fmt_date($logs[$u->id]->max('last_at'), true),
            ];
        });
    }

    public function summary(Request $request, User $user): array
    {
        $q = AuditLog::query()
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d));

        return [
            __('Sign-ins') => (clone $q)->where('event', 'login')->count(),
            __('Failed sign-ins') => (clone $q)->where('event', 'login_failed')->count(),
            __('Changes recorded') => (clone $q)->whereIn('event', ['created', 'updated', 'archived', 'deleted'])->count(),
        ];
    }
}
