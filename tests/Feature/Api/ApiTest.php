<?php

namespace Tests\Feature\Api;

use App\Models\Document;
use App\Models\Project;
use App\Models\Staff;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_requests_without_a_token_are_rejected(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_token_user_needs_api_access_permission(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['projects.view']), ['read']);

        $this->getJson('/api/v1/projects')->assertForbidden();
    }

    public function test_projects_endpoint_returns_paginated_records(): void
    {
        Project::query()->create(['code' => 'PRJ-1', 'title' => 'Data hall design', 'status' => 'in_progress']);
        Sanctum::actingAs($this->userWithPermissions(['api.access', 'projects.view']), ['read']);

        $this->getJson('/api/v1/projects')->assertOk()
            ->assertJsonPath('data.0.code', 'PRJ-1')
            ->assertJsonPath('meta.total', 1);

        $project = Project::query()->firstOrFail();
        $this->getJson('/api/v1/projects/'.$project->id)->assertOk()->assertJsonPath('data.title', 'Data hall design');
    }

    public function test_module_permission_is_enforced(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['api.access']), ['read']);

        $this->getJson('/api/v1/projects')->assertForbidden();
        $this->getJson('/api/v1/me')->assertOk();
    }

    public function test_task_and_document_visibility_matches_web_rules(): void
    {
        $owner = $this->superAdmin();
        Task::query()->create(['title' => 'Private task', 'assigned_to' => $owner->id, 'status' => 'pending']);
        Document::query()->create(['reference_number' => 'NDC/DOC/2026/0009', 'title' => 'Restricted file', 'confidentiality' => 'restricted', 'status' => 'draft', 'owner_id' => $owner->id]);

        Sanctum::actingAs($this->userWithPermissions(['api.access', 'tasks.view', 'documents.view']), ['read']);

        $this->getJson('/api/v1/tasks')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/documents')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_staff_endpoint_never_returns_sensitive_fields(): void
    {
        Staff::query()->create(['staff_number' => 'STF-1', 'full_name' => 'Hodan Ahmed', 'national_id' => 'SECRET-123']);
        Sanctum::actingAs($this->userWithPermissions(['api.access', 'staff.view', 'staff.view-sensitive']), ['read']);

        $response = $this->getJson('/api/v1/staff')->assertOk()->assertJsonPath('data.0.full_name', 'Hodan Ahmed');
        $this->assertStringNotContainsString('SECRET-123', $response->getContent());
        $this->assertArrayNotHasKey('national_id', $response->json('data.0'));
    }

    public function test_token_without_read_ability_is_refused(): void
    {
        Sanctum::actingAs($this->userWithPermissions(['api.access', 'projects.view']), []);

        $this->getJson('/api/v1/projects')->assertForbidden();
    }

    public function test_user_can_issue_and_revoke_tokens_from_profile(): void
    {
        $user = $this->userWithPermissions(['api.access']);

        $this->actingAs($user)->post(route('profile.tokens.store'), ['token_name' => 'GIS sync', 'expires_in_days' => 90])
            ->assertRedirect(route('profile.security'))->assertSessionHas('api.new_token');
        $token = $user->tokens()->firstOrFail();
        $this->assertSame(['read'], $token->abilities);
        $this->assertNotNull($token->expires_at);

        $this->actingAs($user)->delete(route('profile.tokens.destroy', $token->id))->assertRedirect();
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_users_without_api_access_cannot_issue_tokens(): void
    {
        $this->actingAs($this->userWithRole('General Staff'))
            ->post(route('profile.tokens.store'), ['token_name' => 'x', 'expires_in_days' => 30])->assertForbidden();
    }
}
