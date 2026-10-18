<?php

namespace Tests\Feature\Documents;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentAccess;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    private const PDF = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function pdf(string $name = 'annual-report.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, self::PDF);
    }

    private function register(User $user, array $overrides = []): Document
    {
        $this->actingAs($user)->post('/documents', array_merge([
            'title' => 'Strategic plan',
            'registration_date' => today()->toDateString(),
            'confidentiality' => 'internal',
            'file' => $this->pdf(),
        ], $overrides))->assertSessionHasNoErrors()->assertRedirect();

        return Document::query()->latest('id')->firstOrFail();
    }

    public function test_document_is_registered_with_first_version_on_private_disk(): void
    {
        $officer = $this->userWithRole('document-officer');
        $document = $this->register($officer);

        $this->assertMatchesRegularExpression('/^NDC-DOC-\d{4}-0001$/', $document->reference);
        $this->assertSame(1, $document->current_version);
        $this->assertSame($officer->id, $document->owner_id);
        $this->assertSame('draft', $document->status);

        $version = $document->versions()->first();
        $this->assertSame('annual-report.pdf', $version->original_name);
        $this->assertSame('application/pdf', $version->mime_type);
        Storage::disk('local')->assertExists($version->path);
        $this->assertStringStartsWith('documents/', $version->path);
        $this->assertStringNotContainsString('annual-report', $version->path, 'Stored name must be random');
        $this->assertFileDoesNotExist(public_path($version->path));
        $this->assertSame(hash('sha256', self::PDF), $version->checksum);
    }

    public function test_upload_rejects_disallowed_extension_and_content_mismatch(): void
    {
        $officer = $this->userWithRole('document-officer');
        $base = ['title' => 'X', 'registration_date' => today()->toDateString(), 'confidentiality' => 'internal'];

        $this->actingAs($officer)->post('/documents', $base + ['file' => UploadedFile::fake()->createWithContent('tool.exe', "MZ\x90\x00binary")])
            ->assertSessionHasErrors('file');
        $this->actingAs($officer)->post('/documents', $base + ['file' => UploadedFile::fake()->createWithContent('fake.pdf', 'just plain text, not a pdf')])
            ->assertSessionHasErrors('file');
        $this->actingAs($officer)->post('/documents', $base)->assertSessionHasErrors('file');

        $this->assertSame(0, Document::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_validation_rejects_expiry_before_registration_and_unknown_confidentiality(): void
    {
        $officer = $this->userWithRole('document-officer');
        $this->actingAs($officer)->post('/documents', [
            'title' => '', 'registration_date' => '2026-05-01', 'expiry_date' => '2026-04-01',
            'confidentiality' => 'top-secret', 'file' => $this->pdf(),
        ])->assertSessionHasErrors(['title', 'expiry_date', 'confidentiality']);
    }

    public function test_user_without_create_permission_cannot_register(): void
    {
        $staff = $this->userWithRole('staff');
        $this->actingAs($staff)->get('/documents/create')->assertForbidden();
        $this->actingAs($staff)->post('/documents', ['title' => 'X', 'registration_date' => today()->toDateString(), 'confidentiality' => 'internal', 'file' => $this->pdf()])
            ->assertForbidden();
    }

    public function test_confidential_document_is_hidden_from_other_departments(): void
    {
        $owner = $this->userWithRole('project-officer');
        $ownDept = $this->placeInDepartment($owner);
        $document = $this->register($owner, ['title' => 'Secret budget memo', 'confidentiality' => 'confidential', 'department_id' => $ownDept->id]);
        $version = $document->versions()->first();

        $outsider = $this->userWithRole('staff');
        $this->placeInDepartment($outsider);

        $this->actingAs($outsider)->get('/documents')->assertOk()->assertDontSee('Secret budget memo');
        $this->actingAs($outsider)->get("/documents/{$document->id}")->assertForbidden();
        $this->actingAs($outsider)->get("/documents/{$document->id}/versions/{$version->id}/download")->assertForbidden();

        $colleague = $this->userWithRole('staff');
        $this->placeInDepartment($colleague, $ownDept);
        $this->actingAs($colleague)->get('/documents')->assertSee('Secret budget memo');
        $this->actingAs($colleague)->get("/documents/{$document->id}")->assertOk();

        $this->actingAs($owner)->get("/documents/{$document->id}/versions/{$version->id}/download")->assertOk();
    }

    public function test_restricted_document_requires_grant_or_permission(): void
    {
        $owner = $this->userWithRole('project-officer');
        $dept = $this->placeInDepartment($owner);
        $document = $this->register($owner, ['title' => 'Restricted file', 'confidentiality' => 'restricted', 'department_id' => $dept->id]);

        $colleague = $this->userWithRole('staff');
        $this->placeInDepartment($colleague, $dept);
        $this->actingAs($colleague)->get("/documents/{$document->id}")->assertForbidden();

        $director = $this->userWithRole('director');
        $this->actingAs($director)->get("/documents/{$document->id}")->assertOk();

        // Owner grants explicit access to the colleague
        $this->actingAs($owner)->post("/documents/{$document->id}/access", ['user_id' => $colleague->id, 'level' => 'view'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('document_access', ['document_id' => $document->id, 'user_id' => $colleague->id, 'level' => 'view']);
        $this->actingAs($colleague->fresh())->get("/documents/{$document->id}")->assertOk();
        $this->actingAs($colleague->fresh())->get('/documents')->assertSee('Restricted file');

        // ... and revokes it
        $grant = DocumentAccess::query()->firstOrFail();
        $this->actingAs($owner)->delete("/documents/{$document->id}/access/{$grant->id}");
        $this->actingAs($colleague->fresh())->get("/documents/{$document->id}")->assertForbidden();

        // Someone without manageAccess cannot grant
        $this->actingAs($director)->post("/documents/{$document->id}/access", ['user_id' => $director->id, 'level' => 'edit'])->assertForbidden();
    }

    public function test_download_is_audited_and_never_inline_for_unsafe_types(): void
    {
        $officer = $this->userWithRole('document-officer');
        $document = $this->register($officer);
        $version = $document->versions()->first();

        $response = $this->actingAs($officer)->get("/documents/{$document->id}/versions/{$version->id}/download");
        $response->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertSame(self::PDF, $response->streamedContent());

        $preview = $this->actingAs($officer)->get("/documents/{$document->id}/versions/{$version->id}/download?preview=1");
        $this->assertStringContainsString('inline', $preview->headers->get('Content-Disposition'));

        $this->assertTrue(AuditLog::query()->where('auditable_type', 'document')->where('auditable_id', $document->id)->where('event', 'downloaded')->exists());
    }

    public function test_version_of_another_document_cannot_be_fetched_through_this_document(): void
    {
        $officer = $this->userWithRole('document-officer');
        $public = $this->register($officer, ['title' => 'Public one']);
        $secret = $this->register($officer, ['title' => 'Restricted one', 'confidentiality' => 'restricted']);
        $secretVersion = $secret->versions()->first();

        $reader = $this->userWithRole('staff');
        $this->actingAs($reader)->get("/documents/{$public->id}/versions/{$secretVersion->id}/download")->assertNotFound();
    }

    public function test_review_workflow_prevents_self_approval(): void
    {
        $owner = $this->userWithRole('department-head'); // holds documents.approve
        $document = $this->register($owner);
        $approver = $this->userWithRole('director');

        $this->actingAs($owner)->post("/documents/{$document->id}/submit")->assertSessionHasNoErrors();
        $this->assertSame('pending_review', $document->fresh()->status);

        $this->actingAs($owner)->post("/documents/{$document->id}/approve")->assertForbidden();
        $this->assertSame('pending_review', $document->fresh()->status);

        $this->assertDatabaseHas('notifications', ['notifiable_id' => $approver->id]);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $owner->id]);

        $this->actingAs($approver)->post("/documents/{$document->id}/reject", ['review_note' => ''])->assertSessionHasErrors('review_note');
        $this->actingAs($approver)->post("/documents/{$document->id}/approve")->assertSessionHasNoErrors();

        $fresh = $document->fresh();
        $this->assertSame('approved', $fresh->status);
        $this->assertSame($approver->id, $fresh->approved_by);
        $this->assertNotNull($fresh->approved_at);
    }

    public function test_rejection_returns_document_with_note(): void
    {
        $owner = $this->userWithRole('project-officer');
        $document = $this->register($owner);
        $this->actingAs($owner)->post("/documents/{$document->id}/submit");

        $this->actingAs($this->userWithRole('director'))->post("/documents/{$document->id}/reject", ['review_note' => 'Missing annex'])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $document->fresh()->status);
        $this->assertSame('Missing annex', $document->fresh()->review_note);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $owner->id]);
    }

    public function test_new_version_increments_number_and_resets_approval(): void
    {
        $owner = $this->userWithRole('project-officer');
        $document = $this->register($owner);
        $document->forceFill(['status' => 'approved', 'approved_by' => $this->userWithRole('director')->id, 'approved_at' => now()])->save();

        $this->actingAs($owner)->post("/documents/{$document->id}/versions", ['file' => $this->pdf('v2.pdf'), 'change_note' => 'Updated figures'])
            ->assertSessionHasNoErrors();

        $fresh = $document->fresh();
        $this->assertSame(2, $fresh->current_version);
        $this->assertSame('draft', $fresh->status);
        $this->assertNull($fresh->approved_by);
        $this->assertSame([2, 1], $fresh->versions()->pluck('version_number')->all());

        // A reader cannot upload versions
        $this->actingAs($this->userWithRole('staff'))->post("/documents/{$document->id}/versions", ['file' => $this->pdf()])->assertForbidden();
    }

    public function test_archive_hides_document_from_default_list_and_blocks_edits(): void
    {
        $officer = $this->userWithRole('document-officer');
        $document = $this->register($officer, ['title' => 'Old circular']);

        $this->actingAs($officer)->post("/documents/{$document->id}/archive")->assertRedirect();
        $this->assertSame('archived', $document->fresh()->status);
        $this->actingAs($officer)->get('/documents')->assertDontSee('Old circular');
        $this->actingAs($officer)->get('/documents?status=archived')->assertSee('Old circular');
        $this->actingAs($officer)->get("/documents/{$document->id}/edit")->assertForbidden();

        $this->actingAs($officer)->post("/documents/{$document->id}/restore")->assertRedirect();
        $this->assertSame('draft', $document->fresh()->status);
    }

    public function test_search_and_expiring_filters(): void
    {
        $officer = $this->userWithRole('document-officer');
        $this->register($officer, ['title' => 'Network policy', 'expiry_date' => today()->addDays(10)->toDateString()]);
        $this->register($officer, ['title' => 'Travel guideline']);

        $this->actingAs($officer)->get('/documents?q=Network')->assertSee('Network policy')->assertDontSee('Travel guideline');
        $this->actingAs($officer)->get('/documents?expiring=1')->assertSee('Network policy')->assertDontSee('Travel guideline');
    }

    public function test_metadata_update_is_audited(): void
    {
        $officer = $this->userWithRole('document-officer');
        $document = $this->register($officer);

        $this->actingAs($officer)->put("/documents/{$document->id}", [
            'title' => 'Strategic plan (revised)', 'registration_date' => today()->toDateString(), 'confidentiality' => 'confidential',
        ])->assertRedirect(route('documents.show', $document));

        $this->assertSame('confidential', $document->fresh()->confidentiality);
        $log = AuditLog::query()->where('auditable_type', 'document')->where('auditable_id', $document->id)->where('event', 'updated')->latest('id')->first();
        $this->assertSame('internal', $log->old_values['confidentiality']);
    }

    public function test_pages_render_for_authorised_users(): void
    {
        $officer = $this->userWithRole('document-officer');
        $document = $this->register($officer, ['confidentiality' => 'confidential']);

        $this->actingAs($officer)->get('/documents/create')->assertOk()->assertSee('name="file"', false);
        $this->actingAs($officer)->get("/documents/{$document->id}/edit")->assertOk();
        $this->actingAs($officer)->get("/documents/{$document->id}")->assertOk()
            ->assertSee($document->reference)->assertSee(__('documents.access_grants'))->assertDontSee('documents/20', false);
    }
}
