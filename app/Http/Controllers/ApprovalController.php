<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\Task;
use App\Services\ApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Approvals inbox: items waiting for the current user's decision, the user's
 * own outstanding requests, and submitted tasks awaiting review.
 */
class ApprovalController extends Controller
{
    public function index(Request $request, ApprovalService $service): View
    {
        $user = $request->user();

        $pending = Approval::query()->with(['approvable', 'requester'])
            ->where('status', 'pending')->latest()->get();

        $awaitingMe = $pending->filter(fn (Approval $a) => $a->approvable && $service->canDecide($a, $user))->values();
        $myRequests = $pending->where('requested_by', $user->id)->values();

        $tasksToReview = $user->can('tasks.review')
            ? Task::query()->with(['assignee', 'project'])->where('status', 'submitted')
                ->where(fn ($q) => $q->whereNull('assigned_to')->orWhere('assigned_to', '!=', $user->id))
                ->visibleTo($user)->orderBy('submitted_at')->get()
            : collect();

        $recent = Approval::query()->with(['approvable', 'requester'])
            ->where('decided_by', $user->id)->whereIn('status', ['approved', 'rejected'])
            ->latest('decided_at')->limit(10)->get();

        return view('approvals.index', compact('awaitingMe', 'myRequests', 'tasksToReview', 'recent'));
    }

    public function decide(Request $request, Approval $approval, ApprovalService $service): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'decision_comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $service->decide($approval, $request->user(), $data['decision'] === 'approve', $data['decision_comment'] ?? null);

        return back()->with('success', $data['decision'] === 'approve' ? __('Approved.') : __('Rejected.'));
    }
}
