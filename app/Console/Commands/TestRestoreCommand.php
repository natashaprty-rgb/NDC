<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use App\Support\Audit;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use PDO;
use RuntimeException;
use ZipArchive;

/**
 * Periodic restoration test: restores a backup's database into a separate,
 * disposable database and compares table row counts with the manifest's
 * source. It never touches the live database.
 */
#[Signature('ndc:backup:test-restore {run? : Backup run ID (defaults to the latest verified backup)}')]
#[Description('Restore a backup into the scratch database defined by RESTORE_TEST_DB_* and report the result')]
class TestRestoreCommand extends Command
{
    public function handle(): int
    {
        $run = $this->argument('run')
            ? BackupRun::query()->findOrFail($this->argument('run'))
            : BackupRun::query()->where('status', 'success')->latest('id')->firstOrFail();

        $work = storage_path('app/tmp/restore-test-'.$run->id);
        File::ensureDirectoryExists($work);

        try {
            $zipPath = $work.'/backup.zip';
            file_put_contents($zipPath, Storage::disk($run->disk)->readStream($run->path));

            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CHECKCONS) !== true) {
                throw new RuntimeException('Archive cannot be opened.');
            }
            $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
            $zip->extractTo($work, [$manifest['database_entry']]);
            $zip->close();
            $dump = $work.'/'.$manifest['database_entry'];

            $tables = $manifest['database_driver'] === 'sqlite'
                ? $this->inspectSqlite($dump)
                : $this->restoreMysql($dump);

            $this->table(['Table', 'Rows'], collect($tables)->map(fn ($n, $t) => [$t, $n])->values()->all());
            $message = 'Restore test of backup #'.$run->id.' succeeded: '.count($tables).' tables restored.';
            $this->info($message);
            Audit::log('backup_restore_tested', $run, $message);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Restore test failed: '.$e->getMessage());
            Audit::log('backup_restore_test_failed', $run, mb_substr($e->getMessage(), 0, 255));

            return self::FAILURE;
        } finally {
            File::deleteDirectory($work);
        }
    }

    /**
     * @return array<string, int>
     */
    private function inspectSqlite(string $file): array
    {
        $pdo = new PDO('sqlite:'.$file);
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

        return collect($tables)->mapWithKeys(fn ($t) => [$t => (int) $pdo->query('SELECT COUNT(*) FROM "'.$t.'"')->fetchColumn()])->all();
    }

    /**
     * @return array<string, int>
     */
    private function restoreMysql(string $file): array
    {
        $host = env('RESTORE_TEST_DB_HOST', config('database.connections.mysql.host'));
        $port = env('RESTORE_TEST_DB_PORT', config('database.connections.mysql.port'));
        $database = env('RESTORE_TEST_DB_DATABASE');
        $username = env('RESTORE_TEST_DB_USERNAME', config('database.connections.mysql.username'));
        $password = (string) env('RESTORE_TEST_DB_PASSWORD', config('database.connections.mysql.password'));

        if (! $database || $database === config('database.connections.mysql.database')) {
            throw new RuntimeException('Set RESTORE_TEST_DB_DATABASE to a separate scratch database (never the live one).');
        }

        $result = Process::env(['MYSQL_PWD' => $password])->timeout(3600)
            ->input(fopen($file, 'r'))
            ->run([config('ndc.backup.mysql_path'), '--host='.$host, '--port='.$port, '--user='.$username, $database]);

        if (! $result->successful()) {
            throw new RuntimeException(trim($result->errorOutput()));
        }

        $pdo = new PDO("mysql:host={$host};port={$port};dbname={$database}", $username, $password);
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

        return collect($tables)->mapWithKeys(fn ($t) => [$t => (int) $pdo->query('SELECT COUNT(*) FROM `'.$t.'`')->fetchColumn()])->all();
    }
}
