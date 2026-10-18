<?php

namespace App\Reports;

use App\Models\User;
use App\Services\ApprovalInbox;

/** Items currently awaiting the signed-in user's decision. */
class PendingApprovalsReport extends Report
{
    public function __construct(private readonly ApprovalInbox $inbox) {}

    public function key(): string
    {
        return 'pending-approvals';
    }

    public function group(): string
    {
        return 'operations';
    }

    public function build(User $user, array $filters): ReportResult
    {
        $groups = $this->inbox->for($user);
        $rows = [];
        $links = [];
        $add = function (string $type, $item, string $title, ?string $by, $date, ?string $url) use (&$rows, &$links) {
            $links[count($rows)] = $url;
            $rows[] = [$item->reference, __('approvals.'.$type), $title, $by, fmt_date($date, '')];
        };
        foreach ($groups['tasks'] as $t) {
            $add('tasks', $t, $t->title, $t->assignee?->name, $t->submitted_at, route_if('tasks.show', $t));
        }
        foreach ($groups['documents'] as $d) {
            $add('documents', $d, $d->title, $d->owner?->name, $d->updated_at, route_if('documents.show', $d));
        }
        foreach ($groups['purchase_requests'] as $p) {
            $add('purchase_requests', $p, $p->title, $p->requester?->name, $p->submitted_at, route_if('purchase-requests.show', $p));
        }
        foreach ($groups['budgets'] as $b) {
            $add('budgets', $b, $b->title, $b->creator?->name, $b->updated_at, route_if('budgets.show', $b));
        }
        foreach ($groups['expenses'] as $e) {
            $add('expenses', $e, $e->description, $e->recorder?->name, $e->expense_date, route_if('expenses.show', $e));
        }

        $result = (new ReportResult)->addSummary(__('reports.s.pending_approvals'), count($rows));
        foreach (['tasks', 'documents', 'purchase_requests', 'budgets', 'expenses'] as $type) {
            $result->addSummary(__('approvals.'.$type), $groups[$type]->count());
        }

        return $result->addTable(new ReportTable(__('reports.r.pending-approvals.title'), [
            $this->col('common.reference'), $this->col('common.type'), $this->col('common.title'), $this->col('reports.c.submitted_by'), $this->col('common.date', 'date'),
        ], $rows, $links));
    }
}
