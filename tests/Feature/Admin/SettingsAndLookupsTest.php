<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\DocumentCategory;
use App\Models\Location;
use App\Support\ReferenceNumber;
use App\Support\Settings;
use Tests\TestCase;

class SettingsAndLookupsTest extends TestCase
{
    public function test_admin_can_update_settings_and_change_is_audited(): void
    {
        $admin = $this->userWithRole('system-admin');

        $this->actingAs($admin)->put('/admin/settings/org', [
            'org__name' => 'National Data Center Somalia',
            'org__short_name' => 'NDC',
            'org__email' => 'info@example.test',
        ])->assertRedirect(route('admin.settings.edit', ['tab' => 'org']));

        Settings::flush();
        $this->assertSame('info@example.test', Settings::get('org.email'));
        $this->assertTrue(AuditLog::query()->where('event', 'settings_updated')->exists());
    }

    public function test_numbering_settings_drive_new_reference_numbers(): void
    {
        $admin = $this->userWithRole('super-admin');
        $payload = ['numbering__org_prefix' => 'NDCS', 'numbering__padding' => 5];
        foreach (config('ndc.settings_defaults') as $key => $default) {
            if (str_starts_with($key, 'numbering.prefix.')) {
                $payload[str_replace('.', '__', $key)] = $default;
            }
        }
        $payload['numbering__prefix__project'] = 'PROJ';

        $this->actingAs($admin)->put('/admin/settings/numbering', $payload)->assertSessionHasNoErrors();
        Settings::flush();

        $this->assertSame('NDCS-PROJ-2026-00001', ReferenceNumber::next('project', 2026));
    }

    public function test_invalid_settings_are_rejected(): void
    {
        $admin = $this->userWithRole('system-admin');

        $this->actingAs($admin)->put('/admin/settings/formats', [
            'locale__default' => 'fr', 'format__date' => 'Y', 'format__datetime' => 'd/m/Y H:i', 'format__currency' => 'dollars',
        ])->assertSessionHasErrors(['locale__default', 'format__date', 'format__currency']);

        $this->actingAs($admin)->put('/admin/settings/numbering', ['numbering__org_prefix' => 'bad prefix!'])->assertSessionHasErrors('numbering__org_prefix');
    }

    public function test_upload_policy_only_accepts_known_extensions(): void
    {
        $admin = $this->userWithRole('system-admin');

        $this->actingAs($admin)->put('/admin/settings/uploads', [
            'uploads__max_kb' => 5000,
            'uploads__allowed_extensions' => ['pdf', 'exe', 'php', 'png'],
        ])->assertSessionHasNoErrors();
        Settings::flush();

        $this->assertSame('pdf,png', Settings::get('uploads.allowed_extensions'));
        $this->assertSame(['pdf', 'png'], \App\Services\FileStorage::allowedExtensions());
    }

    public function test_settings_and_lookups_require_permission(): void
    {
        $director = $this->userWithRole('director');

        $this->actingAs($director)->get('/admin/settings')->assertForbidden();
        $this->actingAs($director)->put('/admin/settings/org', ['org__name' => 'X', 'org__short_name' => 'X'])->assertForbidden();
        $this->actingAs($director)->get('/admin/lookups/locations')->assertForbidden();
        $this->actingAs($director)->post('/admin/lookups/locations', ['code' => 'L1', 'name' => 'X'])->assertForbidden();
    }

    public function test_lookup_entries_can_be_created_edited_and_deactivated(): void
    {
        $admin = $this->userWithRole('system-admin');

        $this->actingAs($admin)->post('/admin/lookups/locations', ['code' => 'HQ-1', 'name' => 'Head office, room 1', 'is_active' => '1'])
            ->assertRedirect(route('admin.lookups.index', 'locations'));
        $location = Location::query()->where('code', 'HQ-1')->firstOrFail();

        $this->actingAs($admin)->put("/admin/lookups/locations/{$location->id}", ['code' => 'HQ-1', 'name' => 'Head office, room 1', 'is_active' => '0'])
            ->assertRedirect();
        $this->assertFalse($location->fresh()->is_active);

        DocumentCategory::query()->create(['code' => 'POL', 'name' => 'Policies']);
        $this->actingAs($admin)->post('/admin/lookups/document-categories', ['code' => 'POL', 'name' => 'Duplicate code'])
            ->assertSessionHasErrors('code');
        $this->actingAs($admin)->post('/admin/lookups/document-categories', ['code' => 'LEG', 'name' => 'Legal', 'retention_years' => 10, 'is_active' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertSame(10, DocumentCategory::query()->where('code', 'LEG')->value('retention_years'));
    }

    public function test_unknown_lookup_type_returns_404(): void
    {
        $admin = $this->userWithRole('system-admin');
        $this->actingAs($admin)->get('/admin/lookups/users')->assertNotFound();
    }

    public function test_settings_page_renders_all_tabs(): void
    {
        $admin = $this->userWithRole('system-admin');
        foreach (['org', 'formats', 'numbering', 'uploads', 'notifications', 'retention', 'security'] as $tab) {
            $this->actingAs($admin)->get('/admin/settings?tab='.$tab)->assertOk()->assertSee(__('settings.groups.'.$tab));
        }
    }
}
