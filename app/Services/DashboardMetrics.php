<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Budget;
use App\Models\Correspondence;
use App\Models\Department;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\Staff;
use App\Models\Task;
use App\Models\User;
use App\Policies\PurchaseRequestPolicy;
use App\Support\Money;

/**
 * Builds dashboard figures from live database records only. Each block is
 * included only when the user holds the matching permission, and every
 * figure respects the same visibility rules as the module lists.
 */
class DashboardMetrics
{
    public function __construct(private readonly ApprovalInbox $inbox) {}

    public function for(User $user): array
    {
        $data = ['pending_approvals' => $this->inbox->count($user)];

        if ($user->can('projects.view')) {
            $base = Project::query()->notArchived();
            $byStatus = (clone $base)->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status')->map(fn ($v) => (int) $v);
            $data['projects'] = [
                'total' => $byStatus->sum(),
                'active' => $byStatus->get('in_progress', 0),
                'by_status' => collect(Project::STATUSES)->mapWithKeys(fn ($s) => [$s => $byStatus->get($s, 0)])->all(),
                'overdue' => (clone $base)->whereIn('status', Project::ACTIVE_STATUSES)->whereNotNull('expected_end_date')->whereDate('expected_end_date', '<', today())->count(),
                'recent' => (clone $base)->whereIn('status', ['planned', 'in_progress'])->with('manager')->latest('updated_at')->limit(5)->get(),
            ];
        }

        if ($user->can('staff.view')) {
            $data['staff'] = [
                'total' => Staff::query()->where('employment_status', '!=', 'separated')->count(),
                'departments' => Department::query()->active()->count(),
            ];
        }

        $mine = Task::query()->where('assigned_to', $user->id);
        $data['my_tasks'] = [
            'open' => (clone $mine)->open()->count(),
            'overdue' => (clone $mine)->overdue()->count(),
            'due_soon' => (clone $mine)->open()->whereNotNull('due_date')->whereBetween('due_date', [today(), today()->addDays(7)])->orderBy('due_date')->with('project')->limit(6)->get(),
        ];
        if ($user->can('tasks.view_all')) {
            $data['tasks'] = [
                'pending' => Task::query()->whereIn('status', ['todo', 'in_progress'])->count(),
                'awaiting_approval' => Task::query()->where('status', 'awaiting_approval')->count(),
                'completed' => Task::query()->where('status', 'completed')->count(),
                'overdue' => Task::query()->overdue()->count(),
            ];
        }

        if ($user->can('documents.view')) {
            $docs = Document::query()->visibleTo($user)->where('status', '!=', 'archived');
            $data['documents'] = [
                'total' => (clone $docs)->count(),
                'pending_review' => (clone $docs)->where('status', 'pending_review')->count(),
                'expiring' => (clone $docs)->whereNotNull('expiry_date')->whereBetween('expiry_date', [today(), today()->addDays(30)])->count(),
            ];
        }

        if ($user->can('correspondence.view')) {
            $corr = Correspondence::query()->visibleTo($user);
            $data['correspondence'] = [
                'total' => (clone $corr)->count(),
                'open_incoming' => (clone $corr)->where('direction', 'incoming')->whereIn('status', Correspondence::OPEN_STATUSES)->count(),
                'overdue' => (clone $corr)->whereIn('status', Correspondence::OPEN_STATUSES)->whereNotNull('response_deadline')->whereDate('response_deadline', '<', today())->count(),
            ];
        }

        if ($user->can('assets.view')) {
            $byStatus = Asset::query()->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status')->map(fn ($v) => (int) $v);
            $data['assets'] = ['total' => $byStatus->sum(), 'by_status' => $byStatus->all()];
        }

        if ($user->can('procurement.view')) {
            $pr = PurchaseRequest::query();
            if (! PurchaseRequestPolicy::hasOversight($user)) {
                $pr->where('requested_by', $user->id);
            }
            $data['procurement'] = [
                'pending' => (clone $pr)->where('status', 'submitted')->count(),
                'approved_not_ordered' => (clone $pr)->where('status', 'approved')->count(),
                'in_delivery' => (clone $pr)->whereIn('status', ['ordered', 'partially_received'])->count(),
            ];
        }

        if ($user->can('budgets.view')) {
            $year = (int) now()->format('Y');
            $budgetIds = Budget::query()->where('fiscal_year', $year)->whereIn('status', ['approved', 'closed'])->pluck('id');
            $allocated = Money::toCents((string) (\App\Models\BudgetAllocation::query()->whereIn('budget_id', $budgetIds)->sum('amount') ?: '0'));
            $sums = Expense::query()->whereIn('budget_id', $budgetIds)->selectRaw('status, SUM(amount) as t')->groupBy('status')->pluck('t', 'status')->map(fn ($v) => Money::toCents((string) $v));
            $approved = $sums->only(Expense::COMMITTED_STATUSES)->sum();
            $data['budget'] = [
                'year' => $year,
                'budgets' => $budgetIds->count(),
                'allocated' => Money::fromCents($allocated),
                'recorded' => Money::fromCents($sums->except(['rejected'])->sum()),
                'approved' => Money::fromCents($approved),
                'remaining' => Money::fromCents($allocated - $approved),
                'percent' => Money::percent($approved, $allocated),
            ];
        }

        $meetings = Meeting::query()->upcoming()->where('starts_at', '<=', now()->addDays(14))->limit(5);
        if (! $user->can('meetings.view')) {
            $meetings->where(fn ($q) => $q->where('organizer_id', $user->id)->orWhereHas('participants', fn ($p) => $p->where('user_id', $user->id)));
        }
        $data['meetings'] = $meetings->get();

        $activity = AuditLog::query()->with('user')->latest('id')->limit(10);
        if (! $user->can('audit.view')) {
            $activity->where('user_id', $user->id);
        }
        $data['activity'] = $activity->get();

        return $data;
    }
}
