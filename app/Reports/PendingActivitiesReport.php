<?php

namespace App\Reports;

use App\Models\Approval;
use App\Models\Correspondence;
use App\Models\MeetingActionItem;
use App\Models\ProjectMilestone;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Everything waiting on someone: open approval requests and overdue tasks,
 * milestones, correspondence responses and meeting action points.
 */
class PendingActivitiesReport extends Report
{
    public function key(): string
    {
        return 'pending';
    }

    public function title(): string
    {
        return __('Pending approvals and overdue activities');
    }

    public function description(): string
    {
        return __('Open approval requests and every overdue item across modules the viewer can access.');
    }

    public function permission(): string
    {
        return 'dashboard.view';
    }

    public function filters(): array
    {
        return [
            'kind' => ['label' => 'Show', 'type' => 'select', 'options' => ['approvals' => __('Pending approvals'), 'overdue' => __('Overdue activities')]],
        ];
    }

    public function columns(): array
    {
        return ['Type', 'Item', 'Responsible', 'Since / due', 'Days'];
    }

    public function numericColumns(): array
    {
        return [4];
    }

    public function rows(Request $request, User $user): iterable
    {
        $rows = collect();
        $kind = $request->query('kind');

        if ($kind !== 'overdue') {
            Approval::query()->with(['approvable', 'requester'])->where('status', 'pending')->oldest()->get()
                ->filter(fn (Approval $a) => $a->approvable && ($user->can($a->permission) || $a->requested_by === $user->id))
                ->each(fn (Approval $a) => $rows->push([__('Approval'), $a->approvable->approvalTitle(), $a->requester?->name, $a->created_at, $a->created_at]));

            if ($user->can('tasks.review')) {
                Task::query()->visibleTo($user)->where('status', 'submitted')->with('assignee')->get()
                    ->each(fn (Task $t) => $rows->push([__('Task review'), $t->title, $t->assignee?->name, $t->submitted_at, $t->submitted_at]));
            }
        }

        if ($kind !== 'approvals') {
            $this->overdue($user)->each(fn ($row) => $rows->push($row));
        }

        return $rows->map(fn ($r) => [$r[0], $r[1], $r[2], fmt_date($r[3]), $r[4] ? (int) $r[4]->copy()->startOfDay()->diffInDays(today()) : '—']);
    }

    private function overdue(User $user): Collection
    {
        $rows = collect();

        if ($user->can('tasks.view')) {
            Task::query()->visibleTo($user)->overdue()->with('assignee')->orderBy('due_date')->get()
                ->each(fn (Task $t) => $rows->push([__('Task'), $t->title, $t->assignee?->name, $t->due_date, $t->due_date]));
        }
        if ($user->can('projects.view')) {
            ProjectMilestone::query()->with('project.manager')->where('status', '!=', 'completed')->whereDate('due_date', '<', today())->orderBy('due_date')->get()
                ->each(fn (ProjectMilestone $m) => $rows->push([__('Milestone'), $m->project?->code.' — '.$m->title, $m->project?->manager?->name, $m->due_date, $m->due_date]));
        }
        if ($user->can('correspondence.view')) {
            Correspondence::query()->visibleTo($user)->overdue()->with('assignee')->orderBy('response_due_date')->get()
                ->each(fn (Correspondence $c) => $rows->push([__('Correspondence response'), $c->reference_number.' — '.$c->subject, $c->assignee?->name, $c->response_due_date, $c->response_due_date]));
        }
        if ($user->can('meetings.view')) {
            MeetingActionItem::query()->outstanding()->whereDate('due_date', '<', today())->with('assignee')->orderBy('due_date')->get()
                ->each(fn (MeetingActionItem $a) => $rows->push([__('Meeting action point'), $a->description, $a->assignee?->name, $a->due_date, $a->due_date]));
        }

        return $rows;
    }
}
