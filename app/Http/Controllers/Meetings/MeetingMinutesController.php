<?php

namespace App\Http\Controllers\Meetings;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Support\Audit;
use App\Support\Status;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Attendance, minutes and the printable minutes / meeting report.
 */
class MeetingMinutesController extends Controller
{
    /**
     * Printable minutes: header, attendees, agenda, minutes, decisions and action points.
     */
    public function show(Meeting $meeting): View
    {
        $this->authorize('view', $meeting);

        $meeting->load(['organizer', 'department', 'project', 'minutesRecorder', 'participants.user', 'actionItems.assignee']);

        return view('meetings.minutes', ['meeting' => $meeting]);
    }

    public function update(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->authorize('update', $meeting);

        $data = $request->validate([
            'minutes' => ['required', 'string', 'max:100000'],
            'mark_completed' => ['boolean'],
        ]);

        $meeting->forceFill([
            'minutes' => $data['minutes'],
            'minutes_recorded_by' => $request->user()->id,
            'minutes_recorded_at' => now(),
        ]);
        if ($request->boolean('mark_completed') && $meeting->status === 'scheduled') {
            $meeting->status = 'completed';
        }
        $meeting->save();
        Audit::log('minutes_recorded', $meeting);

        return redirect()->to(route('meetings.show', $meeting).'#minutes')->with('success', __('Minutes saved.'));
    }

    /**
     * Record who attended, after the meeting.
     */
    public function attendance(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->authorize('update', $meeting);

        $data = $request->validate([
            'attendance' => ['required', 'array'],
            'attendance.*' => [Rule::in(Status::keys('attendance'))],
        ]);

        $participants = $meeting->participants()->whereIn('id', array_keys($data['attendance']))->get();
        foreach ($participants as $participant) {
            $participant->update(['attendance' => $data['attendance'][$participant->id]]);
        }
        Audit::log('attendance_recorded', $meeting, null, null, [
            'attended' => $participants->where('attendance', 'attended')->count(),
            'absent' => $participants->where('attendance', 'absent')->count(),
            'excused' => $participants->where('attendance', 'excused')->count(),
        ]);

        return redirect()->to(route('meetings.show', $meeting).'#participants')->with('success', __('Attendance recorded.'));
    }
}
