<?php

namespace App\Http\Controllers\Meetings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Meetings\MeetingRequest;
use App\Models\Department;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\User;
use App\Services\Meetings\MeetingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MeetingController extends Controller
{
    public function __construct(private readonly MeetingService $service) {}

    /** Same rule as MeetingPolicy::view: everything with meetings.view, otherwise own meetings only. */
    public static function visibleQuery(User $user): Builder
    {
        $query = Meeting::query();
        if (! $user->can('meetings.view')) {
            $query->where(fn (Builder $q) => $q->where('organizer_id', $user->id)
                ->orWhereHas('participants', fn (Builder $p) => $p->where('user_id', $user->id)));
        }

        return $query;
    }

    public function index(Request $request): View
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'type' => ['nullable', Rule::in(Meeting::TYPES)],
            'status' => ['nullable', Rule::in(Meeting::STATUSES)],
        ]);
        $tab = in_array($request->query('tab'), ['upcoming', 'past', 'all'], true) ? $request->query('tab') : 'upcoming';

        $meetings = self::visibleQuery($request->user())
            ->with(['organizer', 'department', 'project'])
            ->withCount('participants')
            ->when($tab === 'upcoming', fn ($q) => $q->where('starts_at', '>=', now()->startOfDay())->orderBy('starts_at'))
            ->when($tab === 'past', fn ($q) => $q->where('starts_at', '<', now()->startOfDay())->orderByDesc('starts_at'))
            ->when($tab === 'all', fn ($q) => $q->orderByDesc('starts_at'))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->trim().'%';
                $q->where(fn ($w) => $w->where('title', 'like', $term)->orWhere('reference', 'like', $term)
                    ->orWhere('agenda', 'like', $term)->orWhere('minutes', 'like', $term)->orWhere('location', 'like', $term));
            })
            ->when($request->filled('type'), fn ($q) => $q->where('meeting_type', $request->query('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('department'), fn ($q) => $q->where('department_id', (int) $request->query('department')))
            ->when($request->filled('project'), fn ($q) => $q->where('project_id', (int) $request->query('project')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('starts_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('starts_at', '<=', $request->date('to')))
            ->paginate(25)
            ->withQueryString();

        return view('meetings.index', [
            'meetings' => $meetings,
            'tab' => $tab,
            'departments' => Department::query()->orderBy('name')->pluck('name', 'id')->all(),
            'projects' => Project::query()->notArchived()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function calendar(Request $request): View
    {
        $month = $request->query('month');
        $start = is_string($month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)
            ? Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfDay()
            : now()->startOfMonth();
        $gridStart = $start->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $start->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $meetings = self::visibleQuery($request->user())
            ->whereBetween('starts_at', [$gridStart, $gridEnd])
            ->orderBy('starts_at')
            ->get();
        $byDay = $meetings->groupBy(fn (Meeting $m) => $m->starts_at->toDateString());

        $weeks = [];
        for ($day = $gridStart->copy(); $day->lte($gridEnd); $day->addDay()) {
            $weeks[intdiv($gridStart->diffInDays($day), 7)][] = $day->copy();
        }

        return view('meetings.calendar', [
            'month' => $start,
            'weeks' => $weeks,
            'byDay' => $byDay,
            'monthMeetings' => $meetings->filter(fn (Meeting $m) => $m->starts_at->isSameMonth($start)),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Meeting::class);
        $meeting = new Meeting(['meeting_type' => 'internal', 'status' => 'scheduled', 'organizer_id' => $request->user()->id]);
        if ($request->filled('project')) {
            $meeting->project_id = (int) $request->query('project');
        }

        return view('meetings.form', ['meeting' => $meeting] + $this->formOptions());
    }

    public function store(MeetingRequest $request): RedirectResponse
    {
        $meeting = $this->service->create($request->validated(), $request->user());

        return redirect()->route('meetings.show', $meeting)->with('success', __('common.created'));
    }

    public function show(Meeting $meeting): View
    {
        $meeting->load(['participants.user', 'organizer', 'department', 'project', 'items.responsible', 'items.task']);
        $this->authorize('view', $meeting);

        return view('meetings.show', [
            'meeting' => $meeting,
            'users' => User::query()->active()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function edit(Meeting $meeting): View
    {
        $this->authorize('update', $meeting);
        $meeting->load('participants');

        return view('meetings.form', ['meeting' => $meeting] + $this->formOptions());
    }

    public function update(MeetingRequest $request, Meeting $meeting): RedirectResponse
    {
        $this->service->update($meeting, $request->validated());

        return redirect()->route('meetings.show', $meeting)->with('success', __('common.updated'));
    }

    public function changeStatus(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->authorize('update', $meeting);
        $data = $request->validate(['status' => ['required', Rule::in(Meeting::STATUSES)]]);
        $meeting->update($data);

        return back()->with('success', __('meetings.status_changed'));
    }

    public function destroy(Meeting $meeting): RedirectResponse
    {
        $this->authorize('delete', $meeting);
        $meeting->delete();

        return redirect()->route('meetings.index')->with('success', __('common.archived'));
    }

    private function formOptions(): array
    {
        return [
            'users' => User::query()->active()->orderBy('name')->pluck('name', 'id')->all(),
            'departments' => Department::query()->active()->orderBy('name')->pluck('name', 'id')->all(),
            'projects' => Project::query()->notArchived()->orderBy('name')->pluck('name', 'id')->all(),
        ];
    }
}
