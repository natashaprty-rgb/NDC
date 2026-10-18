<?php

namespace App\Services\Correspondence;

use App\Models\Correspondence;
use App\Models\CorrespondenceAction;
use App\Models\Department;
use App\Models\User;
use App\Services\FileStorage;
use App\Services\Notifier;
use App\Support\ReferenceNumber;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CorrespondenceService
{
    public function register(array $data, ?UploadedFile $scan, User $user): Correspondence
    {
        $item = DB::transaction(function () use ($data, $scan) {
            unset($data['file']);
            $data['reference'] = ReferenceNumber::next($data['direction'] === 'incoming' ? 'correspondence_in' : 'correspondence_out');
            $data['status'] = ! empty($data['assigned_to']) ? 'assigned' : 'registered';

            $item = Correspondence::query()->create($data);
            if ($scan) {
                FileStorage::attach($item, $scan, 'scan');
            }

            return $item;
        });

        if ($item->assigned_to) {
            $this->notifyAssigned($item, User::query()->find($item->assigned_to));
        }

        return $item;
    }

    /** Forward to an officer and/or a department. */
    public function forward(Correspondence $item, User $actor, ?int $toUserId, ?int $toDepartmentId, ?string $note): CorrespondenceAction
    {
        $action = DB::transaction(function () use ($item, $actor, $toUserId, $toDepartmentId, $note) {
            $fromStatus = $item->status;
            $updates = [];
            if ($toUserId) {
                $updates += ['assigned_to' => $toUserId, 'status' => 'assigned', 'closed_at' => null];
            }
            if ($toDepartmentId) {
                $updates['department_id'] = $toDepartmentId;
            }
            if ($updates) {
                $item->update($updates);
            }

            return $item->actions()->create([
                'action' => 'forwarded',
                'from_user_id' => $actor->id,
                'to_user_id' => $toUserId,
                'to_department_id' => $toDepartmentId,
                'from_status' => $fromStatus,
                'to_status' => $item->status,
                'note' => $note,
            ]);
        });

        $recipient = $toUserId ? User::query()->find($toUserId) : null;
        if (! $recipient && $toDepartmentId) {
            $recipient = Department::query()->find($toDepartmentId)?->head;
        }
        $this->notifyAssigned($item, $recipient, 'correspondence.notify.forwarded');

        return $action;
    }

    public function followUp(Correspondence $item, User $actor, string $note, ?string $followUpDate): CorrespondenceAction
    {
        return $item->actions()->create([
            'action' => 'follow_up',
            'from_user_id' => $actor->id,
            'from_status' => $item->status,
            'to_status' => $item->status,
            'note' => $note,
            'follow_up_date' => $followUpDate,
        ]);
    }

    public function changeStatus(Correspondence $item, User $actor, string $status, ?string $note): CorrespondenceAction
    {
        return DB::transaction(function () use ($item, $actor, $status, $note) {
            $from = $item->status;
            $item->update([
                'status' => $status,
                'closed_at' => $status === 'closed' ? now() : null,
            ]);

            return $item->actions()->create([
                'action' => 'status_change',
                'from_user_id' => $actor->id,
                'from_status' => $from,
                'to_status' => $status,
                'note' => $note,
            ]);
        });
    }

    private function notifyAssigned(Correspondence $item, ?User $user, string $key = 'correspondence.notify.assigned'): void
    {
        Notifier::send($user, 'correspondence_assigned', $key,
            ['reference' => $item->reference, 'subject' => $item->subject], route('correspondence.show', $item));
    }
}
