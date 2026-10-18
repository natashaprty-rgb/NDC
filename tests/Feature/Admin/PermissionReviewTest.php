<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\PermissionReview;
use App\Models\User;
use Tests\TestCase;

class PermissionReviewTest extends TestCase
{
    public function test_sheet_lists_accounts_with_flags(): void
    {
        $reviewer = $this->userWithRole('system-admin', ['last_login_at' => now()]);
        $this->userWithRole('super-admin', ['name' => 'Root Holder', 'last_login_at' => now()]);
        $this->userWithRole('staff', ['name' => 'Dormant Person', 'last_login_at' => now()->subDays(200)]);

        $this->actingAs($reviewer)->get('/admin/permission-reviews/sheet')->assertOk()
            ->assertSee('Root Holder')->assertSee(__('audit.flag.super_admin'))
            ->assertSee('Dormant Person')->assertSee(__('audit.flag.inactive'));
    }

    public function test_csv_export_contains_header_and_rows_and_is_audited(): void
    {
        $reviewer = $this->userWithRole('system-admin');
        $this->userWithRole('staff', ['name' => '=HYPERLINK("x")', 'email' => 'formula@example.test']);

        $response = $this->actingAs($reviewer)->get('/admin/permission-reviews/sheet.csv');
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $lines = array_values(array_filter(explode("\n", trim(substr($csv, 3)))));
        $this->assertStringContainsString(__('auth.email'), $lines[0]);
        $this->assertCount(User::query()->count() + 1, $lines);
        $this->assertStringContainsString('formula@example.test', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv, 'Formula injection must be neutralised');
        $this->assertTrue(AuditLog::query()->where('event', 'exported')->where('user_id', $reviewer->id)->exists());
    }

    public function test_review_is_recorded_with_account_count(): void
    {
        $reviewer = $this->userWithRole('system-admin');
        $this->userWithRole('staff');

        $this->actingAs($reviewer)->post('/admin/permission-reviews', [
            'review_date' => today()->toDateString(), 'findings' => 'Two dormant accounts suspended.',
            'actions_taken' => 'Suspended', 'next_review_due' => today()->addDays(90)->toDateString(),
        ])->assertRedirect(route('admin.permission-reviews.index'));

        $review = PermissionReview::query()->firstOrFail();
        $this->assertSame($reviewer->id, $review->reviewed_by);
        $this->assertSame(User::query()->count(), $review->accounts_reviewed);

        $this->actingAs($reviewer)->post('/admin/permission-reviews', ['review_date' => today()->toDateString(), 'findings' => '', 'next_review_due' => today()->subDay()->toDateString()])
            ->assertSessionHasErrors(['findings', 'next_review_due']);
    }

    public function test_overdue_banner_shown_when_no_recent_review(): void
    {
        $reviewer = $this->userWithRole('system-admin');
        $this->actingAs($reviewer)->get('/admin/permission-reviews')->assertSee(__('audit.review_never'));
    }

    public function test_requires_permission_review_permission(): void
    {
        $auditor = $this->userWithRole('auditor');
        $this->actingAs($auditor)->get('/admin/permission-reviews')->assertForbidden();
        $this->actingAs($auditor)->get('/admin/permission-reviews/sheet.csv')->assertForbidden();
        $this->actingAs($auditor)->post('/admin/permission-reviews', [])->assertForbidden();
    }
}
