<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BackupRun;
use App\Services\BackupService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Application health, background jobs and backups.
 */
class SystemController extends Controller
{
    public function index(): View
    {
        $checks = [];

        try {
            DB::connection()->getPdo();
            $checks['database'] = ['ok', DB::connection()->getDriverName().' · '.DB::connection()->getDatabaseName()];
        } catch (Throwable $e) {
            $checks['database'] = ['fail', $e->getMessage()];
        }

        $storage = storage_path('app/private');
        $free = @disk_free_space($storage);
        $checks['storage'] = [
            is_writable($storage) ? ($free !== false && $free < 1024 ** 3 ? 'warn' : 'ok') : 'fail',
            (is_writable($storage) ? __('Writable') : __('Not writable')).($free !== false ? ' · '.__(':size free', ['size' => format_bytes($free)]) : ''),
        ];

        $heartbeat = Cache::get('ndc.scheduler.heartbeat');
        $checks['scheduler'] = $heartbeat && now()->diffInMinutes($heartbeat, true) <= 10
            ? ['ok', __('Last run :time', ['time' => fmt_date($heartbeat, true)])]
            : ['warn', $heartbeat ? __('Last run :time', ['time' => fmt_date($heartbeat, true)]) : __('No run recorded. Configure the cron entry for schedule:run.')];

        $pendingJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();
        $checks['queue'] = [$failedJobs > 0 ? 'warn' : 'ok', __(':pending pending, :failed failed', ['pending' => $pendingJobs, 'failed' => $failedJobs]).' · '.config('queue.default')];

        $lastBackup = BackupRun::query()->latest('id')->first();
        $lastGood = BackupRun::query()->where('status', 'success')->latest('id')->first();
        $checks['backup'] = match (true) {
            $lastGood && $lastGood->verified_at->gt(now()->subDay()) => ['ok', __('Last verified backup :time', ['time' => fmt_date($lastGood->verified_at, true)])],
            $lastGood !== null => ['warn', __('Last verified backup :time', ['time' => fmt_date($lastGood->verified_at, true)])],
            default => ['fail', __('No verified backup exists.')],
        };

        $checks['debug'] = config('app.debug') && app()->isProduction()
            ? ['fail', __('Debug mode is enabled in production.')]
            : ['ok', config('app.env').' · '.(config('app.debug') ? 'debug on' : 'debug off')];

        $checks['https'] = app()->isProduction() && ! str_starts_with((string) config('app.url'), 'https://')
            ? ['fail', __('APP_URL is not HTTPS.')]
            : ['ok', (string) config('app.url')];

        $checks['mail'] = [setting('mail_notifications_enabled') ? 'ok' : 'warn', config('mail.default').' · '.(setting('mail_notifications_enabled') ? __('e-mail notifications on') : __('e-mail notifications off'))];

        return view('admin.system.index', [
            'checks' => $checks,
            'lastBackup' => $lastBackup,
            'failed' => DB::table('failed_jobs')->latest('failed_at')->limit(10)->get(),
            'info' => [
                'PHP' => PHP_VERSION,
                'Laravel' => app()->version(),
                __('Timezone') => config('app.timezone'),
                __('Migrations') => DB::table('migrations')->count(),
            ],
        ]);
    }

    public function backups(): View
    {
        return view('admin.system.backups', [
            'runs' => BackupRun::query()->with('triggeredBy')->latest('id')->paginate(20),
        ]);
    }

    public function runBackup(Request $request, BackupService $backups): RedirectResponse
    {
        $run = $backups->run($request->user());
        Audit::log('backup_run', $run, "#{$run->id} {$run->status}");

        return redirect()->route('admin.backups.index')->with(
            $run->status === 'success' ? 'success' : 'error',
            $run->status === 'success' ? __('Backup created and verified.') : __('Backup did not complete verification: :message', ['message' => $run->message]),
        );
    }

    public function verify(BackupRun $run, BackupService $backups): RedirectResponse
    {
        $backups->verify($run);
        Audit::log('backup_verified', $run, "#{$run->id} {$run->status}");

        return back()->with($run->status === 'success' ? 'success' : 'error', $run->status === 'success'
            ? __('Backup verified successfully.')
            : __('Verification failed: :message', ['message' => $run->message]));
    }

    public function download(BackupRun $run): StreamedResponse
    {
        abort_unless($run->path && Storage::disk($run->disk)->exists($run->path), 404);
        Audit::log('backup_downloaded', $run, $run->path);

        return Storage::disk($run->disk)->download($run->path);
    }
}
