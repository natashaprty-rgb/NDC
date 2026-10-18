<?php

namespace App\Console\Commands;

use App\Models\BackupRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Creates a compressed MySQL dump (and optionally an archive of private
 * uploaded files) under storage/app/private/backups and records the result.
 * Credentials are passed through a temporary option file (0600), never on
 * the command line. Copy backups off the server - see docs/07-backup-restore.md.
 */
class BackupDatabase extends Command
{
    protected $signature = 'ndc:backup {--files : Also archive private uploaded files} {--keep=14 : Number of command-made backups of each type to keep}';

    protected $description = 'Back up the NDC-SMS database (and optionally uploaded files) and record the backup status';

    public function handle(): int
    {
        $dir = storage_path('app/private/backups');
        File::ensureDirectoryExists($dir, 0700);
        $stamp = now()->format('Ymd-His');

        $ok = $this->backupDatabase($dir, $stamp);
        if ($this->option('files')) {
            $ok = $this->backupFiles($dir, $stamp) && $ok;
        }

        $this->prune($dir, max(1, (int) $this->option('keep')));

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function backupDatabase(string $dir, string $stamp): bool
    {
        $connection = config('database.connections.'.config('database.default'));
        $record = BackupRecord::query()->create(['type' => 'database', 'method' => 'command', 'status' => 'failed', 'started_at' => now(), 'location' => 'storage/app/private/backups']);

        if (($connection['driver'] ?? '') !== 'mysql' && ($connection['driver'] ?? '') !== 'mariadb') {
            $record->update(['notes' => 'Only MySQL/MariaDB is supported by this command.', 'completed_at' => now()]);
            $this->error('Unsupported database driver.');

            return false;
        }

        $file = "{$dir}/ndc-db-{$stamp}.sql.gz";
        $optionFile = tempnam(sys_get_temp_dir(), 'ndcdump');
        chmod($optionFile, 0600);
        file_put_contents($optionFile, "[client]\nuser=\"{$connection['username']}\"\npassword=\"".addcslashes((string) $connection['password'], '"\\')."\"\nhost=\"{$connection['host']}\"\nport=\"{$connection['port']}\"\n");

        try {
            $dump = sprintf(
                'mysqldump --defaults-extra-file=%s --single-transaction --quick --routines --triggers --no-tablespaces %s | gzip -9 > %s',
                escapeshellarg($optionFile), escapeshellarg($connection['database']), escapeshellarg($file)
            );
            $process = new Process(['bash', '-c', 'set -o pipefail; '.$dump], null, null, null, 3600);
            $process->run();

            if (! $process->isSuccessful() || ! is_file($file) || filesize($file) < 100) {
                @unlink($file);
                $record->update(['completed_at' => now(), 'notes' => \Illuminate\Support\Str::limit(trim($process->getErrorOutput()) ?: 'mysqldump failed', 1000)]);
                $this->error('Database backup failed. See backup records.');

                return false;
            }

            $record->update([
                'status' => 'successful',
                'file_name' => basename($file),
                'size' => filesize($file),
                'checksum' => hash_file('sha256', $file),
                'completed_at' => now(),
            ]);
            $this->info('Database backup written: '.basename($file));

            return true;
        } finally {
            @unlink($optionFile);
        }
    }

    private function backupFiles(string $dir, string $stamp): bool
    {
        $record = BackupRecord::query()->create(['type' => 'files', 'method' => 'command', 'status' => 'failed', 'started_at' => now(), 'location' => 'storage/app/private/backups']);
        $file = "{$dir}/ndc-files-{$stamp}.tar.gz";
        $process = new Process(['tar', '-czf', $file, '--exclude=./backups', '-C', storage_path('app/private'), '.'], null, null, null, 3600);
        $process->run();

        if (! $process->isSuccessful()) {
            @unlink($file);
            $record->update(['completed_at' => now(), 'notes' => \Illuminate\Support\Str::limit(trim($process->getErrorOutput()), 1000)]);
            $this->error('File backup failed.');

            return false;
        }

        $record->update(['status' => 'successful', 'file_name' => basename($file), 'size' => filesize($file), 'checksum' => hash_file('sha256', $file), 'completed_at' => now()]);
        $this->info('File backup written: '.basename($file));

        return true;
    }

    private function prune(string $dir, int $keep): void
    {
        foreach (['ndc-db-*.sql.gz', 'ndc-files-*.tar.gz'] as $pattern) {
            $files = glob($dir.'/'.$pattern) ?: [];
            rsort($files); // newest first (timestamped names)
            foreach (array_slice($files, $keep) as $old) {
                @unlink($old);
            }
        }
    }
}
