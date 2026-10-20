<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\BackupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('ndc:prune')]
#[Description('Apply retention periods to audit logs, read notifications and backup archives')]
class PruneRecordsCommand extends Command
{
    public function handle(BackupService $backups): int
    {
        $audit = AuditLog::query()
            ->where('created_at', '<', now()->subDays((int) setting('audit_retention_days', 1825)))
            ->delete();

        $notifications = DB::table('notifications')
            ->whereNotNull('read_at')
            ->where('created_at', '<', now()->subDays((int) setting('notification_retention_days', 365)))
            ->delete();

        $archives = $backups->prune();

        $this->info("Removed {$audit} audit entries, {$notifications} notifications and {$archives} backup archives past retention.");

        return self::SUCCESS;
    }
}
