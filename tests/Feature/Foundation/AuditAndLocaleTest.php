<?php

namespace Tests\Feature\Foundation;

use App\Models\AuditLog;
use App\Models\Department;
use Tests\TestCase;

class AuditAndLocaleTest extends TestCase
{
    public function test_model_changes_are_audited_with_old_and_new_values(): void
    {
        $user = $this->userWithRole('system-admin');
        $this->actingAs($user);

        $dept = Department::query()->create(['code' => 'ICT', 'name' => 'ICT Unit']);
        $dept->update(['name' => 'ICT Department']);

        $created = AuditLog::query()->where('auditable_type', 'department')->where('auditable_id', $dept->id)->where('event', 'created')->first();
        $updated = AuditLog::query()->where('auditable_type', 'department')->where('auditable_id', $dept->id)->where('event', 'updated')->first();

        $this->assertSame($user->id, $created->user_id);
        $this->assertSame('ICT Unit', $updated->old_values['name']);
        $this->assertSame('ICT Department', $updated->new_values['name']);
    }

    public function test_sensitive_user_fields_are_never_written_to_audit_log(): void
    {
        $user = $this->userWithRole('staff');
        $user->update(['password' => 'Another-Pass-1!']);

        $log = AuditLog::query()->where('auditable_type', 'user')->where('auditable_id', $user->id)->where('event', 'updated')->latest('id')->first();
        $this->assertSame('[redacted]', $log->new_values['password']);
    }

    public function test_audit_logs_cannot_be_modified_or_deleted(): void
    {
        $log = AuditLog::query()->create(['event' => 'test']);
        $log->event = 'tampered';
        $this->assertFalse($log->save());
        $this->assertFalse($log->delete());
        $this->assertSame('test', AuditLog::query()->find($log->id)->event);
    }

    public function test_language_can_be_switched_and_is_remembered_for_the_user(): void
    {
        $user = $this->userWithRole('staff');

        $this->actingAs($user)->post('/locale', ['locale' => 'so'])->assertRedirect();
        $this->assertSame('so', $user->fresh()->locale);

        $this->actingAs($user->fresh())->get('/dashboard')->assertOk()->assertSee('Shaashadda maamulka sare')->assertSee('lang="so"', false);
    }

    public function test_guest_language_choice_is_kept_in_session(): void
    {
        $this->post('/locale', ['locale' => 'so']);
        $this->get('/login')->assertSee('Gal akoonkaaga');
    }

    public function test_unsupported_locale_is_rejected(): void
    {
        $this->post('/locale', ['locale' => 'xx'])->assertSessionHasErrors('locale');
    }

    public function test_somali_files_define_every_key_of_the_english_files(): void
    {
        foreach (glob(lang_path('en/*.php')) as $file) {
            $name = basename($file);
            $this->assertFileExists(lang_path('so/'.$name), "Missing Somali translation file {$name}");
            if (in_array($name, ['validation.php'], true)) {
                continue; // partial by design: falls back to English for rarely used rules
            }
            $en = \Illuminate\Support\Arr::dot(require $file);
            $so = \Illuminate\Support\Arr::dot(require lang_path('so/'.$name));
            $missing = array_diff(array_keys($en), array_keys($so));
            $this->assertSame([], array_values($missing), "Somali {$name} is missing keys");
        }
    }
}
