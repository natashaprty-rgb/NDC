<?php

namespace App\Http\Controllers\Meetings;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MeetingActionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Month calendar of meetings, appointments and events, together with the
 * deadlines of outstanding action points.
 */
class MeetingCalendarController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize('viewAny', Meeting::class);

        $month = today()->startOfMonth();
        $param = $request->query('month');
        if (is_string($param) && preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $param, $m) && (int) $m[1] >= 1970 && (int) $m[1] <= 2200) {
            $month = Carbon::create((int) $m[1], (int) $m[2], 1)->startOfDay();
        }

        $gridStart = $month->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $meetings = Meeting::query()
            ->where('start_at', '>=', $gridStart)
            ->where('start_at', '<=', $gridEnd->copy()->endOfDay())
            ->when($request->boolean('mine'), fn ($q) => $q->where(fn ($q) => $q
                ->where('organizer_id', $request->user()->id)
                ->orWhereHas('participants', fn ($p) => $p->where('user_id', $request->user()->id))))
            ->orderBy('start_at')
            ->get()
            ->groupBy(fn (Meeting $m) => $m->start_at->toDateString());

        $deadlines = MeetingActionItem::query()->outstanding()
            ->whereHas('meeting')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>=', $gridStart)
            ->whereDate('due_date', '<=', $gridEnd)
            ->when($request->boolean('mine'), fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->with('assignee')
            ->orderBy('due_date')
            ->get()
            ->groupBy(fn (MeetingActionItem $a) => $a->due_date->toDateString());

        $days = [];
        for ($day = $gridStart->copy(); $day->lte($gridEnd); $day->addDay()) {
            $days[] = $day->copy();
        }

        return view('meetings.calendar', [
            'month' => $month,
            'days' => $days,
            'meetings' => $meetings,
            'deadlines' => $deadlines,
            'prev' => $month->copy()->subMonth()->format('Y-m'),
            'next' => $month->copy()->addMonth()->format('Y-m'),
        ]);
    }
}
