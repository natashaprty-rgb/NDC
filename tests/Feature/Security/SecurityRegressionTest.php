<?php

namespace Tests\Feature\Security;

use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityRegressionTest extends TestCase
{
    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('v.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");
    }

    /**
     * A reviewer holding documents.manage could upload a new version of someone
     * else's document, submit it and approve their own content.
     */
    public function test_reviewer_cannot_approve_a_version_they_uploaded(): void
    {
        Storage::fake('local');
        $owner = $this->userWithRole('document-officer');
        $reviewer = $this->userWithPermissions(['documents.view', 'documents.create', 'documents.manage', 'documents.approve']);

        $this->actingAs($owner)->post('/documents', [
            'title' => 'Hosting policy', 'registration_date' => today()->toDateString(), 'confidentiality' => 'internal', 'file' => $this->pdf(),
        ])->assertSessionHasNoErrors();
        $document = Document::query()->firstOrFail();

        // Reviewer replaces the content, then submits it for review.
        $this->actingAs($reviewer)->post("/documents/{$document->id}/versions", ['file' => $this->pdf(), 'change_note' => 'edited'])->assertSessionHasNoErrors();
        $this->actingAs($reviewer)->post("/documents/{$document->id}/submit")->assertSessionHasNoErrors();
        $this->assertSame('pending_review', $document->fresh()->status);

        $this->actingAs($reviewer)->post("/documents/{$document->id}/approve")->assertForbidden();
        $this->assertSame('pending_review', $document->fresh()->status);

        // An independent reviewer can still approve.
        $other = $this->userWithRole('director');
        $this->actingAs($other)->post("/documents/{$document->id}/approve")->assertRedirect();
        $this->assertSame('approved', $document->fresh()->status);
    }

    /** Staff profiles listed a colleague's open tasks and assets regardless of task visibility / assets.view. */
    public function test_staff_profile_does_not_leak_tasks_or_assets_the_viewer_cannot_see(): void
    {
        $colleague = $this->userWithRole('staff');
        $viewer = $this->userWithRole('staff');
        $manager = $this->userWithRole('project-manager');
        $this->placeInDepartment($colleague);
        $staffRecord = \App\Models\Staff::query()->where('user_id', $colleague->id)->firstOrFail();

        $this->actingAs($manager);
        \App\Models\Task::query()->create(['reference' => 'TSK-SECRET-1', 'title' => 'Confidential investigation task', 'status' => 'todo', 'assigned_to' => $colleague->id, 'assigned_by' => $manager->id]);
        \App\Models\Asset::query()->create(['asset_tag' => 'AST-SECRET-1', 'name' => 'Encrypted laptop', 'category_id' => \App\Models\AssetCategory::query()->create(['code' => 'CMP', 'name' => 'Computers'])->id, 'status' => 'in_use', 'assigned_staff_id' => $staffRecord->id]);

        $this->actingAs($viewer)->get("/staff/{$staffRecord->id}")->assertOk()
            ->assertDontSee('Confidential investigation task')
            ->assertDontSee('Encrypted laptop');

        // The task creator still sees it on the profile.
        $this->actingAs($manager)->get("/staff/{$staffRecord->id}")->assertOk()->assertSee('Confidential investigation task');
    }
}
