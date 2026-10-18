<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PermissionReviewRequest;
use App\Models\PermissionReview;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Periodic access review: a sheet of every account with its roles and
 * risk flags, plus a register of completed reviews.
 */
class PermissionReviewController extends Controller
{
    public const INACTIVE_DAYS = 90;

    public function index(): View
    {
        $reviews = PermissionReview::query()->with('reviewer')->latest('review_date')->latest('id')->paginate(25);
        $last = PermissionReview::query()->latest('review_date')->first();
        $interval = (int) Settings::get('security.permission_review_days', 90);
        $overdue = ! $last || $last->review_date->lt(today()->subDays($interval));

        return view('admin.permission-reviews.index', compact('reviews', 'last', 'overdue', 'interval'));
    }

    public function sheet(): View
    {
        return view('admin.permission-reviews.sheet', ['rows' => $this->rows()]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->rows();
        AuditLogger::log('exported', null, 'Access review sheet exported ('.$rows->count().' accounts)');

        $headers = [__('common.name'), __('auth.email'), __('common.status'), __('users.roles'), __('audit.permission_count'),
            __('users.last_login'), __('users.mfa'), __('audit.flags')];

        return response()->streamDownload(function () use ($rows, $headers) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'csvSafe'], [
                    $row['user']->name,
                    $row['user']->email,
                    $row['user']->status,
                    $row['roles'],
                    $row['permission_count'],
                    $row['user']->last_login_at?->format('Y-m-d H:i') ?? '',
                    $row['user']->hasMfaEnabled() ? 'yes' : 'no',
                    implode('; ', array_map(fn ($f) => __('audit.flag.'.$f), $row['flags'])),
                ]));
            }
            fclose($out);
        }, 'access-review-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function create(): View
    {
        return view('admin.permission-reviews.form', ['accounts' => User::query()->count()]);
    }

    public function store(PermissionReviewRequest $request): RedirectResponse
    {
        PermissionReview::query()->create($request->validated() + [
            'reviewed_by' => $request->user()->id,
            'accounts_reviewed' => User::query()->count(),
        ]);

        return redirect()->route('admin.permission-reviews.index')->with('success', __('audit.review_recorded'));
    }

    /** Neutralise spreadsheet formula injection. */
    public static function csvSafe(mixed $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }

    /** @return Collection<int, array{user: User, roles: string, permission_count: int, flags: list<string>}> */
    private function rows(): Collection
    {
        $threshold = now()->subDays(self::INACTIVE_DAYS);

        return User::query()->with('roles.permissions')->orderBy('name')->get()->map(function (User $user) use ($threshold) {
            $flags = [];
            if (! $user->isActive()) {
                $flags[] = 'suspended';
            }
            if ($user->isSuperAdmin()) {
                $flags[] = 'super_admin';
            }
            if (! $user->last_login_at || $user->last_login_at->lt($threshold)) {
                $flags[] = 'inactive';
            }
            if (! $user->hasMfaEnabled()) {
                $flags[] = 'no_mfa';
            }
            $count = $user->isSuperAdmin()
                ? count(\App\Models\Permission::allSlugs())
                : $user->roles->flatMap(fn ($r) => $r->permissions->pluck('slug'))->unique()->count();

            return ['user' => $user, 'roles' => $user->roles->pluck('name')->join(', '), 'permission_count' => $count, 'flags' => $flags];
        });
    }
}
