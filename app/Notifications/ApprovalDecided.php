<?php

namespace App\Notifications;

use App\Models\Approval;

class ApprovalDecided extends AppNotification
{
    public function __construct(public Approval $approval) {}

    protected function type(): string
    {
        return 'approval_'.$this->approval->status;
    }

    protected function title(): string
    {
        return $this->approval->status === 'approved' ? __('Request approved') : __('Request rejected');
    }

    protected function body(): string
    {
        return __(':item was :decision by :name.', [
            'item' => $this->approval->approvable?->approvalTitle() ?? '',
            'decision' => $this->approval->status === 'approved' ? __('approved') : __('rejected'),
            'name' => $this->approval->decider?->name ?? '',
        ]);
    }

    protected function url(): string
    {
        return $this->approval->approvable?->approvalUrl() ?? route('approvals.index');
    }
}
