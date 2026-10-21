<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Throwable;

/**
 * Checks an installation and explains how to fix each problem. Useful when
 * the site shows "500 Server Error" with debugging switched off.
 */
#[Signature('ndc:doctor')]
#[Description('Check the installation (PHP, .env, database, migrations, first administrator) and explain fixes')]
class DoctorCommand extends Command
{
    /** @var list<array{0: string, 1: string, 2: string}> */
    private array $rows = [];

    private bool $failed = false;

    public function handle(): int
    {
        $this->checkPhp();
        $this->checkEnvironment();
        $databaseOk = $this->checkDatabase();

        if ($databaseOk) {
            $this->checkSchema();
        }

        $this->checkStorage();

        $this->table(['Check', 'Result', 'Detail / how to fix'], $this->rows);

        if ($this->failed) {
            $this->error('Fix the items marked FAIL, then run php artisan ndc:doctor again.');
            $this->line('Setup guide: docs/LOCAL_SETUP.md (local computer) or docs/INSTALLATION.md (server).');

            return self::FAILURE;
        }

        $this->info('Everything needed to run the application is in place.');

        return self::SUCCESS;
    }

    private function checkPhp(): void
    {
        $this->add('PHP version', version_compare(PHP_VERSION, '8.3.0', '>='), PHP_VERSION, 'PHP 8.3 or newer is required.');

        $required = ['pdo_mysql', 'mbstring', 'openssl', 'fileinfo', 'intl', 'zip', 'gd', 'sodium'];
        $missing = array_values(array_filter($required, fn ($ext) => ! extension_loaded($ext)));
        $this->add('PHP extensions', $missing === [], $missing === [] ? 'all present' : 'missing: '.implode(', ', $missing),
            'Enable them in php.ini (XAMPP: remove the ";" before extension=... and restart).');
    }

    private function checkEnvironment(): void
    {
        $this->add('.env file', is_file(base_path('.env')), base_path('.env'), 'Copy .env.example to .env.');
        $this->add('APP_KEY', filled(config('app.key')), filled(config('app.key')) ? 'set' : 'empty', 'Run: php artisan key:generate');

        $url = (string) config('app.url');
        $local = str_contains($url, '127.0.0.1') || str_contains($url, 'localhost');
        if ($local && str_starts_with($url, 'https://')) {
            $this->addWarning('APP_URL', "{$url} uses https on a local address; use http://127.0.0.1:8000 for php artisan serve.");
        } else {
            $this->add('APP_URL', true, $url, '');
        }

        if (app()->isProduction() && config('app.debug')) {
            $this->add('APP_DEBUG', false, 'true in production', 'Set APP_DEBUG=false in production.');
        } else {
            $this->add('APP_ENV / APP_DEBUG', true, config('app.env').' / '.(config('app.debug') ? 'true' : 'false'), '');
        }
    }

    private function checkDatabase(): bool
    {
        $name = config('database.default');
        $config = config("database.connections.{$name}");
        $target = $config['driver'] === 'sqlite'
            ? 'sqlite: '.$config['database']
            : "{$config['driver']}://{$config['username']}@{$config['host']}:{$config['port']}/{$config['database']}";

        try {
            DB::connection()->getPdo();
            $this->add('Database connection', true, $target, '');

            return true;
        } catch (Throwable $e) {
            $message = $e->getMessage();
            $hint = match (true) {
                str_contains($message, 'Connection refused'), str_contains($message, '2002') => 'MySQL is not running or DB_HOST/DB_PORT is wrong. Start MySQL (XAMPP Control Panel → MySQL → Start).',
                str_contains($message, 'Unknown database'), str_contains($message, '1049') => "Database \"{$config['database']}\" does not exist. Create it (phpMyAdmin → New, collation utf8mb4_unicode_ci).",
                str_contains($message, 'Access denied'), str_contains($message, '1045') => 'Wrong DB_USERNAME / DB_PASSWORD. XAMPP default is DB_USERNAME=root with an empty DB_PASSWORD.',
                str_contains($message, 'could not find driver') => 'Enable the pdo_mysql extension in php.ini.',
                default => 'Check the DB_* settings in .env.',
            };
            $this->add('Database connection', false, $target.' — '.mb_substr($message, 0, 120), $hint);

            return false;
        }
    }

    private function checkSchema(): void
    {
        if (! Schema::hasTable('migrations') || ! Schema::hasTable('users')) {
            $this->add('Database tables', false, 'not created', 'Run: php artisan migrate --seed');

            return;
        }

        $ran = DB::table('migrations')->pluck('migration')->all();
        $files = array_map(fn ($f) => basename($f, '.php'), glob(database_path('migrations/*.php')) ?: []);
        $pending = array_diff($files, $ran);
        $this->add('Migrations', $pending === [], $pending === [] ? count($ran).' applied' : count($pending).' pending', 'Run: php artisan migrate');

        if (config('session.driver') === 'database') {
            $this->add('Sessions table', Schema::hasTable(config('session.table', 'sessions')), 'SESSION_DRIVER=database', 'Run: php artisan migrate');
        }

        $permissions = Schema::hasTable('permissions') ? Permission::query()->count() : 0;
        $this->add('Roles and permissions', $permissions > 0, $permissions.' permissions', 'Run: php artisan db:seed');

        $admins = Schema::hasTable('roles') && $permissions > 0
            ? User::role(config('ndc.super_admin_role'))->count()
            : 0;
        $this->add('Super Administrator account', $admins > 0, $admins.' found', 'Run: php artisan ndc:create-admin');
    }

    private function checkStorage(): void
    {
        foreach (['storage/framework/sessions', 'storage/framework/views', 'storage/framework/cache', 'storage/logs', 'storage/app/private', 'bootstrap/cache'] as $dir) {
            $path = base_path($dir);
            if (! is_dir($path)) {
                @mkdir($path, 0775, true);
            }
            if (! is_writable($path)) {
                $this->add('Writable folders', false, $dir, 'Give the web server user write access to storage/ and bootstrap/cache/.');

                return;
            }
        }
        $this->add('Writable folders', true, 'storage/, bootstrap/cache/', '');
    }

    private function add(string $check, bool $ok, string $detail, string $fix): void
    {
        $this->rows[] = [$check, $ok ? 'OK' : 'FAIL', $ok ? $detail : $detail."\n→ ".$fix];
        $this->failed = $this->failed || ! $ok;
    }

    private function addWarning(string $check, string $detail): void
    {
        $this->rows[] = [$check, 'WARN', $detail];
    }
}
