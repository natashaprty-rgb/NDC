<?php

namespace Tests\Feature\Core;

use App\Models\BackupRun;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_in_memory_database_backup_is_recorded_as_failed_not_assumed(): void
    {
        Storage::fake('backups');
        $run = app(BackupService::class)->run();

        // The test suite uses an in-memory database, which cannot be dumped:
        // the run must be marked failed rather than reported as successful.
        $this->assertSame('failed', $run->status);
        $this->assertNotEmpty($run->message);
    }

    public function test_verification_detects_tampered_archive(): void
    {
        Storage::fake('backups');
        Storage::disk('backups')->put('fake.zip', 'not a zip');
        $run = BackupRun::query()->create(['status' => 'unverified', 'disk' => 'backups', 'path' => 'fake.zip', 'checksum' => hash('sha256', 'not a zip')]);

        app(BackupService::class)->verify($run);

        $this->assertSame('unverified', $run->fresh()->status);
        $this->assertStringContainsString('FAILED', $run->fresh()->verification_result);
    }

    public function test_only_system_managers_can_run_backups(): void
    {
        $this->actingAs($this->userWithRole('Project Manager'))->post(route('admin.backups.store'))->assertForbidden();
    }
}
