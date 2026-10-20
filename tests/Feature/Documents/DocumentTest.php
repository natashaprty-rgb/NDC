<?php

namespace Tests\Feature\Documents;

use App\Models\Category;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function pdf(string $name = 'policy.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 100, 'application/pdf');
    }

    /**
     * Upload a document through the controller and return it.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function upload(User $user, array $attributes = []): Document
    {
        $this->actingAs($user)->post(route('documents.store'), $attributes + [
            'title' => 'Data Protection Policy',
            'confidentiality' => 'internal',
            'file' => $this->pdf(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        return Document::query()->latest('id')->firstOrFail();
    }

    /**
     * A document with a stored version 1 file.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function documentWithFile(array $attributes = []): Document
    {
        $document = Document::factory()->create($attributes + ['current_version' => 1]);
        $version = DocumentVersion::factory()->create(['document_id' => $document->id, 'version' => 1]);
        Storage::disk('local')->put($version->path, '%PDF-1.4 test');

        return $document;
    }

    public function test_pages_render(): void
    {
        $admin = $this->superAdmin();
        $document = $this->documentWithFile(['confidentiality' => 'restricted', 'expiry_date' => today()->addDays(5)]);
        Category::query()->create(['type' => 'document', 'name' => 'Policy']);

        foreach (['documents.index', 'documents.create', 'documents.register'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }
        $this->actingAs($admin)->get(route('documents.index', ['archived' => 1, 'expiring' => 'soon', 'sort' => 'title', 'dir' => 'asc']))->assertOk();
        $this->actingAs($admin)->get(route('documents.show', $document))->assertOk()->assertSee($document->title);
        $this->actingAs($admin)->get(route('documents.edit', $document))->assertOk();
    }

    public function test_upload_creates_version_one_with_checksum(): void
    {
        $officer = $this->userWithRole('Document Officer');
        $category = Category::query()->create(['type' => 'document', 'name' => 'Policy']);

        $document = $this->upload($officer, ['category_id' => $category->id, 'description' => 'Approved policy']);

        $this->assertSame('NDC/DOC/'.now()->year.'/0001', $document->reference_number);
        $this->assertSame('draft', $document->status);
        $this->assertSame(1, $document->current_version);
        $this->assertSame($officer->id, $document->owner_id);
        $this->assertSame($officer->id, $document->created_by);

        $version = $document->versions()->firstOrFail();
        $this->assertSame(1, $version->version);
        $this->assertSame('policy.pdf', $version->original_name);
        $this->assertSame(64, strlen((string) $version->checksum));
        $this->assertSame($officer->id, $version->uploaded_by);
        Storage::disk('local')->assertExists($version->path);
        $this->assertStringStartsWith('documents/', $version->path);
    }

    public function test_dangerous_file_types_are_rejected(): void
    {
        $officer = $this->userWithRole('Document Officer');

        foreach (['shell.php' => 'application/x-php', 'setup.exe' => 'application/x-msdownload'] as $name => $mime) {
            $this->actingAs($officer)->post(route('documents.store'), [
                'title' => 'Bad', 'confidentiality' => 'internal',
                'file' => UploadedFile::fake()->create($name, 10, $mime),
            ])->assertSessionHasErrors('file');
        }

        $this->actingAs($officer)->post(route('documents.store'), [
            'title' => '', 'confidentiality' => 'secret',
        ])->assertSessionHasErrors(['title', 'confidentiality', 'file']);

        $this->assertSame(0, Document::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_new_version_increments_current_version(): void
    {
        $officer = $this->userWithRole('Document Officer');
        $document = $this->upload($officer);

        $this->actingAs($officer)->post(route('documents.versions.store', $document), [
            'file' => $this->pdf('policy-v2.pdf'),
        ])->assertSessionHasErrors('change_summary');

        $this->actingAs($officer)->post(route('documents.versions.store', $document), [
            'file' => $this->pdf('policy-v2.pdf'),
            'change_summary' => 'Updated retention section',
        ])->assertRedirect();

        $document->refresh();
        $this->assertSame(2, $document->current_version);
        $this->assertSame(2, $document->versions()->count());
        $this->assertSame('policy-v2.pdf', $document->latestVersion->original_name);
        $this->assertSame(2, $document->latestVersion->version);

        $this->actingAs($officer)->get(route('documents.show', $document))->assertSee('Updated retention section')->assertSee('policy-v2.pdf');

        $this->actingAs($officer)->post(route('documents.versions.store', $document), [
            'file' => UploadedFile::fake()->create('evil.php', 5, 'application/x-php'), 'change_summary' => 'x',
        ])->assertSessionHasErrors('file');
        $this->assertSame(2, $document->fresh()->current_version);
    }

    public function test_download_streams_for_authorised_users_and_logs_access(): void
    {
        $document = $this->documentWithFile();
        $version = $document->versions()->firstOrFail();
        $reader = $this->userWithRole('General Staff');

        $response = $this->actingAs($reader)->get(route('documents.versions.download', [$document, $version]));
        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString($version->original_name, (string) $response->headers->get('Content-Disposition'));

        $this->assertDatabaseHas('document_access_logs', [
            'document_id' => $document->id, 'document_version_id' => $version->id, 'user_id' => $reader->id, 'action' => 'download',
        ]);

        $this->actingAs($reader)->get(route('documents.show', $document))->assertOk();
        $this->assertDatabaseHas('document_access_logs', ['document_id' => $document->id, 'user_id' => $reader->id, 'action' => 'view']);
    }

    public function test_download_forbidden_without_access(): void
    {
        $document = $this->documentWithFile(['confidentiality' => 'confidential']);
        $version = $document->versions()->firstOrFail();

        $this->actingAs($this->userWithRole('General Staff'))
            ->get(route('documents.versions.download', [$document, $version]))->assertForbidden();
        $this->actingAs($this->userWithPermissions(['dashboard.view']))
            ->get(route('documents.versions.download', [$document, $version]))->assertForbidden();

        $this->assertSame(0, $document->accessLogs()->count());
    }

    public function test_version_of_another_document_is_not_found(): void
    {
        $a = $this->documentWithFile();
        $b = $this->documentWithFile(['confidentiality' => 'restricted']);

        $this->actingAs($this->userWithRole('General Staff'))
            ->get(route('documents.versions.download', [$a, $b->versions()->first()]))->assertNotFound();
    }

    public function test_confidential_and_restricted_visibility(): void
    {
        $owner = $this->userWithRole('Project Officer');
        $public = Document::factory()->create(['title' => 'Public Notice', 'confidentiality' => 'public']);
        $confidential = Document::factory()->create(['title' => 'Salary Scale', 'confidentiality' => 'confidential']);
        $restricted = Document::factory()->create(['title' => 'Security Audit', 'confidentiality' => 'restricted', 'owner_id' => $owner->id]);

        $reader = $this->userWithRole('General Staff');
        $this->actingAs($reader)->get(route('documents.index'))->assertOk()
            ->assertSee('Public Notice')->assertDontSee('Salary Scale')->assertDontSee('Security Audit');
        $this->actingAs($reader)->get(route('documents.show', $confidential))->assertForbidden();
        $this->actingAs($reader)->get(route('documents.show', $restricted))->assertForbidden();
        $this->actingAs($reader)->get(route('documents.register'))->assertDontSee('Salary Scale');

        $this->actingAs($owner)->get(route('documents.show', $restricted))->assertOk();
        $this->actingAs($owner)->get(route('documents.index'))->assertSee('Security Audit')->assertDontSee('Salary Scale');

        $auditor = $this->userWithRole('Read-Only Auditor');
        $this->actingAs($auditor)->get(route('documents.index'))->assertSee('Salary Scale')->assertSee('Security Audit');
        $this->actingAs($auditor)->get(route('documents.show', $confidential))->assertOk();
    }

    public function test_owner_can_grant_and_revoke_access_to_restricted_document(): void
    {
        $owner = $this->userWithRole('Project Officer');
        $document = $this->documentWithFile(['confidentiality' => 'restricted', 'owner_id' => $owner->id]);
        $colleague = $this->userWithRole('General Staff');
        $outsider = $this->userWithPermissions(['dashboard.view']);

        $this->actingAs($colleague)->get(route('documents.show', $document))->assertForbidden();
        $this->actingAs($colleague)->post(route('documents.access.store', $document), ['user_id' => $colleague->id])->assertForbidden();

        $this->actingAs($owner)->post(route('documents.access.store', $document), ['user_id' => $outsider->id])
            ->assertSessionHasErrors('user_id');

        $this->actingAs($owner)->post(route('documents.access.store', $document), ['user_id' => $colleague->id])->assertRedirect();
        $this->assertDatabaseHas('document_user', ['document_id' => $document->id, 'user_id' => $colleague->id, 'granted_by' => $owner->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'access_granted', 'auditable_type' => 'document', 'auditable_id' => $document->id]);

        $this->actingAs($colleague)->get(route('documents.show', $document))->assertOk();
        $this->actingAs($colleague)->get(route('documents.index'))->assertSee($document->title);
        $this->actingAs($colleague)->get(route('documents.versions.download', [$document, $document->versions()->first()]))->assertOk();
        // A grantee cannot pass access on.
        $this->actingAs($colleague)->post(route('documents.access.store', $document), ['user_id' => $owner->id])->assertForbidden();

        $this->actingAs($owner)->delete(route('documents.access.destroy', [$document, $colleague]))->assertRedirect();
        $this->assertDatabaseMissing('document_user', ['document_id' => $document->id, 'user_id' => $colleague->id]);
        $this->actingAs($colleague)->get(route('documents.show', $document))->assertForbidden();
    }

    public function test_access_grants_only_apply_to_restricted_documents(): void
    {
        $owner = $this->userWithRole('Project Officer');
        $document = Document::factory()->create(['confidentiality' => 'confidential', 'owner_id' => $owner->id]);

        $this->actingAs($owner)->post(route('documents.access.store', $document), [
            'user_id' => $this->userWithRole('General Staff')->id,
        ])->assertForbidden();
    }

    public function test_approval_workflow_submit_approve_publish(): void
    {
        $officer = $this->userWithRole('Document Officer');
        $head = $this->userWithRole('Department Head');
        $document = $this->upload($officer);

        $this->actingAs($officer)->post(route('documents.publish', $document))->assertForbidden();

        $this->actingAs($officer)->post(route('documents.submit', $document), ['note' => 'Please review'])->assertRedirect();
        $document->refresh();
        $this->assertSame('pending_review', $document->status);
        $approval = $document->pendingApproval()->firstOrFail();
        $this->assertSame('documents.approve', $approval->permission);

        // Cannot submit twice or edit while under review.
        $this->actingAs($officer)->post(route('documents.submit', $document))->assertForbidden();
        $this->actingAs($officer)->get(route('documents.edit', $document))->assertForbidden();

        $this->actingAs($head)->get(route('documents.show', $document))->assertOk()->assertSee(__('Approve'));
        $this->actingAs($head)->post(route('approvals.decide', $approval), ['decision' => 'approve'])->assertRedirect();
        $this->assertSame('approved', $document->fresh()->status);

        $this->actingAs($this->userWithRole('General Staff'))->post(route('documents.publish', $document))->assertForbidden();
        $this->actingAs($head)->post(route('documents.publish', $document))->assertRedirect();
        $document->refresh();
        $this->assertSame('published', $document->status);
        $this->assertNotNull($document->published_at);
        $this->assertDatabaseHas('audit_logs', ['event' => 'published', 'auditable_type' => 'document', 'auditable_id' => $document->id]);
    }

    public function test_requester_cannot_approve_own_document_and_rejection_needs_comment(): void
    {
        $head = $this->userWithRole('Department Head');
        $other = $this->userWithRole('Director or Management');
        $document = $this->upload($head);

        $this->actingAs($head)->post(route('documents.submit', $document))->assertRedirect();
        $approval = $document->pendingApproval()->firstOrFail();

        $this->actingAs($head)->post(route('approvals.decide', $approval), ['decision' => 'approve'])->assertForbidden();
        $this->assertSame('pending_review', $document->fresh()->status);

        $this->actingAs($other)->post(route('approvals.decide', $approval), ['decision' => 'reject'])
            ->assertSessionHasErrors('decision_comment');
        $this->assertSame('pending_review', $document->fresh()->status);

        $this->actingAs($other)->post(route('approvals.decide', $approval), ['decision' => 'reject', 'decision_comment' => 'Missing annex'])->assertRedirect();
        $this->assertSame('rejected', $document->fresh()->status);

        // A rejected document can be corrected and resubmitted.
        $this->actingAs($head)->get(route('documents.edit', $document))->assertOk();
        $this->actingAs($head)->post(route('documents.submit', $document))->assertRedirect();
        $this->assertSame('pending_review', $document->fresh()->status);
    }

    public function test_metadata_update(): void
    {
        $officer = $this->userWithRole('Document Officer');
        $document = $this->upload($officer);

        $this->actingAs($officer)->put(route('documents.update', $document), [
            'title' => 'Data Protection Policy 2026', 'confidentiality' => 'confidential',
            'document_date' => '2026-01-10', 'expiry_date' => '2025-01-01',
        ])->assertSessionHasErrors('expiry_date');

        $this->actingAs($officer)->put(route('documents.update', $document), [
            'title' => 'Data Protection Policy 2026', 'confidentiality' => 'confidential',
            'document_date' => '2026-01-10', 'review_date' => '2027-01-10', 'expiry_date' => '2028-01-10',
        ])->assertRedirect(route('documents.show', $document));

        $document->refresh();
        $this->assertSame('Data Protection Policy 2026', $document->title);
        $this->assertSame('confidential', $document->confidentiality);
        $this->assertSame('2028-01-10', $document->expiry_date->format('Y-m-d'));

        $this->actingAs($this->userWithRole('General Staff'))->put(route('documents.update', $document), [
            'title' => 'Hijack', 'confidentiality' => 'public',
        ])->assertForbidden();
    }

    public function test_archive_and_unarchive(): void
    {
        $officer = $this->userWithRole('Document Officer');
        $document = $this->documentWithFile(['status' => 'published', 'published_at' => now()]);

        $this->actingAs($this->userWithRole('Project Officer'))->post(route('documents.archive', $document))->assertForbidden();

        $this->actingAs($officer)->post(route('documents.archive', $document))->assertRedirect();
        $document->refresh();
        $this->assertSame('archived', $document->status);
        $this->assertNotNull($document->archived_at);

        $this->actingAs($officer)->get(route('documents.index'))->assertDontSee($document->title);
        $this->actingAs($officer)->get(route('documents.index', ['archived' => 1]))->assertSee($document->title);
        $this->actingAs($officer)->get(route('documents.edit', $document))->assertForbidden();
        $this->actingAs($officer)->get(route('documents.versions.download', [$document, $document->versions()->first()]))->assertOk();

        $this->actingAs($officer)->post(route('documents.unarchive', $document))->assertRedirect();
        $document->refresh();
        $this->assertNull($document->archived_at);
        $this->assertSame('published', $document->status);
    }

    public function test_archiving_cancels_pending_approval(): void
    {
        $officer = $this->userWithRole('Document Officer');
        $document = $this->upload($officer);
        $this->actingAs($officer)->post(route('documents.submit', $document))->assertRedirect();

        $this->actingAs($officer)->post(route('documents.archive', $document))->assertRedirect();
        $this->assertFalse($document->fresh()->hasPendingApproval());

        $this->actingAs($officer)->post(route('documents.unarchive', $document))->assertRedirect();
        $this->assertSame('draft', $document->fresh()->status);
    }

    public function test_expiry_filters_and_badges(): void
    {
        $user = $this->userWithRole('General Staff');
        Document::factory()->create(['title' => 'Expiring Licence', 'expiry_date' => today()->addDays(10)]);
        Document::factory()->create(['title' => 'Expired Contract', 'expiry_date' => today()->subDay()]);
        Document::factory()->create(['title' => 'Long Term Plan', 'expiry_date' => today()->addYear()]);
        Document::factory()->create(['title' => 'Review Me', 'review_date' => today()->subDays(2)]);

        $this->actingAs($user)->get(route('documents.index', ['expiring' => 'soon']))->assertOk()
            ->assertSee('Expiring Licence')->assertDontSee('Expired Contract')->assertDontSee('Long Term Plan');
        $this->actingAs($user)->get(route('documents.index', ['expiring' => 'expired']))
            ->assertSee('Expired Contract')->assertDontSee('Expiring Licence');
        $this->actingAs($user)->get(route('documents.index', ['expiring' => 'review']))
            ->assertSee('Review Me')->assertDontSee('Long Term Plan');

        $expired = Document::query()->where('title', 'Expired Contract')->first();
        $this->actingAs($user)->get(route('documents.show', $expired))->assertSee(__('Expired'));
    }

    public function test_register_filters_by_search_and_metadata(): void
    {
        $user = $this->userWithRole('General Staff');
        $category = Category::query()->create(['type' => 'document', 'name' => 'Contract']);
        Document::factory()->create(['title' => 'Fibre Contract', 'category_id' => $category->id]);
        Document::factory()->create(['title' => 'Annual Report']);

        $this->actingAs($user)->get(route('documents.index', ['category' => $category->id]))
            ->assertSee('Fibre Contract')->assertDontSee('Annual Report');
        $this->actingAs($user)->get(route('documents.index', ['q' => 'Annual']))
            ->assertSee('Annual Report')->assertDontSee('Fibre Contract');
        $this->actingAs($user)->get(route('documents.register', ['q' => 'Fibre']))
            ->assertOk()->assertSee('Fibre Contract')->assertDontSee('Annual Report');
    }

    public function test_access_log_visible_to_owner_and_approvers_only(): void
    {
        $owner = $this->userWithRole('Project Officer');
        $document = $this->documentWithFile(['owner_id' => $owner->id]);
        $reader = $this->userWithRole('General Staff');

        $this->actingAs($reader)->get(route('documents.show', $document))->assertOk()->assertDontSee(__('Access log'));
        $this->actingAs($owner)->get(route('documents.show', $document))->assertOk()->assertSee(__('Access log'))->assertSee($reader->name);
        $this->actingAs($this->userWithRole('Read-Only Auditor'))->get(route('documents.show', $document))->assertSee(__('Access log'));
    }

    public function test_general_staff_cannot_upload(): void
    {
        $this->actingAs($this->userWithRole('General Staff'))->get(route('documents.create'))->assertForbidden();
        $this->actingAs($this->userWithRole('General Staff'))->post(route('documents.store'), [
            'title' => 'X', 'confidentiality' => 'internal', 'file' => $this->pdf(),
        ])->assertForbidden();
    }

    public function test_pages_render_in_somali(): void
    {
        $user = $this->superAdmin(['locale' => 'so']);
        $document = $this->documentWithFile();

        $this->actingAs($user)->get(route('documents.index'))->assertOk()->assertSee('Diiwaanka la daabici karo');
        $this->actingAs($user)->get(route('documents.show', $document))->assertOk()->assertSee('Nuqullada iyo taariikhda dib-u-eegista');
    }
}
