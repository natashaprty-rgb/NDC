<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BackupRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Backup status register. Backups are produced by `php artisan ndc:backup`
 * (cron) or by the hosting team; this page records and reviews them,
 * including restoration tests. Backup files are never downloadable here.
 */
class BackupController extends Controller
{
    public function index(): View
    {
        return view('admin.backups.index', [
            'records' => BackupRecord::query()->with('recorder')->latest('id')->paginate(25),
            'lastSuccess' => BackupRecord::query()->where('status', 'successful')->latest('completed_at')->first(),
            'lastRestoreTest' => BackupRecord::query()->whereNotNull('restore_tested_at')->latest('restore_tested_at')->first(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(BackupRecord::TYPES)],
            'status' => ['required', Rule::in(['successful', 'failed'])],
            'location' => ['required', 'string', 'max:255', 'not_regex:/(password|passwd|secret|token)=/i'],
            'file_name' => ['nullable', 'string', 'max:255'],
            'completed_at' => ['required', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        BackupRecord::query()->create($data + ['method' => 'manual', 'started_at' => $data['completed_at'], 'recorded_by' => $request->user()->id]);

        return back()->with('success', __('backups.recorded'));
    }

    public function restoreTest(Request $request, BackupRecord $backup): RedirectResponse
    {
        $data = $request->validate([
            'restore_test_result' => ['required', Rule::in(['passed', 'failed'])],
            'restore_tested_at' => ['required', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $backup->update([
            'restore_test_result' => $data['restore_test_result'],
            'restore_tested_at' => $data['restore_tested_at'],
            'notes' => trim(($backup->notes ? $backup->notes."\n" : '').($data['notes'] ?? '')) ?: null,
        ]);

        return back()->with('success', __('backups.restore_recorded'));
    }
}
