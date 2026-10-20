<?php

namespace App\Http\Controllers\Meetings;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\User;
use App\Notifications\GenericNotification;
use App\Support\Status;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Decisions and action points recorded in meeting minutes, and the
 * cross-meeting list of outstanding action points.
 */
class MeetingActionItemController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Meeting::class);

        $status = in_array($request->query('status'), Status::keys('action_item'), true) ? $request->query('status') : null;
        $assignee = $request->query('assignee') === 'me' ? $request->user()->id : (int) $request->query('assignee');

        $query = MeetingActionItem::query()
            ->whereHas('meeting')
            ->with(['meeting', 'assignee'])
            ->when($status, fn ($q) => $q->where('status', $status), fn ($q) => $q->outstanding())
            ->when($assignee, fn ($q) => $q->where('assigned_to', $assignee))
            ->when(in_array($request->query('type'), Status::keys('action_item_type'), true), fn ($q) => $q->where('type', $request->query('type')))
            ->when($request->boolean('overdue'), fn ($q) => $q->overdue())
            ->when($request->query('q'), fn ($q, $term) => $q->where('description', 'like', "%{$term}%"));

        $sort = $request->query('sort');
        if (in_array($sort, ['due_date', 'status', 'created_at'], true)) {
            $this->applySort($query, $request, ['due_date', 'status', 'created_at'], 'due_date', 'asc');
        } else {
            // Items with a deadline first, earliest first.
            $query->orderByRaw('due_date is null')->orderBy('due_date')->orderBy('id');
        }

        return view('meetings.action-items', [
            'items' => $query->paginate($this->perPage($request))->withQueryString(),
            'users' => User::query()->active()->orderBy('name')->pluck('name', 'id')->all(),
            'overdueCount' => MeetingActionItem::query()->whereHas('meeting')->overdue()->count(),
            'mineCount' => MeetingActionItem::query()->whereHas('meeting')->outstanding()->where('assigned_to', $request->user()->id)->count(),
        ]);
    }

    public function store(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->authorize('update', $meeting);

        $item = $meeting->actionItems()->create([
            ...$this->validated($request),
            'status' => 'open',
        ]);

        $this->notifyAssignee($item, $request->user());

        return redirect()->to(route('meetings.show', $meeting).'#action-items')
            ->with('success', $item->type === 'decision' ? __('Decision recorded.') : __('Action point recorded.'));
    }

    public function edit(MeetingActionItem $actionItem): View
    {
        $this->authorize('update', $actionItem);
        $actionItem->load('meeting');

        return view('meetings.action-item-form', [
            'item' => $actionItem,
            'users' => User::query()->active()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function update(Request $request, MeetingActionItem $actionItem): RedirectResponse
    {
        $this->authorize('update', $actionItem);

        $data = $this->validated($request, withStatus: true);
        $previousAssignee = $actionItem->assigned_to;

        $actionItem->fill($data);
        $this->applyCompletion($actionItem);
        $actionItem->save();

        if ($actionItem->assigned_to && $actionItem->assigned_to !== $previousAssignee) {
            $this->notifyAssignee($actionItem, $request->user());
        }

        return redirect()->to(route('meetings.show', $actionItem->meeting_id).'#action-items')->with('success', __('Action point updated.'));
    }

    /**
     * Progress update; also open to the assignee without meetings.update.
     */
    public function updateStatus(Request $request, MeetingActionItem $actionItem): RedirectResponse
    {
        $this->authorize('updateStatus', $actionItem);
        abort_if($actionItem->meeting()->doesntExist(), 404);

        $data = $request->validate([
            'status' => ['required', Rule::in(Status::keys('action_item'))],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $actionItem->status = $data['status'];
        if (array_key_exists('notes', $data) && $data['notes'] !== null) {
            $actionItem->notes = $data['notes'];
        }
        $this->applyCompletion($actionItem);
        $actionItem->save();

        // Let the organiser know when an action point they own is completed by someone else.
        $organizer = $actionItem->meeting->organizer;
        if ($data['status'] === 'done' && $organizer && ! $organizer->is($request->user()) && $organizer->isActive()) {
            $organizer->notify(new GenericNotification(
                'meeting_action_completed',
                'Action point completed',
                ':name completed the action point ":description" from the meeting ":title".',
                ['name' => $request->user()->name, 'description' => mb_substr($actionItem->description, 0, 120), 'title' => $actionItem->meeting->title],
                route('meetings.show', $actionItem->meeting_id),
            ));
        }

        return back()->with('success', __('Action point status changed to :status.', ['status' => Status::label('action_item', $data['status'])]));
    }

    public function destroy(MeetingActionItem $actionItem): RedirectResponse
    {
        $this->authorize('delete', $actionItem);

        $meetingId = $actionItem->meeting_id;
        $actionItem->delete();

        return redirect()->to(route('meetings.show', $meetingId).'#action-items')->with('success', __('Action point removed.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $withStatus = false): array
    {
        $rules = [
            'type' => ['required', Rule::in(Status::keys('action_item_type'))],
            'description' => ['required', 'string', 'max:5000'],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('status', 'active')],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
        if ($withStatus) {
            $rules['status'] = ['required', Rule::in(Status::keys('action_item'))];
        }

        return $request->validate($rules);
    }

    private function applyCompletion(MeetingActionItem $item): void
    {
        if ($item->status === 'done') {
            $item->completed_at ??= now();
        } else {
            $item->completed_at = null;
        }
    }

    private function notifyAssignee(MeetingActionItem $item, User $actor): void
    {
        $assignee = $item->assignee()->first();
        if ($assignee === null || $assignee->is($actor) || ! $assignee->isActive()) {
            return;
        }

        $item->loadMissing('meeting');
        $assignee->notify(new GenericNotification(
            'meeting_action_assigned',
            'Action point assigned to you',
            'You have been assigned an action point from the meeting ":title": :description',
            [
                'title' => $item->meeting->title,
                'description' => mb_substr($item->description, 0, 160),
            ],
            route('meetings.show', $item->meeting_id),
        ));
    }
}
