<?php

namespace App\Services;

use App\Models\Approval;
use App\Models\Concerns\HasApprovals;
use App\Models\User;
use App\Notifications\ApprovalDecided;
use App\Notifications\ApprovalRequested;
use App\Support\Audit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Generic approval workflow used by tasks, documents, purchase requests,
 * budgets, expenses and asset disposals.
 *
 * Rules enforced here:
 *  - only users holding the approval's permission may decide it;
 *  - the requester can never approve their own request (separation of duties);
 *  - a record has at most one pending approval at a time;
 *  - nothing is approved automatically.
 */
class ApprovalService
{
    /**
     * Open an approval request for the model.
     *
     * @param  Model&HasApprovals  $model
     */
    public function request(Model $model, string $permission, User $requester, ?string $note = null): Approval
    {
        if ($model->hasPendingApproval()) {
            throw ValidationException::withMessages([
                'approval' => __('This record already has a pending approval request.'),
            ]);
        }

        $approval = $model->approvals()->create([
            'status' => 'pending',
            'permission' => $permission,
            'requested_by' => $requester->id,
            'request_note' => $note,
        ]);

        Audit::log('approval_requested', $model, $model->approvalTitle());

        $approvers = $this->eligibleApprovers($approval)->reject(fn (User $u) => $u->is($requester));
        Notification::send($approvers, new ApprovalRequested($approval));

        return $approval;
    }

    /**
     * Record an approval or rejection.
     */
    public function decide(Approval $approval, User $user, bool $approve, ?string $comment = null): Approval
    {
        if (! $this->canDecide($approval, $user)) {
            throw new AuthorizationException(__('You are not allowed to decide this approval.'));
        }

        if (! $approve && blank($comment)) {
            throw ValidationException::withMessages([
                'decision_comment' => __('A reason is required when rejecting.'),
            ]);
        }

        DB::transaction(function () use ($approval, $user, $approve, $comment) {
            $approval->forceFill([
                'status' => $approve ? 'approved' : 'rejected',
                'decided_by' => $user->id,
                'decision_comment' => $comment,
                'decided_at' => now(),
            ])->save();

            $approval->approvable->approvalDecided($approval);
        });

        Audit::log($approve ? 'approved' : 'rejected', $approval->approvable, $approval->approvable->approvalTitle(), null, [
            'comment' => $comment,
        ]);

        if ($approval->requester && ! $approval->requester->is($user)) {
            $approval->requester->notify(new ApprovalDecided($approval));
        }

        return $approval;
    }

    public function cancel(Approval $approval, User $user): void
    {
        if ($approval->status !== 'pending') {
            return;
        }

        $approval->forceFill(['status' => 'cancelled', 'decided_by' => $user->id, 'decided_at' => now()])->save();
        Audit::log('approval_cancelled', $approval->approvable, $approval->approvable?->approvalTitle());
    }

    public function canDecide(Approval $approval, User $user): bool
    {
        return $approval->status === 'pending'
            && $approval->requested_by !== $user->id
            && $user->isActive()
            && $user->can($approval->permission);
    }

    /**
     * @return Collection<int, User>
     */
    public function eligibleApprovers(Approval $approval)
    {
        return User::query()->active()->get()
            ->filter(fn (User $user) => $user->can($approval->permission))
            ->values();
    }
}
