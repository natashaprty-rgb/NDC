<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ndc:backup {--prune : Delete archives older than the retention period afterwards}')]
#[Description('Create a verified backup of the database and uploaded files')]
class BackupCommand extends Command
{
    public function handle(BackupService $backups): int
    {
        $run = $backups->run();

        if ($run->status !== 'success') {
            $this->error("Backup #{$run->id} {$run->status}: {$run->message}");

            return self::FAILURE;
        }

        $this->info("Backup #{$run->id} created and verified: {$run->path} (".format_bytes($run->size).')');
        $this->line($run->verification_result);

        if ($this->option('prune')) {
            $this->info('Pruned '.$backups->prune().' old backup(s).');
        }

        return self::SUCCESS;
    }
}
