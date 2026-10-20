<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Budget;
use App\Models\Correspondence;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Staff;
use App\Models\Task;
use App\Models\User;
use App\Services\ApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Management overview. Every figure is counted from the database at request
 * time and only for modules the user is allowed to see.
 */
class DashboardController extends Controller
{
    private const HORIZON_DAYS = 14;

    public function __invoke(Request $request, ApprovalService $approvals): View
    {
        $user = $request->user();
        $today = today();
        $horizon = $today->copy()->addDays(self::HORIZON_DAYS);

        $data = [
            'user' => $user,
            'tiles' => [],
            'projectStatus' => null,
            'activeProjects' => collect(),
            'overdueTasks' => collect(),
            'myTasks' => collect(),
            'agenda' => collect(),
            'budget' => null,
            'activity' => collect(),
            'pendingApprovals' => $this->pendingApprovalCount($user, $approvals),
            'notifications' => $user->unreadNotifications()->limit(5)->get(),
        ];

        if ($user->can('projects.view')) {
            $projects = Project::query()->active();
            $byStatus = (clone $projects)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
            $data['projectStatus'] = $byStatus;
            $data['tiles'][] = [
                'label' => __('Active projects'),
                'value' => (int) ($byStatus['in_progress'] ?? 0),
                'meta' => __(':n delayed', ['n' => (clone $projects)->delayed()->count()]),
                'alert' => (clone $projects)->delayed()->exists(),
                'url' => $this->link('projects.index'),
            ];
            $data['activeProjects'] = Project::query()->active()->whereIn('status', ['planned', 'in_progress', 'on_hold'])
                ->with('manager')->orderBy('expected_end_date')->limit(6)->get();
        }

        if ($user->can('tasks.view')) {
            $visible = Task::query()->visibleTo($user);
            $overdue = (clone $visible)->overdue();
            $data['tiles'][] = [
                'label' => __('My open tasks'),
                'value' => Task::query()->where('assigned_to', $user->id)->open()->count(),
                'meta' => __(':n overdue', ['n' => Task::query()->where('assigned_to', $user->id)->overdue()->count()]),
                'alert' => Task::query()->where('assigned_to', $user->id)->overdue()->exists(),
                'url' => $this->link('tasks.index', ['mine' => 1]),
            ];
            $data['overdueTasks'] = (clone $overdue)->with(['assignee', 'project'])->orderBy('due_date')->limit(8)->get();
            $data['myTasks'] = Task::query()->where('assigned_to', $user->id)->open()
                ->orderByRaw('due_date is null')->orderBy('due_date')->limit(6)->get();
        }

        if ($user->can('documents.view')) {
            $docs = Document::query()->visibleTo($user)->active();
            $expiring = (clone $docs)->whereNotNull('expiry_date')->whereBetween('expiry_date', [$today, $today->copy()->addDays((int) setting('document_expiry_reminder_days', 30))]);
            $data['tiles'][] = [
                'label' => __('Documents'),
                'value' => (clone $docs)->count(),
                'meta' => __(':n expiring soon', ['n' => $expiring->count()]),
                'alert' => false,
                'url' => $this->link('documents.index'),
            ];
        }

        if ($user->can('assets.view')) {
            $data['tiles'][] = [
                'label' => __('Assets'),
                'value' => Asset::query()->whereNotIn('status', ['disposed'])->count(),
                'meta' => __(':n under maintenance', ['n' => Asset::query()->where('status', 'under_maintenance')->count()]),
                'alert' => false,
                'url' => $this->link('assets.index'),
            ];
        }

        if ($user->can('staff.view')) {
            $data['tiles'][] = [
                'label' => __('Staff'),
                'value' => Staff::query()->where('employment_status', 'active')->count(),
                'meta' => __(':n departments', ['n' => \App\Models\Department::query()->active()->count()]),
                'alert' => false,
                'url' => $this->link('staff.index'),
            ];
        }

        if ($user->can('procurement.view')) {
            $data['tiles'][] = [
                'label' => __('Open purchase requests'),
                'value' => ProcurementRequest::query()->whereIn('status', ['submitted', 'approved', 'ordered', 'partially_received'])->count(),
                'meta' => __(':n awaiting approval', ['n' => ProcurementRequest::query()->where('status', 'submitted')->count()]),
                'alert' => false,
                'url' => $this->link('procurement.index'),
            ];
        }

        if ($user->can('budgets.view')) {
            $approved = Budget::query()->where('status', 'approved');
            $amount = (float) (clone $approved)->sum('amount');
            $spent = (float) Expense::query()->where('status', 'approved')
                ->whereIn('budget_id', (clone $approved)->select('id'))->sum('amount');
            $data['budget'] = [
                'amount' => $amount,
                'spent' => $spent,
                'remaining' => $amount - $spent,
                'pending' => (float) Expense::query()->where('status', 'pending')->sum('amount'),
                'utilisation' => $amount > 0 ? (int) round($spent / $amount * 100) : 0,
                'count' => (clone $approved)->count(),
            ];
        }

        $data['agenda'] = $this->agenda($user, $today, $horizon);

        $data['activity'] = AuditLog::query()->with('user')
            ->when(! $user->can('audit.view'), fn ($q) => $q->where('user_id', $user->id))
            ->whereNotIn('event', ['login_failed'])
            ->latest('created_at')->latest('id')->limit(8)->get();

        return view('dashboard', $data);
    }

    /**
     * URL for a module route, or null while that module is not installed.
     */
    private function link(string $name, mixed $parameters = []): ?string
    {
        return \Illuminate\Support\Facades\Route::has($name) ? route($name, $parameters) : null;
    }

    private function pendingApprovalCount(User $user, ApprovalService $approvals): int
    {
        $count = Approval::query()->where('status', 'pending')->where('requested_by', '!=', $user->id)->get()
            ->filter(fn (Approval $a) => $approvals->canDecide($a, $user))->count();

        if ($user->can('tasks.review')) {
            $count += Task::query()->visibleTo($user)->where('status', 'submitted')
                ->where(fn ($q) => $q->whereNull('assigned_to')->orWhere('assigned_to', '!=', $user->id))->count();
        }

        return $count;
    }

    /**
     * Upcoming meetings and deadlines within the horizon, sorted by date.
     *
     * @return Collection<int, array{date: \Carbon\CarbonInterface, type: string, title: string, url: ?string, overdue?: bool}>
     */
    private function agenda(User $user, $today, $horizon): Collection
    {
        $items = collect();

        if ($user->can('meetings.view')) {
            Meeting::query()->where('status', 'scheduled')->whereBetween('start_at', [now(), $horizon->copy()->endOfDay()])
                ->orderBy('start_at')->limit(10)->get()
                ->each(fn (Meeting $m) => $items->push(['date' => $m->start_at, 'type' => __('Meeting'), 'title' => $m->title, 'url' => $this->link('meetings.show', $m), 'time' => true]));

            MeetingActionItem::query()->outstanding()->where('assigned_to', $user->id)->whereNotNull('due_date')
                ->whereDate('due_date', '<=', $horizon)->with('meeting')->limit(10)->get()
                ->each(fn (MeetingActionItem $a) => $items->push(['date' => $a->due_date, 'type' => __('Action point'), 'title' => \Illuminate\Support\Str::limit($a->description, 80), 'url' => $this->link('meetings.show', $a->meeting_id), 'overdue' => $a->isOverdue()]));
        }

        if ($user->can('tasks.view')) {
            Task::query()->where('assigned_to', $user->id)->open()->whereNotNull('due_date')
                ->whereBetween('due_date', [$today, $horizon])->limit(10)->get()
                ->each(fn (Task $t) => $items->push(['date' => $t->due_date, 'type' => __('Task due'), 'title' => $t->title, 'url' => $this->link('tasks.show', $t)]));
        }

        if ($user->can('projects.view')) {
            ProjectMilestone::query()->whereNot('status', 'completed')->whereNotNull('due_date')
                ->whereBetween('due_date', [$today, $horizon])->with('project')->limit(10)->get()
                ->each(fn (ProjectMilestone $m) => $items->push(['date' => $m->due_date, 'type' => __('Milestone'), 'title' => ($m->project?->code ? $m->project->code.' — ' : '').$m->title, 'url' => $this->link('projects.show', $m->project_id)]));
        }

        if ($user->can('correspondence.view')) {
            Correspondence::query()->visibleTo($user)->open()->whereNotNull('response_due_date')
                ->whereDate('response_due_date', '<=', $horizon)
                ->where(fn ($q) => $q->where('assigned_to', $user->id)->orWhere('responsible_officer_id', $user->id))
                ->limit(10)->get()
                ->each(fn (Correspondence $c) => $items->push(['date' => $c->response_due_date, 'type' => __('Response due'), 'title' => $c->reference_number.' — '.$c->subject, 'url' => $this->link('correspondence.show', $c), 'overdue' => $c->isOverdue()]));
        }

        if ($user->can('documents.view')) {
            Document::query()->visibleTo($user)->active()->whereNotNull('review_date')
                ->whereBetween('review_date', [$today, $horizon])->limit(5)->get()
                ->each(fn (Document $d) => $items->push(['date' => $d->review_date, 'type' => __('Document review'), 'title' => $d->title, 'url' => $this->link('documents.show', $d)]));
        }

        return $items->sortBy(fn ($i) => $i['date']->timestamp)->values()->take(12);
    }
}
