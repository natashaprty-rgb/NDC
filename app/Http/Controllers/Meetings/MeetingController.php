<?php

namespace App\Http\Controllers\Meetings;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\Project;
use App\Models\User;
use App\Notifications\GenericNotification;
use App\Support\Audit;
use App\Support\Status;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Meetings, appointments and official events with their participants.
 * Minutes and attendance: MeetingMinutesController; decisions and action
 * points: MeetingActionItemController; month view: MeetingCalendarController.
 */
class MeetingController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Meeting::class);

        $when = in_array($request->query('when'), ['upcoming', 'past', 'all'], true) ? $request->query('when') : 'upcoming';

        $query = Meeting::query()->with(['organizer', 'department'])->withCount('participants')
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('title', 'like', "%{$term}%")
                ->orWhere('location', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")))
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('department'), fn ($q, $d) => $q->where('department_id', (int) $d))
            ->when($request->query('project'), fn ($q, $p) => $q->where('project_id', (int) $p))
            ->when($request->boolean('mine'), fn ($q) => $q->where(fn ($q) => $q
                ->where('organizer_id', $request->user()->id)
                ->orWhereHas('participants', fn ($p) => $p->where('user_id', $request->user()->id))))
            ->when($when === 'upcoming', fn ($q) => $q->where(fn ($q) => $q
                ->where('start_at', '>=', now()->startOfDay())->orWhere('end_at', '>=', now())))
            ->when($when === 'past', fn ($q) => $q->where('start_at', '<', now()->startOfDay()));

        $this->applySort($query, $request, ['title', 'start_at', 'status', 'type'], 'start_at', $when === 'upcoming' ? 'asc' : 'desc');

        return view('meetings.index', [
            'meetings' => $query->paginate($this->perPage($request))->withQueryString(),
            'when' => $when,
            'departments' => Department::options(),
            'projects' => Project::options(),
            'outstanding' => MeetingActionItem::query()->outstanding()->whereHas('meeting')->count(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Meeting::class);

        $start = now()->addDay()->setTime(9, 0);
        if (is_string($request->query('date')) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $request->query('date'))) {
            $start = Carbon::parse($request->query('date'))->setTime(9, 0);
        }

        $meeting = new Meeting([
            'type' => 'meeting',
            'status' => 'scheduled',
            'start_at' => $start,
            'end_at' => $start->copy()->addHour(),
            'organizer_id' => $request->user()->id,
            'department_id' => $request->user()->department_id,
            'project_id' => $request->integer('project') ?: null,
        ]);

        return view('meetings.form', $this->formData($meeting));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Meeting::class);
        $data = $this->validated($request);

        [$meeting, $invited] = DB::transaction(function () use ($data, $request) {
            $meeting = Meeting::query()->create([
                ...$this->meetingAttributes($data),
                'status' => 'scheduled',
                'created_by' => $request->user()->id,
            ]);
            $invited = $this->syncParticipants($meeting, $data);

            return [$meeting, $invited];
        });

        $this->notifyInvited($meeting, $invited, $request->user());

        return redirect()->route('meetings.show', $meeting)->with('success', __('Meeting scheduled.'));
    }

    public function show(Meeting $meeting): View
    {
        $this->authorize('view', $meeting);

        $meeting->load([
            'organizer', 'department', 'project', 'minutesRecorder', 'attachments.uploader',
            'participants.user.department', 'actionItems.assignee',
        ]);

        return view('meetings.show', [
            'meeting' => $meeting,
            'users' => $this->userOptions(),
        ]);
    }

    public function edit(Meeting $meeting): View
    {
        $this->authorize('update', $meeting);
        $meeting->load('participants.user');

        return view('meetings.form', $this->formData($meeting));
    }

    public function update(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->authorize('update', $meeting);
        $data = $this->validated($request);

        $originalStart = $meeting->start_at?->copy();
        $originalLocation = $meeting->location;

        $invited = DB::transaction(function () use ($meeting, $data) {
            $meeting->update($this->meetingAttributes($data));

            return $this->syncParticipants($meeting, $data);
        });

        $this->notifyInvited($meeting, $invited, $request->user());

        // Tell the people already invited when the time or place changes.
        if (! $meeting->start_at->equalTo($originalStart) || $meeting->location !== $originalLocation) {
            $this->notifyParticipants($meeting, $request->user(), 'meeting_rescheduled', 'Meeting rescheduled',
                'The meeting ":title" has been changed. New time: :date, location: :location.', $invited);
        }

        return redirect()->route('meetings.show', $meeting)->with('success', __('Meeting updated.'));
    }

    /**
     * Mark a meeting as completed, postponed, cancelled or scheduled again.
     */
    public function status(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->authorize('update', $meeting);

        $data = $request->validate([
            'status' => ['required', Rule::in(Status::keys('meeting'))],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['status'] === $meeting->status) {
            return back();
        }

        $meeting->update(['status' => $data['status']]);
        Audit::log('status_changed', $meeting, trim(Status::label('meeting', $data['status']).' '.($data['reason'] ?? '')));

        if (in_array($data['status'], ['postponed', 'cancelled'], true)) {
            $this->notifyParticipants($meeting, $request->user(), 'meeting_'.$data['status'],
                $data['status'] === 'cancelled' ? 'Meeting cancelled' : 'Meeting postponed',
                $data['status'] === 'cancelled' ? 'The meeting ":title" planned for :date has been cancelled.' : 'The meeting ":title" planned for :date has been postponed.');
        }

        return back()->with('success', __('Meeting status changed to :status.', ['status' => Status::label('meeting', $data['status'])]));
    }

    public function destroy(Meeting $meeting): RedirectResponse
    {
        $this->authorize('delete', $meeting);

        $meeting->delete();

        return redirect()->route('meetings.index')->with('success', __('Meeting deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(Status::keys('meeting_type'))],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after:start_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'organizer_id' => ['nullable', Rule::exists('users', 'id')],
            'department_id' => ['nullable', Rule::exists('departments', 'id')],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:10000'],
            'agenda' => ['nullable', 'string', 'max:20000'],
            'participants' => ['array'],
            'participants.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
            'guests' => ['array', 'max:100'],
            'guests.*.id' => ['nullable', 'integer'],
            'guests.*.name' => ['nullable', 'required_with:guests.*.organization,guests.*.email', 'string', 'max:255'],
            'guests.*.organization' => ['nullable', 'string', 'max:255'],
            'guests.*.email' => ['nullable', 'email', 'max:255'],
        ], [
            'end_at.after' => __('The end time must be after the start time.'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function meetingAttributes(array $data): array
    {
        return collect($data)->only([
            'title', 'type', 'start_at', 'end_at', 'location', 'organizer_id', 'department_id', 'project_id',
            'description', 'agenda',
        ])->all();
    }

    /**
     * Bring internal participants and external guests in line with the form,
     * keeping recorded attendance for people who stay on the list.
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, User> newly invited internal users
     */
    private function syncParticipants(Meeting $meeting, array $data): Collection
    {
        $userIds = collect($data['participants'] ?? [])->map(fn ($id) => (int) $id)->unique();
        $existing = $meeting->participants()->whereNotNull('user_id')->pluck('user_id');

        $meeting->participants()->whereNotNull('user_id')->whereNotIn('user_id', $userIds)->delete();
        $newIds = $userIds->diff($existing)->values();
        foreach ($newIds as $userId) {
            $meeting->participants()->create(['user_id' => $userId, 'attendance' => 'invited']);
        }

        $guests = collect($data['guests'] ?? [])->filter(fn ($g) => filled($g['name'] ?? null));
        $keep = $guests->pluck('id')->filter()->map(fn ($id) => (int) $id);
        $meeting->participants()->whereNull('user_id')->whereNotIn('id', $keep)->delete();
        foreach ($guests as $guest) {
            $attributes = [
                'name' => $guest['name'],
                'organization' => $guest['organization'] ?? null,
                'email' => $guest['email'] ?? null,
            ];
            $row = ! empty($guest['id']) ? $meeting->participants()->whereNull('user_id')->find($guest['id']) : null;
            $row ? $row->update($attributes) : $meeting->participants()->create($attributes + ['attendance' => 'invited']);
        }

        $changes = ['participants' => $userIds->count(), 'guests' => $guests->count()];
        if ($newIds->isNotEmpty() || $existing->diff($userIds)->isNotEmpty()) {
            Audit::log('participants_updated', $meeting, null, null, $changes);
        }

        return User::query()->whereIn('id', $newIds)->get();
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function notifyInvited(Meeting $meeting, Collection $users, User $actor): void
    {
        foreach ($users as $user) {
            if ($user->is($actor) || ! $user->isActive()) {
                continue;
            }
            $user->notify(new GenericNotification(
                'meeting_invitation',
                'Meeting invitation',
                'You are invited to ":title" on :date at :location.',
                $this->replacements($meeting),
                route('meetings.show', $meeting),
            ));
        }
    }

    /**
     * Notify internal participants (and the organiser) about a change.
     *
     * @param  Collection<int, User>|null  $except  users who already received another notice
     */
    private function notifyParticipants(Meeting $meeting, User $actor, string $kind, string $title, string $body, ?Collection $except = null): void
    {
        $skip = ($except ?? collect())->pluck('id')->push($actor->id);
        $ids = $meeting->participants()->whereNotNull('user_id')->pluck('user_id')->push($meeting->organizer_id)->filter()->unique();

        User::query()->active()->whereIn('id', $ids->diff($skip))->get()
            ->each(fn (User $user) => $user->notify(new GenericNotification($kind, $title, $body, $this->replacements($meeting), route('meetings.show', $meeting))));
    }

    /**
     * @return array<string, string>
     */
    private function replacements(Meeting $meeting): array
    {
        return [
            'title' => $meeting->title,
            'date' => $meeting->start_at->format('Y-m-d H:i'),
            'location' => $meeting->location ?: '—',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Meeting $meeting): array
    {
        return [
            'meeting' => $meeting,
            'users' => $this->userOptions(),
            'departments' => Department::options(),
            'projects' => Project::options(),
            'selectedParticipants' => $meeting->exists
                ? $meeting->participants->whereNotNull('user_id')->pluck('user_id')->all()
                : [],
            'guests' => $meeting->exists ? $meeting->participants->whereNull('user_id')->values() : collect(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function userOptions(): array
    {
        return User::query()->active()->orderBy('name')->pluck('name', 'id')->all();
    }
}
