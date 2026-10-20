<?php

namespace Tests\Feature\Core;

use App\Models\Task;
use App\Notifications\GenericNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RemindersTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_and_overdue_tasks_are_reminded_once_per_day(): void
    {
        Notification::fake();
        $user = $this->userWithRole('General Staff');
        Task::query()->create(['title' => 'Due soon', 'assigned_to' => $user->id, 'status' => 'pending', 'due_date' => today()->addDay()]);
        Task::query()->create(['title' => 'Late', 'assigned_to' => $user->id, 'status' => 'in_progress', 'due_date' => today()->subDays(2)]);
        Task::query()->create(['title' => 'Far away', 'assigned_to' => $user->id, 'status' => 'pending', 'due_date' => today()->addMonth()]);
        Task::query()->create(['title' => 'Done', 'assigned_to' => $user->id, 'status' => 'completed', 'due_date' => today()->subDay()]);

        $this->artisan('ndc:send-reminders')->assertSuccessful();
        Notification::assertSentToTimes($user, GenericNotification::class, 2);

        $this->artisan('ndc:send-reminders')->assertSuccessful();
        Notification::assertSentToTimes($user, GenericNotification::class, 2);
    }

    public function test_inactive_users_are_not_notified(): void
    {
        Notification::fake();
        $user = $this->userWithRole('General Staff', ['status' => 'suspended']);
        Task::query()->create(['title' => 'Late', 'assigned_to' => $user->id, 'status' => 'pending', 'due_date' => today()->subDay()]);

        $this->artisan('ndc:send-reminders')->assertSuccessful();
        Notification::assertNothingSent();
    }
}
