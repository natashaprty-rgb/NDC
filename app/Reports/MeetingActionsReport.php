<?php

namespace App\Reports;

use App\Models\MeetingActionItem;
use App\Models\User;
use App\Support\Status;
use Illuminate\Http\Request;

class MeetingActionsReport extends Report
{
    public function key(): string
    {
        return 'meeting-actions';
    }

    public function title(): string
    {
        return __('Meeting decisions and outstanding actions');
    }

    public function description(): string
    {
        return __('Decisions and action points recorded in meeting minutes and their completion.');
    }

    public function permission(): string
    {
        return 'meetings.view';
    }

    public function filters(): array
    {
        return [
            'status' => ['label' => 'Status', 'type' => 'select', 'options' => Status::options('action_item')],
            'type' => ['label' => 'Type', 'type' => 'select', 'options' => Status::options('action_item_type')],
            'assignee' => ['label' => 'Assigned to', 'type' => 'select', 'options' => User::query()->orderBy('name')->pluck('name', 'id')->all()],
            'outstanding' => ['label' => 'Completion', 'type' => 'select', 'options' => ['1' => __('Outstanding only')]],
            ...$this->dateRange('Meeting date'),
        ];
    }

    public function columns(): array
    {
        return ['Meeting', 'Meeting date', 'Type', 'Description', 'Assigned to', 'Due date', 'Status', 'Overdue'];
    }

    public function rows(Request $request, User $user): iterable
    {
        return MeetingActionItem::query()->with(['meeting', 'assignee'])
            ->whereHas('meeting', fn ($q) => $q
                ->when($request->query('from'), fn ($q, $d) => $q->whereDate('start_at', '>=', $d))
                ->when($request->query('to'), fn ($q, $d) => $q->whereDate('start_at', '<=', $d)))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->query('assignee'), fn ($q, $a) => $q->where('assigned_to', $a))
            ->when($request->query('outstanding'), fn ($q) => $q->outstanding())
            ->orderByRaw('due_date is null')->orderBy('due_date')->get()
            ->map(fn (MeetingActionItem $a) => [
                $a->meeting?->title, fmt_date($a->meeting?->start_at), Status::label('action_item_type', $a->type), $a->description,
                $a->assignee?->name, fmt_date($a->due_date), Status::label('action_item', $a->status), $a->isOverdue() ? __('Yes') : __('No'),
            ]);
    }
}
