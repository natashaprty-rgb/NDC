<?php

namespace App\Reports;

use App\Models\Correspondence;
use App\Models\Department;
use App\Models\User;
use App\Support\Status;
use Illuminate\Http\Request;

class CorrespondenceReport extends Report
{
    public function key(): string
    {
        return 'correspondence';
    }

    public function title(): string
    {
        return __('Correspondence register');
    }

    public function description(): string
    {
        return __('Incoming and outgoing letters with status and response deadlines.');
    }

    public function permission(): string
    {
        return 'correspondence.view';
    }

    public function filters(): array
    {
        return [
            'direction' => ['label' => 'Direction', 'type' => 'select', 'options' => Status::options('correspondence_direction')],
            'status' => ['label' => 'Status', 'type' => 'select', 'options' => Status::options('correspondence')],
            'department' => ['label' => 'Department', 'type' => 'select', 'options' => Department::options()],
            'overdue' => ['label' => 'Response', 'type' => 'select', 'options' => ['1' => __('Overdue only')]],
            ...$this->dateRange('Date received/sent'),
        ];
    }

    public function columns(): array
    {
        return ['Reference', 'Direction', 'Date', 'Sender', 'Recipient', 'Subject', 'Assigned to', 'Status', 'Response due', 'Overdue'];
    }

    public function rows(Request $request, User $user): iterable
    {
        return Correspondence::query()->visibleTo($user)->with('assignee')
            ->when($request->query('direction'), fn ($q, $d) => $q->where('direction', $d))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('department'), fn ($q, $d) => $q->where('department_id', $d))
            ->when($request->query('overdue'), fn ($q) => $q->overdue())
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('received_or_sent_date', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('received_or_sent_date', '<=', $d))
            ->orderBy('received_or_sent_date')->orderBy('reference_number')->get()
            ->map(fn (Correspondence $c) => [
                $c->reference_number, Status::label('correspondence_direction', $c->direction), fmt_date($c->received_or_sent_date),
                $c->sender, $c->recipient, $c->subject, $c->assignee?->name, Status::label('correspondence', $c->status),
                fmt_date($c->response_due_date), $c->isOverdue() ? __('Yes') : __('No'),
            ]);
    }
}
