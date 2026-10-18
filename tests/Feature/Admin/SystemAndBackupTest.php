<?php

namespace Tests\Feature\Admin;

use App\Models\BackupRecord;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SystemAndBackupTest extends TestCase
{
    public function test_system_health_page_never_reveals_secrets(): void
    {
        $admin = $this->userWithRole('system-admin');

        $response = $this->actingAs($admin)->get('/admin/system')->assertOk()->assertSee(__('system.check.database'));

        $response->assertDontSee((string) config('database.connections.mysql.password'));
        $response->assertDontSee((string) config('app.key'));
    }

    public function test_system_and_backup_pages_require_permission(): void
    {
        $director = $this->userWithRole('director');

        $this->actingAs($director)->get('/admin/system')->assertForbidden();
        $this->actingAs($director)->get('/admin/backups')->assertForbidden();
        $this->actingAs($director)->post('/admin/backups', [])->assertForbidden();
    }

    public function test_manual_backup_and_restore_test_can_be_recorded(): void
    {
        $admin = $this->userWithRole('system-admin');

        $this->actingAs($admin)->post('/admin/backups', [
            'type' => 'database', 'status' => 'successful', 'location' => 'Off-site encrypted disk 2',
            'file_name' => 'ndc-2026-10-01.sql.gz', 'completed_at' => now()->subDay()->format('Y-m-d H:i'),
        ])->assertSessionHasNoErrors();

        $record = BackupRecord::query()->firstOrFail();
        $this->assertSame('manual', $record->method);

        $this->actingAs($admin)->put("/admin/backups/{$record->id}/restore-test", ['restore_test_result' => 'passed', 'restore_tested_at' => today()->toDateString()])
            ->assertSessionHasNoErrors();
        $this->assertSame('passed', $record->fresh()->restore_test_result);
    }

    public function test_backup_location_cannot_contain_credentials(): void
    {
        $admin = $this->userWithRole('system-admin');
        $this->actingAs($admin)->post('/admin/backups', [
            'type' => 'database', 'status' => 'successful', 'location' => 'sftp://host?password=hunter2', 'completed_at' => now()->format('Y-m-d H:i'),
        ])->assertSessionHasErrors('location');
    }

    public function test_backup_command_creates_compressed_dump_and_record(): void
    {
        if (trim((string) shell_exec('command -v mysqldump')) === '') {
            $this->markTestSkipped('mysqldump is not installed in this environment.');
        }

        // The command reads the connection that the test suite uses (isolated test database).
        $this->artisan('ndc:backup', ['--keep' => 50])->assertSuccessful();

        $record = BackupRecord::query()->where('type', 'database')->latest('id')->firstOrFail();
        $this->assertSame('successful', $record->status);
        $path = storage_path('app/private/backups/'.$record->file_name);
        $this->assertFileExists($path);
        $this->assertSame($record->checksum, hash_file('sha256', $path));
        $this->assertStringContainsString('CREATE TABLE', (string) gzdecode((string) file_get_contents($path)));

        File::delete($path);
    }
}
