<?php

namespace App\Services;

use App\Models\BackupRun;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use PDO;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Creates and verifies backup archives containing a database dump and all
 * uploaded files (storage/app/private). Each archive carries a manifest with
 * SHA-256 checksums; a backup only counts as successful once the archive has
 * been re-opened and every checksum and the database dump have been verified.
 */
class BackupService
{
    public function run(?User $user = null): BackupRun
    {
        $run = BackupRun::query()->create([
            'type' => 'full',
            'status' => 'running',
            'disk' => config('ndc.backup.disk'),
            'triggered_by' => $user?->id,
            'started_at' => now(),
        ]);

        $workDir = storage_path('app/tmp/backup-'.$run->id);
        File::ensureDirectoryExists($workDir);

        try {
            $dumpFile = $this->dumpDatabase($workDir);
            $archive = $this->buildArchive($workDir, $dumpFile);

            $name = 'ndc-backup-'.now()->format('Ymd-His').'-'.$run->id.'.zip';
            $stream = fopen($archive, 'r');
            Storage::disk($run->disk)->writeStream($name, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            $run->forceFill([
                'path' => $name,
                'size' => filesize($archive),
                'checksum' => hash_file('sha256', $archive),
                'finished_at' => now(),
            ])->save();

            $this->verify($run);
        } catch (Throwable $e) {
            Log::error('Backup failed', ['run' => $run->id, 'error' => $e->getMessage()]);
            $run->forceFill(['status' => 'failed', 'message' => mb_substr($e->getMessage(), 0, 2000), 'finished_at' => now()])->save();
        } finally {
            File::deleteDirectory($workDir);
        }

        if ($run->status !== 'success') {
            $this->notifyFailure($run);
        }

        return $run->refresh();
    }

    /**
     * Re-read the stored archive and check its integrity.
     */
    public function verify(BackupRun $run): BackupRun
    {
        $checks = [];
        $local = storage_path('app/tmp/verify-'.$run->id.'.zip');
        File::ensureDirectoryExists(dirname($local));

        try {
            $disk = Storage::disk($run->disk);
            if (! $run->path || ! $disk->exists($run->path)) {
                throw new RuntimeException(__('Backup archive not found on the backup disk.'));
            }

            file_put_contents($local, $disk->readStream($run->path));
            $checks[] = 'archive downloaded ('.format_bytes(filesize($local)).')';

            if ($run->checksum && hash_file('sha256', $local) !== $run->checksum) {
                throw new RuntimeException(__('Archive checksum does not match the value recorded at creation.'));
            }
            $checks[] = 'archive checksum matches';

            $zip = new ZipArchive;
            if ($zip->open($local, ZipArchive::CHECKCONS) !== true) {
                throw new RuntimeException(__('Archive is corrupt or unreadable.'));
            }

            $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
            if (! is_array($manifest) || empty($manifest['files'])) {
                throw new RuntimeException(__('Archive manifest is missing.'));
            }

            foreach ($manifest['files'] as $entry => $hash) {
                $contents = $zip->getFromName($entry);
                if ($contents === false || hash('sha256', $contents) !== $hash) {
                    throw new RuntimeException(__('File :file failed checksum verification.', ['file' => $entry]));
                }
            }
            $checks[] = count($manifest['files']).' files verified against manifest';

            $this->verifyDump($zip, $manifest, $checks);
            $zip->close();

            $run->forceFill([
                'status' => 'success',
                'verified_at' => now(),
                'verification_result' => implode("\n", $checks),
                'message' => null,
            ])->save();
        } catch (Throwable $e) {
            $checks[] = 'FAILED: '.$e->getMessage();
            $run->forceFill([
                'status' => $run->path ? 'unverified' : 'failed',
                'verification_result' => implode("\n", $checks),
                'message' => mb_substr($e->getMessage(), 0, 2000),
            ])->save();
        } finally {
            File::delete($local);
        }

        return $run;
    }

    /**
     * Delete archives older than the configured retention period. The most
     * recent verified backup is always kept.
     */
    public function prune(): int
    {
        $keep = BackupRun::query()->where('status', 'success')->latest('id')->value('id');
        $cutoff = now()->subDays((int) setting('backup_retention_days', 30));
        $count = 0;

        BackupRun::query()->where('created_at', '<', $cutoff)->where('id', '!=', $keep)->each(function (BackupRun $run) use (&$count) {
            if ($run->path) {
                try {
                    Storage::disk($run->disk)->delete($run->path);
                } catch (Throwable) {
                    // Already gone.
                }
            }
            $run->delete();
            $count++;
        });

        return $count;
    }

    private function dumpDatabase(string $workDir): string
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        return match ($config['driver']) {
            'mysql', 'mariadb' => $this->dumpMysql($config, $workDir),
            'sqlite' => $this->dumpSqlite($config, $workDir),
            default => throw new RuntimeException("Backups are not supported for the {$config['driver']} driver."),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function dumpMysql(array $config, string $workDir): string
    {
        $file = $workDir.'/database.sql';

        $command = [
            config('ndc.backup.mysqldump_path'),
            '--single-transaction', '--quick', '--routines', '--triggers', '--no-tablespaces',
            '--default-character-set=utf8mb4',
            '--host='.$config['host'], '--port='.$config['port'], '--user='.$config['username'],
            '--result-file='.$file,
            $config['database'],
        ];

        // The password is passed through the environment, never on the command line.
        $result = Process::env(['MYSQL_PWD' => (string) $config['password']])->timeout(3600)->run($command);

        if (! $result->successful() || ! is_file($file) || filesize($file) === 0) {
            throw new RuntimeException('mysqldump failed: '.trim($result->errorOutput()));
        }

        return $file;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function dumpSqlite(array $config, string $workDir): string
    {
        $file = $workDir.'/database.sqlite';
        if ($config['database'] === ':memory:') {
            throw new RuntimeException('An in-memory database cannot be backed up.');
        }

        // VACUUM INTO produces a consistent copy even while the app is in use.
        DB::connection()->statement('VACUUM INTO '.DB::connection()->getPdo()->quote($file));

        return $file;
    }

    private function buildArchive(string $workDir, string $dumpFile): string
    {
        $archive = $workDir.'/backup.zip';
        $zip = new ZipArchive;
        if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the backup archive.');
        }

        $files = [];
        $dumpEntry = 'database/'.basename($dumpFile);
        $zip->addFile($dumpFile, $dumpEntry);
        $files[$dumpEntry] = hash_file('sha256', $dumpFile);

        $filesRoot = storage_path('app/private');
        if (is_dir($filesRoot)) {
            foreach (File::allFiles($filesRoot) as $file) {
                $entry = 'files/'.str_replace('\\', '/', $file->getRelativePathname());
                $zip->addFile($file->getPathname(), $entry);
                $files[$entry] = hash_file('sha256', $file->getPathname());
            }
        }

        $zip->addFromString('manifest.json', json_encode([
            'application' => config('app.name'),
            'created_at' => now()->toIso8601String(),
            'database_driver' => config('database.connections.'.config('database.default').'.driver'),
            'database_entry' => $dumpEntry,
            'migrations' => DB::table('migrations')->count(),
            'files' => $files,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if (! $zip->close()) {
            throw new RuntimeException('Could not finalise the backup archive.');
        }

        return $archive;
    }

    /**
     * Check the database dump looks complete: for SQLite the copy is opened
     * and its migration count compared; for MySQL the dump must contain the
     * schema of core tables and finish with mysqldump's completion marker.
     *
     * @param  array<string, mixed>  $manifest
     * @param  list<string>  $checks
     */
    private function verifyDump(ZipArchive $zip, array $manifest, array &$checks): void
    {
        $contents = (string) $zip->getFromName($manifest['database_entry']);

        if ($manifest['database_driver'] === 'sqlite') {
            $tmp = tempnam(sys_get_temp_dir(), 'ndcdb');
            file_put_contents($tmp, $contents);
            try {
                $pdo = new PDO('sqlite:'.$tmp);
                $migrations = (int) $pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn();
                $users = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            } finally {
                $pdo = null;
                @unlink($tmp);
            }
            if ($migrations !== (int) $manifest['migrations']) {
                throw new RuntimeException('Database copy is incomplete (migration count mismatch).');
            }
            $checks[] = "database copy opens: {$migrations} migrations, {$users} users";

            return;
        }

        foreach (['`users`', '`migrations`', '`audit_logs`'] as $table) {
            if (! str_contains($contents, 'CREATE TABLE '.$table)) {
                throw new RuntimeException("Database dump is missing table {$table}.");
            }
        }
        if (! str_contains(substr($contents, -500), 'Dump completed')) {
            throw new RuntimeException('Database dump did not complete.');
        }
        $checks[] = 'database dump contains core tables and completion marker';
    }

    private function notifyFailure(BackupRun $run): void
    {
        $admins = User::query()->active()->get()->filter(fn (User $u) => $u->can('system.manage'));

        Notification::send($admins, new GenericNotification(
            'backup_failed',
            'Backup failed',
            'Backup #:id did not complete verification: :message',
            ['id' => (string) $run->id, 'message' => (string) $run->message],
            route('admin.backups.index'),
        ));
    }
}
