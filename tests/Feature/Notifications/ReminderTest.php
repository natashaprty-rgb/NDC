<?php

namespace Tests\Feature\Notifications;

use App\Models\Document;
use App\Models\Meeting;
use App\Models\SentReminder;
use App\Models\Task;
use Database\Factories\TaskFactory;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\TestCase;

class ReminderTest extends TestCase
{
    public function test_overdue_and_due_soon_tasks_send_one_reminder_per_period(): void
    {
        $assignee = $this->userWithRole('staff');
        $creator = $this->userWithRole('project-manager');
        $this->actingAs($creator);
        $overdue = Task::query()->create(['reference' => 'T-1', 'title' => 'Late report', 'status' => 'in_progress', 'due_date' => today()->subDays(3), 'assigned_to' => $assignee->id, 'assigned_by' => $creator->id]);
        $dueSoon = Task::query()->create(['reference' => 'T-2', 'title' => 'Prepare slides', 'status' => 'todo', 'due_date' => today()->addDay(), 'assigned_to' => $assignee->id, 'assigned_by' => $creator->id]);
        Task::query()->create(['reference' => 'T-3', 'title' => 'Done already', 'status' => 'completed', 'due_date' => today()->subDays(3), 'assigned_to' => $assignee->id]);
        auth()->logout();

        $this->artisan('ndc:send-reminders')->assertSuccessful();

        $this->assertSame(1, SentReminder::query()->where('reminder_type', 'task_overdue')->where('user_id', $assignee->id)->count());
        $this->assertSame(1, SentReminder::query()->where('reminder_type', 'task_overdue')->where('user_id', $creator->id)->count());
        $this->assertSame(1, SentReminder::query()->where('reminder_type', 'task_due_soon')->where('remindable_id', $dueSoon->id)->count());
        $this->assertSame(0, SentReminder::query()->where('remindable_type', 'task')->whereNotIn('remindable_id', [$overdue->id, $dueSoon->id])->count());
        $this->assertSame(2, $assignee->notifications()->count());

        // Running again the same day must not duplicate anything.
        $this->artisan('ndc:send-reminders')->assertSuccessful();
        $this->assertSame(2, $assignee->fresh()->notifications()->count());
    }

    public function test_meeting_and_document_expiry_reminders(): void
    {
        $organizer = $this->userWithRole('department-head');
        $participant = $this->userWithRole('staff');
        $this->actingAs($organizer);
        $meeting = Meeting::query()->create(['reference' => 'M-1', 'title' => 'Steering committee', 'starts_at' => now()->addHours(5), 'status' => 'scheduled', 'organizer_id' => $organizer->id]);
        $meeting->participants()->create(['user_id' => $participant->id]);
        Document::query()->create(['reference' => 'D-1', 'title' => 'Hosting policy', 'registration_date' => today(), 'owner_id' => $organizer->id, 'expiry_date' => today()->addDays(10), 'status' => 'approved']);
        auth()->logout();

        $this->artisan('ndc:send-reminders')->assertSuccessful();

        $this->assertTrue(SentReminder::query()->where(['reminder_type' => 'meeting_upcoming', 'user_id' => $participant->id])->exists());
        $this->assertTrue(SentReminder::query()->where(['reminder_type' => 'meeting_upcoming', 'user_id' => $organizer->id])->exists());
        $this->assertTrue(SentReminder::query()->where(['reminder_type' => 'document_expiry', 'user_id' => $organizer->id])->exists());

        $notification = $participant->notifications()->first();
        $this->assertSame('reminders.meeting_upcoming', $notification->data['title_key']);
        $this->assertStringStartsWith('/meetings/', $notification->data['url']);
    }

    public function test_suspended_users_receive_no_reminders(): void
    {
        $assignee = $this->userWithRole('staff', ['status' => 'suspended']);
        Task::query()->create(['reference' => 'T-9', 'title' => 'X', 'status' => 'todo', 'due_date' => today()->subDay(), 'assigned_to' => $assignee->id]);

        $this->artisan('ndc:send-reminders')->assertSuccessful();

        $this->assertSame(0, $assignee->notifications()->count());
    }

    public function test_notification_link_only_redirects_inside_the_application(): void
    {
        $user = $this->userWithRole('staff');
        $user->notifications()->create(['id' => (string) \Illuminate\Support\Str::uuid(), 'type' => 'x', 'data' => ['title_key' => 'notifications.generic', 'params' => [], 'url' => 'https://evil.example/phish']]);
        $user->notifications()->create(['id' => (string) \Illuminate\Support\Str::uuid(), 'type' => 'x', 'data' => ['title_key' => 'notifications.generic', 'params' => [], 'url' => '/tasks']]);
        [$bad, $good] = [$user->notifications()->get()->firstWhere('data.url', 'https://evil.example/phish'), $user->notifications()->get()->firstWhere('data.url', '/tasks')];

        $this->actingAs($user)->get(route('notifications.open', $bad->id))->assertRedirect(route('notifications.index'));
        $this->actingAs($user)->get(route('notifications.open', $good->id))->assertRedirect('/tasks');
        $this->assertNotNull($bad->fresh()->read_at);
    }

    public function test_users_cannot_open_other_users_notifications(): void
    {
        $owner = $this->userWithRole('staff');
        $intruder = $this->userWithRole('staff');
        $owner->notifications()->create(['id' => $id = (string) \Illuminate\Support\Str::uuid(), 'type' => 'x', 'data' => ['title_key' => 'notifications.generic', 'params' => [], 'url' => '/tasks']]);

        $this->actingAs($intruder)->get(route('notifications.open', $id))->assertNotFound();
    }

    public function test_email_channel_is_used_only_when_enabled(): void
    {
        NotificationFacade::fake();
        $user = $this->userWithRole('staff');

        \App\Services\Notifier::send($user, 'test', 'notifications.generic');
        NotificationFacade::assertSentTo($user, \App\Notifications\SystemNotification::class, fn ($n, $channels) => $channels === ['database']);

        \App\Support\Settings::set('notifications.email_enabled', true);
        \App\Services\Notifier::send($user, 'test', 'notifications.generic');
        NotificationFacade::assertSentTo($user, \App\Notifications\SystemNotification::class, fn ($n, $channels) => in_array('mail', $channels, true));
    }

    public function test_delivery_status_is_logged(): void
    {
        $user = $this->userWithRole('staff');
        \App\Services\Notifier::send($user, 'test', 'notifications.generic');

        $this->assertDatabaseHas('notification_logs', ['user_id' => $user->id, 'channel' => 'database', 'status' => 'sent']);
        $this->actingAs($user)->get('/notifications')->assertOk()->assertSee(__('notifications.generic'));
    }
}
