<?php

namespace App\Notifications;

use App\Models\Approval;

class ApprovalRequested extends AppNotification
{
    public function __construct(public Approval $approval)
    {
    }

    protected function type(): string
    {
        return 'approval_requested';
    }

    protected function title(): string
    {
        return __('Approval requested');
    }

    protected function body(): string
    {
        return __(':name requested approval: :item', [
            'name' => $this->approval->requester?->name ?? __('A user'),
            'item' => $this->approval->approvable?->approvalTitle() ?? '',
        ]);
    }

    protected function url(): string
    {
        return $this->approval->approvable?->approvalUrl() ?? route('approvals.index');
    }
}
