<?php

namespace App\Services\Meetings;

use App\Models\Meeting;
use App\Models\MeetingItem;
use App\Models\Task;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifier;
use App\Support\ReferenceNumber;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MeetingService
{
    public function create(array $data, User $actor): Meeting
    {
        $meeting = DB::transaction(function () use ($data, $actor) {
            $meeting = Meeting::query()->create($this->attributes($data) + [
                'reference' => ReferenceNumber::next('meeting'),
                'organizer_id' => $data['organizer_id'] ?? $actor->id,
            ]);
            $this->syncParticipants($meeting, $data);

            return $meeting;
        });

        $this->notifyParticipants($meeting, $meeting->participants()->pluck('user_id')->filter()->all(), 'meetings.notify.invited');

        return $meeting;
    }

    public function update(Meeting $meeting, array $data): Meeting
    {
        $before = $meeting->participants()->pluck('user_id')->filter()->all();
        $oldStart = $meeting->starts_at?->toDateTimeString();
        $oldLocation = $meeting->location;

        DB::transaction(function () use ($meeting, $data) {
            $meeting->update($this->attributes($data) + ['organizer_id' => $data['organizer_id'] ?? $meeting->organizer_id]);
            $this->syncParticipants($meeting, $data);
        });
        $meeting->refresh();

        $now = $meeting->participants()->pluck('user_id')->filter()->all();
        $added = array_diff($now, $before);
        if ($added) {
            $this->notifyParticipants($meeting, $added, 'meetings.notify.invited');
        }
        if ($oldStart !== $meeting->starts_at?->toDateTimeString() || $oldLocation !== $meeting->location) {
            $this->notifyParticipants($meeting, array_diff($now, $added), 'meetings.notify.updated');
        }

        return $meeting;
    }

    public function convertActionToTask(Meeting $meeting, MeetingItem $item, User $actor): Task
    {
        $task = DB::transaction(function () use ($meeting, $item, $actor) {
            $task = Task::query()->create([
                'reference' => ReferenceNumber::next('task'),
                'project_id' => $meeting->project_id,
                'department_id' => $meeting->department_id,
                'title' => Str::limit($item->description, 195),
                'description' => $item->description."\n\n".__('meetings.task_source', ['ref' => $meeting->reference, 'title' => $meeting->title]),
                'priority' => 'medium',
                'status' => 'todo',
                'due_date' => $item->due_date,
                'assigned_to' => $item->responsible_id,
                'assigned_by' => $actor->id,
            ]);
            $item->update(['task_id' => $task->id]);
            AuditLogger::log('task_created_from_action', $meeting, 'Action point converted to task '.$task->reference);

            return $task;
        });

        Notifier::send($item->responsible, 'task_assigned', 'meetings.notify.action_assigned', [
            'title' => $meeting->title,
            'due' => fmt_date($task->due_date),
        ], route_if('tasks.show', $task) ?? route('meetings.show', $meeting));

        return $task;
    }

    private function attributes(array $data): array
    {
        return Arr::only($data, ['title', 'meeting_type', 'description', 'location', 'online_link', 'starts_at', 'ends_at', 'status', 'department_id', 'project_id', 'agenda']);
    }

    private function syncParticipants(Meeting $meeting, array $data): void
    {
        $userIds = array_map('intval', $data['participant_ids'] ?? []);
        $meeting->participants()->whereNotNull('user_id')->whereNotIn('user_id', $userIds ?: [0])->delete();
        $existing = $meeting->participants()->whereNotNull('user_id')->pluck('user_id')->all();
        foreach (array_diff($userIds, $existing) as $userId) {
            $meeting->participants()->create(['user_id' => $userId, 'attendance' => 'invited']);
        }

        // External participants are replaced by the submitted list (attendance kept by name).
        $previous = $meeting->participants()->whereNull('user_id')->get()->keyBy(fn ($p) => mb_strtolower($p->external_name));
        $meeting->participants()->whereNull('user_id')->delete();
        foreach ($data['externals'] ?? [] as $row) {
            $meeting->participants()->create([
                'external_name' => $row['name'],
                'external_organization' => $row['organization'] ?? null,
                'external_email' => $row['email'] ?? null,
                'attendance' => $previous->get(mb_strtolower($row['name']))?->attendance ?? 'invited',
            ]);
        }
    }

    private function notifyParticipants(Meeting $meeting, array $userIds, string $key): void
    {
        if ($userIds === []) {
            return;
        }
        Notifier::send(User::query()->whereIn('id', $userIds)->get(), 'meeting', $key, [
            'title' => $meeting->title,
            'when' => fmt_datetime($meeting->starts_at),
            'location' => $meeting->location ?: '—',
        ], route('meetings.show', $meeting));
    }
}
