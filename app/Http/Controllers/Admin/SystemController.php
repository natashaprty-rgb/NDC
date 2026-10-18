<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BackupRecord;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Application health for administrators. Deliberately shows no secrets,
 * environment variables, credentials or stack traces.
 */
class SystemController extends Controller
{
    public function index(): View
    {
        $checks = [];

        try {
            DB::select('select 1');
            $dbVersion = DB::selectOne('select version() as v')->v ?? '';
            $checks['database'] = ['ok' => true, 'detail' => DB::connection()->getDriverName().' '.$dbVersion];
        } catch (\Throwable) {
            $checks['database'] = ['ok' => false, 'detail' => __('system.db_unreachable')];
        }

        $privateRoot = Storage::disk('local')->path('');
        $checks['storage'] = ['ok' => is_writable($privateRoot) && is_writable(storage_path('logs')), 'detail' => __('system.private_storage')];
        $checks['app_key'] = ['ok' => ! empty(config('app.key')), 'detail' => ''];
        $checks['debug_off'] = ['ok' => ! config('app.debug') || ! app()->isProduction(), 'detail' => config('app.debug') ? __('system.debug_on') : __('system.debug_off')];
        $checks['https'] = ['ok' => str_starts_with((string) config('app.url'), 'https://') || ! app()->isProduction(), 'detail' => (string) config('app.url')];
        $checks['secure_cookies'] = ['ok' => (bool) config('session.secure') || ! app()->isProduction(), 'detail' => ''];

        $heartbeat = Cache::get('ndc.scheduler.heartbeat');
        $checks['scheduler'] = ['ok' => $heartbeat && now()->diffInMinutes($heartbeat) < 10, 'detail' => $heartbeat ? fmt_datetime($heartbeat) : __('system.never')];

        $pendingJobs = $this->safeCount('jobs');
        $failedJobs = $this->safeCount('failed_jobs');
        $checks['queue'] = ['ok' => $failedJobs === 0, 'detail' => __('system.queue_detail', ['pending' => $pendingJobs, 'failed' => $failedJobs, 'driver' => config('queue.default')])];

        $lastBackup = BackupRecord::query()->where('status', 'successful')->latest('completed_at')->first();
        $checks['backup'] = ['ok' => $lastBackup && $lastBackup->completed_at?->gt(now()->subDays(2)), 'detail' => $lastBackup ? fmt_datetime($lastBackup->completed_at) : __('system.never')];

        $free = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());

        return view('admin.system.index', [
            'checks' => $checks,
            'info' => [
                'app_version' => config('ndc.version'),
                'environment' => app()->environment(),
                'php' => PHP_VERSION,
                'laravel' => Application::VERSION,
                'timezone' => config('app.timezone'),
                'mail_driver' => config('mail.default'),
                'cache_driver' => config('cache.default'),
                'session_driver' => config('session.driver'),
                'disk_free' => $free ? round($free / 1073741824, 1).' GB' : '—',
                'disk_total' => $total ? round($total / 1073741824, 1).' GB' : '—',
                'last_reminders' => Cache::get('ndc.reminders.last_run'),
            ],
            'errorLog' => $this->recentErrors(),
        ]);
    }

    private function safeCount(string $table): int
    {
        try {
            return DB::table($table)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Last error-level log lines, message only (no stack traces, truncated). */
    private function recentErrors(int $limit = 30): array
    {
        $file = storage_path('logs/laravel.log');
        if (! is_readable($file)) {
            return [];
        }

        $size = filesize($file);
        $handle = fopen($file, 'rb');
        fseek($handle, max(0, $size - 262144)); // read at most the last 256 KB
        $chunk = (string) stream_get_contents($handle);
        fclose($handle);

        preg_match_all('/^\[(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}[^\]]*)\] \w+\.(ERROR|CRITICAL|ALERT|EMERGENCY|WARNING): (.*)$/m', $chunk, $matches, PREG_SET_ORDER);

        return collect($matches)->reverse()->take($limit)->map(fn ($m) => [
            'time' => $m[1],
            'level' => $m[2],
            // Strip anything that looks like a file path or JSON context to avoid leaking internals.
            'message' => \Illuminate\Support\Str::limit(trim(preg_replace('/\{.*$|\/[\w\/.-]+\.php(:\d+)?/', '', $m[3])), 300),
        ])->values()->all();
    }
}
