<?php

namespace App\Models\Concerns;

use App\Models\Approval;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Models that go through an approval workflow. The model decides what an
 * approval or rejection means by implementing approvalDecided(); the
 * ApprovalService handles permissions and separation of duties.
 */
trait HasApprovals
{
    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable')->latest('id');
    }

    public function pendingApproval(): MorphOne
    {
        return $this->morphOne(Approval::class, 'approvable')->where('status', 'pending')->latestOfMany();
    }

    public function hasPendingApproval(): bool
    {
        return $this->approvals()->where('status', 'pending')->exists();
    }

    /**
     * Called by ApprovalService after a decision has been saved.
     */
    abstract public function approvalDecided(Approval $approval): void;

    /**
     * Short description shown in the approvals inbox.
     */
    abstract public function approvalTitle(): string;

    /**
     * URL of the record the approval relates to.
     */
    abstract public function approvalUrl(): string;
}
