<?php

namespace App\Http\Controllers\Meetings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Meetings\MeetingItemRequest;
use App\Http\Requests\Meetings\MinutesRequest;
use App\Models\Meeting;
use App\Models\MeetingItem;
use App\Models\Task;
use App\Services\Meetings\MeetingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MeetingMinutesController extends Controller
{
    public function edit(Meeting $meeting): View
    {
        $this->authorize('recordMinutes', $meeting);
        $meeting->load('participants.user');

        return view('meetings.minutes', compact('meeting'));
    }

    public function update(MinutesRequest $request, Meeting $meeting): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($meeting, $data, $request) {
            $attributes = [
                'minutes' => $data['minutes'] ?? null,
                'minutes_recorded_by' => $request->user()->id,
                'minutes_recorded_at' => now(),
            ];
            if (! empty($data['mark_completed'])) {
                $attributes['status'] = 'completed';
            }
            $meeting->update($attributes);

            foreach ($data['attendance'] ?? [] as $participantId => $attendance) {
                $meeting->participants()->whereKey((int) $participantId)->update(['attendance' => $attendance]);
            }
        });

        return redirect()->route('meetings.show', $meeting)->with('success', __('meetings.minutes_saved'));
    }

    public function storeItem(MeetingItemRequest $request, Meeting $meeting): RedirectResponse
    {
        $meeting->items()->create($request->validated() + ['status' => 'open']);

        return redirect()->to(route('meetings.show', $meeting).'#items')->with('success', __('meetings.item_saved'));
    }

    public function updateItem(MeetingItemRequest $request, Meeting $meeting, MeetingItem $item): RedirectResponse
    {
        abort_unless($item->meeting_id === $meeting->id, 404);
        $item->update($request->validated());

        return redirect()->to(route('meetings.show', $meeting).'#items')->with('success', __('meetings.item_saved'));
    }

    public function convertToTask(Request $request, Meeting $meeting, MeetingItem $item, MeetingService $service): RedirectResponse
    {
        abort_unless($item->meeting_id === $meeting->id, 404);
        $this->authorize('recordMinutes', $meeting);
        $this->authorize('create', Task::class);

        if ($item->type !== 'action' || $item->task_id !== null) {
            return back()->with('error', __('meetings.already_converted'));
        }
        if ($item->responsible_id === null) {
            return back()->with('error', __('meetings.cannot_convert'));
        }

        $task = $service->convertActionToTask($meeting, $item, $request->user());

        return redirect()->to(route('meetings.show', $meeting).'#items')->with('success', __('meetings.converted', ['ref' => $task->reference]));
    }
}
