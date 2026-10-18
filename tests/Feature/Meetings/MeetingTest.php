<?php

namespace Tests\Feature\Meetings;

use App\Models\Meeting;
use App\Models\MeetingItem;
use App\Models\Task;
use Tests\TestCase;

class MeetingTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Quarterly planning',
            'meeting_type' => 'management',
            'status' => 'scheduled',
            'starts_at' => now()->addDays(3)->format('Y-m-d\T10:00'),
            'ends_at' => now()->addDays(3)->format('Y-m-d\T12:00'),
            'location' => 'Board room',
            'agenda' => "1. Opening\n2. Review",
        ], $overrides);
    }

    public function test_manager_schedules_meeting_with_participants_who_are_notified(): void
    {
        $organiser = $this->userWithRole('project-manager');
        $invitee = $this->userWithRole('staff');

        $this->actingAs($organiser)->post('/meetings', $this->payload([
            'participant_ids' => [$invitee->id],
            'externals' => [
                ['name' => 'Consultant A', 'organization' => 'Partner Ltd', 'email' => 'a@example.test'],
                ['name' => '', 'organization' => '', 'email' => ''],
            ],
        ]))->assertRedirect();

        $meeting = Meeting::query()->where('title', 'Quarterly planning')->firstOrFail();
        $this->assertStringStartsWith('NDC-MTG-', $meeting->reference);
        $this->assertSame($organiser->id, $meeting->organizer_id);
        $this->assertSame(2, $meeting->participants()->count());
        $this->assertDatabaseHas('meeting_participants', ['meeting_id' => $meeting->id, 'external_name' => 'Consultant A']);

        $notification = $invitee->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertSame('meetings.notify.invited', $notification->data['title_key']);
        $this->assertSame(0, $organiser->notifications()->count(), 'Actor is not notified');
    }

    public function test_end_before_start_is_rejected(): void
    {
        $manager = $this->userWithRole('project-manager');

        $this->actingAs($manager)->post('/meetings', $this->payload([
            'starts_at' => now()->addDay()->format('Y-m-d\T14:00'),
            'ends_at' => now()->addDay()->format('Y-m-d\T13:00'),
        ]))->assertSessionHasErrors('ends_at');
        $this->actingAs($manager)->post('/meetings', $this->payload(['online_link' => 'javascript:alert(1)']))->assertSessionHasErrors('online_link');
        $this->assertSame(0, Meeting::query()->count());
    }

    public function test_staff_without_manage_permission_cannot_create_or_edit(): void
    {
        $staff = $this->userWithRole('staff');
        $meeting = Meeting::factory()->create();

        $this->actingAs($staff)->get('/meetings/create')->assertForbidden();
        $this->actingAs($staff)->post('/meetings', $this->payload())->assertForbidden();
        $this->actingAs($staff)->put("/meetings/{$meeting->id}", $this->payload())->assertForbidden();
        $this->actingAs($staff)->get("/meetings/{$meeting->id}/minutes")->assertForbidden();
        $this->actingAs($staff)->get("/meetings/{$meeting->id}")->assertOk();
    }

    public function test_users_without_meetings_view_only_see_their_own_meetings(): void
    {
        $outsider = $this->userWithPermissions(['dashboard.view']);
        $participant = $this->userWithPermissions(['dashboard.view']);
        $secret = Meeting::factory()->create(['title' => 'Hidden board meeting', 'starts_at' => now()->addDays(2)->setTime(9, 0)]);
        $mine = Meeting::factory()->create(['title' => 'My team meeting', 'starts_at' => now()->addDays(2)->setTime(11, 0)]);
        $mine->participants()->create(['user_id' => $participant->id]);

        $this->actingAs($outsider)->get("/meetings/{$secret->id}")->assertForbidden();
        $this->actingAs($outsider)->get('/meetings')->assertOk()->assertDontSee('Hidden board meeting');
        $this->actingAs($outsider)->get('/meetings/calendar?month='.now()->addDays(2)->format('Y-m'))->assertDontSee('Hidden board meeting');

        $this->actingAs($participant)->get("/meetings/{$mine->id}")->assertOk();
        $this->actingAs($participant)->get('/meetings')->assertSee('My team meeting')->assertDontSee('Hidden board meeting');
    }

    public function test_calendar_places_meeting_on_correct_day(): void
    {
        $staff = $this->userWithRole('staff');
        Meeting::factory()->create(['title' => 'Budget review', 'starts_at' => '2026-03-17 09:30:00', 'ends_at' => '2026-03-17 10:30:00']);

        $response = $this->actingAs($staff)->get('/meetings/calendar?month=2026-03')->assertOk();
        $html = $response->getContent();
        $cellStart = strpos($html, 'data-date="2026-03-17"');
        $this->assertNotFalse($cellStart);
        $cell = substr($html, $cellStart, strpos($html, '</td>', $cellStart) - $cellStart);
        $this->assertStringContainsString('Budget review', $cell);
        $this->assertStringNotContainsString('Budget review', substr($html, strpos($html, 'data-date="2026-03-16"'), 300));
    }

    public function test_minutes_attendance_and_action_converted_to_task(): void
    {
        $manager = $this->userWithRole('project-manager');
        $responsible = $this->userWithRole('project-officer');
        $meeting = Meeting::factory()->create(['organizer_id' => $manager->id]);
        $participant = $meeting->participants()->create(['user_id' => $responsible->id]);

        $this->actingAs($manager)->put("/meetings/{$meeting->id}/minutes", [
            'minutes' => 'Agreed to procure laptops.',
            'attendance' => [$participant->id => 'attended'],
            'mark_completed' => '1',
        ])->assertRedirect();
        $meeting->refresh();
        $this->assertSame('Agreed to procure laptops.', $meeting->minutes);
        $this->assertSame('completed', $meeting->status);
        $this->assertSame($manager->id, $meeting->minutes_recorded_by);
        $this->assertSame('attended', $participant->fresh()->attendance);

        $this->actingAs($manager)->post("/meetings/{$meeting->id}/items", ['type' => 'decision', 'description' => 'Approve plan'])->assertRedirect();
        $this->actingAs($manager)->post("/meetings/{$meeting->id}/items", [
            'type' => 'action', 'description' => 'Prepare procurement request', 'responsible_id' => $responsible->id, 'due_date' => now()->addWeek()->toDateString(),
        ])->assertRedirect();
        $action = MeetingItem::query()->where('type', 'action')->firstOrFail();

        $this->actingAs($manager)->post("/meetings/{$meeting->id}/items/{$action->id}/task")->assertRedirect();
        $task = Task::query()->findOrFail($action->fresh()->task_id);
        $this->assertSame($responsible->id, $task->assigned_to);
        $this->assertSame($manager->id, $task->created_by);
        $this->assertSame('Prepare procurement request', $task->title);
        $this->assertSame('meetings.notify.action_assigned', $responsible->notifications()->first()->data['title_key']);

        // A second conversion is refused.
        $this->actingAs($manager)->post("/meetings/{$meeting->id}/items/{$action->id}/task")->assertSessionHas('error');
        $this->assertSame(1, Task::query()->count());
    }

    public function test_user_without_tasks_create_cannot_convert_actions(): void
    {
        $officer = $this->userWithRole('document-officer'); // meetings.manage but not tasks.create
        $other = $this->userWithRole('staff');
        $meeting = Meeting::factory()->create();
        $item = $meeting->items()->create(['type' => 'action', 'description' => 'Do X', 'responsible_id' => $other->id]);

        $this->actingAs($officer)->post("/meetings/{$meeting->id}/items/{$item->id}/task")->assertForbidden();
        $this->assertNull($item->fresh()->task_id);
    }

    public function test_items_of_other_meetings_cannot_be_targeted(): void
    {
        $manager = $this->userWithRole('project-manager');
        $a = Meeting::factory()->create();
        $b = Meeting::factory()->create();
        $item = $b->items()->create(['type' => 'action', 'description' => 'X', 'responsible_id' => $manager->id]);

        $this->actingAs($manager)->put("/meetings/{$a->id}/items/{$item->id}", ['status' => 'done'])->assertNotFound();
    }

    public function test_reschedule_notifies_participants_and_cancel_changes_status(): void
    {
        $manager = $this->userWithRole('project-manager');
        $invitee = $this->userWithRole('staff');
        $meeting = Meeting::factory()->create(['organizer_id' => $manager->id]);
        $meeting->participants()->create(['user_id' => $invitee->id]);

        $this->actingAs($manager)->put("/meetings/{$meeting->id}", $this->payload([
            'title' => $meeting->title, 'participant_ids' => [$invitee->id], 'location' => 'New room',
        ]))->assertRedirect();
        $this->assertSame('meetings.notify.updated', $invitee->notifications()->first()->data['title_key']);

        $this->actingAs($manager)->post("/meetings/{$meeting->id}/status", ['status' => 'cancelled'])->assertRedirect();
        $this->assertSame('cancelled', $meeting->fresh()->status);
    }

    public function test_search_finds_text_in_minutes(): void
    {
        $staff = $this->userWithRole('staff');
        Meeting::factory()->create(['title' => 'Alpha meeting', 'minutes' => 'Discussed fibre connectivity', 'starts_at' => now()->subDays(5)]);
        Meeting::factory()->create(['title' => 'Beta meeting', 'minutes' => 'Other topics', 'starts_at' => now()->subDays(5)]);

        $this->actingAs($staff)->get('/meetings?tab=all&q=fibre')->assertSee('Alpha meeting')->assertDontSee('Beta meeting');
    }

    public function test_forms_render_for_manager(): void
    {
        $manager = $this->userWithRole('project-manager');
        $meeting = Meeting::factory()->create(['organizer_id' => $manager->id]);
        $meeting->participants()->create(['external_name' => 'Guest One', 'external_organization' => 'Org']);

        $this->actingAs($manager)->get('/meetings/create')->assertOk();
        $this->actingAs($manager)->get("/meetings/{$meeting->id}/edit")->assertOk()->assertSee('Guest One');
        $this->actingAs($manager)->get("/meetings/{$meeting->id}/minutes")->assertOk();
        $this->actingAs($manager)->get("/meetings/{$meeting->id}")->assertOk()->assertSee(__('meetings.record_minutes'));
        $this->actingAs($manager)->get('/meetings/calendar?month=bad')->assertOk();
    }
}
