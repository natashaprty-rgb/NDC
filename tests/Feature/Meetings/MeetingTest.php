<?php

namespace Tests\Feature\Meetings;

use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\MeetingParticipant;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MeetingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Notification::fake();
    }

    private function organiser(): User
    {
        return $this->userWithRole('Project Manager'); // meetings.*
    }

    /**
     * @return array<string, mixed>
     */
    private function meetingData(array $overrides = []): array
    {
        return [
            'title' => 'Quarterly data governance review',
            'type' => 'meeting',
            'start_at' => now()->addDays(3)->setTime(10, 0)->format('Y-m-d\TH:i'),
            'end_at' => now()->addDays(3)->setTime(12, 0)->format('Y-m-d\TH:i'),
            'location' => 'Board room',
            'description' => 'Review of data governance progress.',
            'agenda' => "1. Opening\n2. Progress report",
            ...$overrides,
        ];
    }

    public function test_meeting_can_be_scheduled_with_internal_and_external_participants(): void
    {
        $organiser = $this->organiser();
        $alice = $this->userWithRole('General Staff');
        $bob = $this->userWithRole('Project Officer');

        $this->actingAs($organiser)->post(route('meetings.store'), $this->meetingData([
            'participants' => [$alice->id, $bob->id, $organiser->id],
            'guests' => [
                ['name' => 'Hodan Warsame', 'organization' => 'Ministry of Planning', 'email' => 'hodan@example.com'],
                ['name' => '', 'organization' => '', 'email' => ''],
            ],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $meeting = Meeting::query()->firstOrFail();
        $this->assertSame('scheduled', $meeting->status);
        $this->assertSame($organiser->id, $meeting->created_by);
        $this->assertSame(3, $meeting->participants()->whereNotNull('user_id')->count());
        $this->assertDatabaseHas('meeting_participants', ['meeting_id' => $meeting->id, 'name' => 'Hodan Warsame', 'organization' => 'Ministry of Planning', 'user_id' => null]);
        $this->assertSame(4, $meeting->participants()->count());

        Notification::assertSentTo($alice, GenericNotification::class, fn ($n) => $n->kind === 'meeting_invitation');
        Notification::assertSentTo($bob, GenericNotification::class, fn ($n) => $n->kind === 'meeting_invitation');
        Notification::assertNotSentTo($organiser, GenericNotification::class);
    }

    public function test_end_time_must_be_after_start_time(): void
    {
        $this->actingAs($this->organiser())->post(route('meetings.store'), $this->meetingData([
            'start_at' => now()->addDay()->setTime(14, 0)->format('Y-m-d\TH:i'),
            'end_at' => now()->addDay()->setTime(13, 0)->format('Y-m-d\TH:i'),
        ]))->assertSessionHasErrors('end_at');

        $this->assertSame(0, Meeting::query()->count());
    }

    public function test_updating_participants_keeps_attendance_and_notifies_only_new_invitees(): void
    {
        $organiser = $this->organiser();
        $kept = $this->userWithRole('General Staff');
        $removed = $this->userWithRole('General Staff');
        $added = $this->userWithRole('General Staff');
        $meeting = Meeting::factory()->create(['organizer_id' => $organiser->id]);
        $keptRow = $meeting->participants()->create(['user_id' => $kept->id, 'attendance' => 'excused']);
        $meeting->participants()->create(['user_id' => $removed->id]);
        $guest = $meeting->participants()->create(['name' => 'Old Guest', 'organization' => 'UNDP']);

        $this->actingAs($organiser)->put(route('meetings.update', $meeting), $this->meetingData([
            'start_at' => $meeting->start_at->format('Y-m-d\TH:i'),
            'end_at' => $meeting->end_at->format('Y-m-d\TH:i'),
            'location' => $meeting->location,
            'title' => 'Renamed meeting',
            'participants' => [$kept->id, $added->id],
            'guests' => [['id' => $guest->id, 'name' => 'Old Guest', 'organization' => 'UNDP Somalia', 'email' => '']],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('excused', $keptRow->fresh()->attendance);
        $this->assertDatabaseMissing('meeting_participants', ['meeting_id' => $meeting->id, 'user_id' => $removed->id]);
        $this->assertDatabaseHas('meeting_participants', ['id' => $guest->id, 'organization' => 'UNDP Somalia']);
        Notification::assertSentTo($added, GenericNotification::class, fn ($n) => $n->kind === 'meeting_invitation');
        Notification::assertNotSentTo($kept, GenericNotification::class);
        $this->assertSame('Renamed meeting', $meeting->fresh()->title);
    }

    public function test_minutes_attendance_and_action_items_are_recorded(): void
    {
        $organiser = $this->organiser();
        $assignee = $this->userWithRole('General Staff');
        $meeting = Meeting::factory()->past()->create(['organizer_id' => $organiser->id]);
        $participant = MeetingParticipant::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $assignee->id]);
        $guest = MeetingParticipant::factory()->guest()->create(['meeting_id' => $meeting->id]);

        $this->actingAs($organiser)->put(route('meetings.attendance', $meeting), [
            'attendance' => [$participant->id => 'attended', $guest->id => 'absent'],
        ])->assertRedirect();
        $this->assertSame('attended', $participant->fresh()->attendance);
        $this->assertSame('absent', $guest->fresh()->attendance);

        $this->actingAs($organiser)->put(route('meetings.minutes.update', $meeting), [
            'minutes' => 'The committee reviewed progress.', 'mark_completed' => '1',
        ])->assertRedirect();
        $meeting->refresh();
        $this->assertSame('The committee reviewed progress.', $meeting->minutes);
        $this->assertSame($organiser->id, $meeting->minutes_recorded_by);
        $this->assertNotNull($meeting->minutes_recorded_at);
        $this->assertSame('completed', $meeting->status);

        $this->actingAs($organiser)->post(route('meetings.action-items.store', $meeting), [
            'type' => 'action', 'description' => 'Prepare the data inventory', 'assigned_to' => $assignee->id,
            'due_date' => today()->addWeek()->toDateString(),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($organiser)->post(route('meetings.action-items.store', $meeting), [
            'type' => 'decision', 'description' => 'Adopt the open data policy',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(2, $meeting->actionItems()->count());
        $this->assertDatabaseHas('meeting_action_items', ['meeting_id' => $meeting->id, 'type' => 'decision', 'status' => 'open']);
        Notification::assertSentTo($assignee, GenericNotification::class, fn ($n) => $n->kind === 'meeting_action_assigned');
        $this->assertDatabaseHas('audit_logs', ['event' => 'minutes_recorded', 'auditable_type' => 'meeting', 'auditable_id' => $meeting->id]);
    }

    public function test_assignee_can_update_own_action_item_status_without_update_permission(): void
    {
        $organiser = $this->organiser();
        $assignee = $this->userWithRole('General Staff'); // meetings.view only
        $stranger = $this->userWithRole('General Staff');
        $meeting = Meeting::factory()->create(['organizer_id' => $organiser->id]);
        $item = MeetingActionItem::factory()->create(['meeting_id' => $meeting->id, 'assigned_to' => $assignee->id]);

        $this->actingAs($stranger)->patch(route('meetings.action-items.status', $item), ['status' => 'done'])->assertForbidden();
        $this->actingAs($assignee)->put(route('meetings.action-items.update', $item), [
            'type' => 'action', 'description' => 'Hijack', 'status' => 'done',
        ])->assertForbidden();

        $this->actingAs($assignee)->patch(route('meetings.action-items.status', $item), ['status' => 'in_progress', 'notes' => 'Started'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('in_progress', $item->fresh()->status);
        $this->assertNull($item->fresh()->completed_at);

        $this->actingAs($assignee)->patch(route('meetings.action-items.status', $item), ['status' => 'done'])->assertRedirect();
        $item->refresh();
        $this->assertSame('done', $item->status);
        $this->assertNotNull($item->completed_at);
        $this->assertSame('Started', $item->notes);
        Notification::assertSentTo($organiser, GenericNotification::class, fn ($n) => $n->kind === 'meeting_action_completed');
    }

    public function test_outstanding_action_points_page_filters(): void
    {
        $user = $this->organiser();
        $assignee = $this->userWithRole('General Staff');
        $meeting = Meeting::factory()->create();
        MeetingActionItem::factory()->overdue()->create(['meeting_id' => $meeting->id, 'description' => 'Overdue inventory task', 'assigned_to' => $assignee->id]);
        MeetingActionItem::factory()->create(['meeting_id' => $meeting->id, 'description' => 'Future policy draft']);
        MeetingActionItem::factory()->create(['meeting_id' => $meeting->id, 'description' => 'Finished report', 'status' => 'done']);

        $this->actingAs($user)->get(route('meetings.action-items.index'))->assertOk()
            ->assertSee('Overdue inventory task')->assertSee('Future policy draft')->assertDontSee('Finished report');
        $this->actingAs($user)->get(route('meetings.action-items.index', ['overdue' => 1]))->assertOk()
            ->assertSee('Overdue inventory task')->assertDontSee('Future policy draft');
        $this->actingAs($user)->get(route('meetings.action-items.index', ['assignee' => $assignee->id]))->assertOk()
            ->assertSee('Overdue inventory task')->assertDontSee('Future policy draft');
        $this->actingAs($user)->get(route('meetings.action-items.index', ['status' => 'done']))->assertOk()
            ->assertSee('Finished report')->assertDontSee('Future policy draft');
        $this->actingAs($assignee)->get(route('meetings.action-items.index', ['assignee' => 'me']))->assertOk()
            ->assertSee('Overdue inventory task')->assertDontSee('Future policy draft');
    }

    public function test_calendar_month_shows_meetings_and_deadlines(): void
    {
        $user = $this->userWithRole('General Staff');
        $meeting = Meeting::factory()->create(['title' => 'Board strategy session', 'start_at' => '2026-11-10 09:00:00', 'end_at' => '2026-11-10 11:00:00']);
        Meeting::factory()->create(['title' => 'December planning', 'start_at' => '2026-12-15 09:00:00', 'end_at' => '2026-12-15 10:00:00']);
        MeetingActionItem::factory()->create(['meeting_id' => $meeting->id, 'description' => 'Submit budget figures', 'due_date' => '2026-11-20']);

        $response = $this->actingAs($user)->get(route('meetings.calendar', ['month' => '2026-11']))->assertOk();
        $response->assertSee('Board strategy session')->assertSee('Submit budget figures')
            ->assertSee('cal__event--deadline', false)->assertDontSee('December planning')
            ->assertSee('month=2026-10', false)->assertSee('month=2026-12', false);

        // Invalid month falls back to the current month.
        $this->actingAs($user)->get(route('meetings.calendar', ['month' => 'garbage']))->assertOk();

        // Somali interface.
        $somali = $this->userWithRole('General Staff', ['locale' => 'so']);
        $this->actingAs($somali)->withSession(['locale' => 'so'])->get(route('meetings.calendar', ['month' => '2026-11']))
            ->assertOk()->assertSee('Kulamada &amp; Jadwalka', false);
    }

    public function test_printable_minutes_render(): void
    {
        $user = $this->userWithRole('General Staff');
        $meeting = Meeting::factory()->past()->create(['title' => 'Steering committee', 'minutes' => 'Minutes text here', 'status' => 'completed']);
        MeetingParticipant::factory()->guest()->create(['meeting_id' => $meeting->id, 'name' => 'Guest Person', 'attendance' => 'attended']);
        MeetingActionItem::factory()->create(['meeting_id' => $meeting->id, 'type' => 'decision', 'description' => 'Approve roadmap']);
        MeetingActionItem::factory()->create(['meeting_id' => $meeting->id, 'description' => 'Circulate roadmap']);

        $this->actingAs($user)->get(route('meetings.minutes', $meeting))->assertOk()
            ->assertSee('Steering committee')->assertSee('Minutes text here')->assertSee('Guest Person')
            ->assertSee('Approve roadmap')->assertSee('Circulate roadmap')
            ->assertSee('images/logo-horizontal.png', false)->assertSee('data-print', false);
    }

    public function test_status_change_notifies_participants(): void
    {
        $organiser = $this->organiser();
        $participant = $this->userWithRole('General Staff');
        $meeting = Meeting::factory()->create(['organizer_id' => $organiser->id]);
        $meeting->participants()->create(['user_id' => $participant->id]);

        $this->actingAs($organiser)->patch(route('meetings.status', $meeting), ['status' => 'cancelled'])->assertRedirect();

        $this->assertSame('cancelled', $meeting->fresh()->status);
        Notification::assertSentTo($participant, GenericNotification::class, fn ($n) => $n->kind === 'meeting_cancelled');
        $this->assertDatabaseHas('audit_logs', ['event' => 'status_changed', 'auditable_type' => 'meeting', 'auditable_id' => $meeting->id]);
    }

    public function test_authorization(): void
    {
        $staff = $this->userWithRole('General Staff'); // meetings.view only
        $meeting = Meeting::factory()->create();
        $noAccess = $this->userWithPermissions(['dashboard.view']);

        $this->actingAs($staff)->get(route('meetings.index'))->assertOk();
        $this->actingAs($staff)->get(route('meetings.show', $meeting))->assertOk();
        $this->actingAs($staff)->get(route('meetings.create'))->assertForbidden();
        $this->actingAs($staff)->post(route('meetings.store'), $this->meetingData())->assertForbidden();
        $this->actingAs($staff)->get(route('meetings.edit', $meeting))->assertForbidden();
        $this->actingAs($staff)->put(route('meetings.minutes.update', $meeting), ['minutes' => 'x'])->assertForbidden();
        $this->actingAs($staff)->delete(route('meetings.destroy', $meeting))->assertForbidden();
        $this->actingAs($noAccess)->get(route('meetings.index'))->assertForbidden();
        $this->actingAs($noAccess)->get(route('meetings.calendar'))->assertForbidden();
    }

    public function test_delete_meeting(): void
    {
        $director = $this->userWithRole('Director or Management');
        $meeting = Meeting::factory()->create();

        $this->actingAs($director)->delete(route('meetings.destroy', $meeting))->assertRedirect(route('meetings.index'));
        $this->assertSoftDeleted($meeting);
    }

    public function test_all_pages_render(): void
    {
        $organiser = $this->organiser();
        $meeting = Meeting::factory()->create(['title' => 'Upcoming review', 'organizer_id' => $organiser->id]);
        $past = Meeting::factory()->past()->create(['title' => 'Last month review']);
        MeetingParticipant::factory()->create(['meeting_id' => $meeting->id, 'user_id' => $organiser->id]);
        MeetingParticipant::factory()->guest()->create(['meeting_id' => $meeting->id]);
        $item = MeetingActionItem::factory()->create(['meeting_id' => $meeting->id, 'assigned_to' => $organiser->id]);

        $this->actingAs($organiser)->get(route('meetings.index'))->assertOk()->assertSee('Upcoming review')->assertDontSee('Last month review');
        $this->actingAs($organiser)->get(route('meetings.index', ['when' => 'past']))->assertOk()->assertSee('Last month review');
        $this->actingAs($organiser)->get(route('meetings.index', ['when' => 'all', 'mine' => 1]))->assertOk();
        $this->actingAs($organiser)->get(route('meetings.create'))->assertOk();
        $this->actingAs($organiser)->get(route('meetings.show', $meeting))->assertOk()->assertSee($item->description);
        $this->actingAs($organiser)->get(route('meetings.edit', $meeting))->assertOk();
        $this->actingAs($organiser)->get(route('meetings.calendar'))->assertOk();
        $this->actingAs($organiser)->get(route('meetings.calendar', ['mine' => 1]))->assertOk();
        $this->actingAs($organiser)->get(route('meetings.action-items.index'))->assertOk();
        $this->actingAs($organiser)->get(route('meetings.action-items.edit', $item))->assertOk();
        $this->actingAs($organiser)->get(route('meetings.minutes', $meeting))->assertOk();
        $this->actingAs($this->superAdmin())->get(route('meetings.show', $past))->assertOk();
    }
}
