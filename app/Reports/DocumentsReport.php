<?php

namespace App\Reports;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\User;

/** Uses Document::visibleTo(), the same confidentiality rule as the document register. */
class DocumentsReport extends Report
{
    public function key(): string
    {
        return 'documents';
    }

    public function group(): string
    {
        return 'records';
    }

    public function permissions(): array
    {
        return ['documents.view'];
    }

    public function filters(User $user): array
    {
        return [
            'category_id' => ['type' => 'select', 'label' => __('common.category'), 'options' => DocumentCategory::query()->orderBy('name')->get()->mapWithKeys(fn ($c) => [$c->id => $c->displayName()])->all()],
            'department_id' => $this->departmentFilter(),
            'status' => $this->statusFilter(Document::STATUSES),
        ] + $this->dateRange();
    }

    public function build(User $user, array $filters): ReportResult
    {
        $query = Document::query()->visibleTo($user)->with(['category', 'department', 'owner'])
            ->when(! empty($filters['category_id']), fn ($q) => $q->where('category_id', $filters['category_id']))
            ->when(! empty($filters['department_id']), fn ($q) => $q->where('department_id', $filters['department_id']))
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']));
        $this->applyDateRange($query, 'registration_date', $filters);
        $documents = $query->orderBy('reference')->get();

        $count = fn ($grouped) => $grouped->map(fn ($items, $label) => [$label, $items->count()])->values()->all();
        $byCategory = $count($documents->groupBy(fn ($d) => $d->category?->displayName() ?? __('reports.uncategorised'))->sortKeys());
        $byStatus = $count($documents->groupBy(fn ($d) => status_label($d->status)));
        $byConfidentiality = $count($documents->groupBy(fn ($d) => status_label($d->confidentiality)));

        $horizon = today()->addDays(30);
        $attention = $documents->filter(fn ($d) => $d->status !== 'archived'
            && (($d->expiry_date && $d->expiry_date->lte($horizon)) || ($d->review_date && $d->review_date->lte($horizon))))->values();
        $rows = [];
        $links = [];
        foreach ($attention as $i => $d) {
            $rows[] = [$d->reference, $d->title, $d->category?->displayName(), $d->owner?->name, status_label($d->confidentiality),
                fmt_date($d->review_date, ''), fmt_date($d->expiry_date, ''), $this->yesNo($d->isExpired())];
            $links[$i] = route_if('documents.show', $d);
        }

        $countCols = fn (string $key) => [$this->col($key), $this->col('reports.c.count', 'int')];

        return (new ReportResult)
            ->addSummary(__('reports.s.documents'), $documents->count())
            ->addSummary(__('reports.s.pending_review'), $documents->where('status', 'pending_review')->count())
            ->addSummary(__('reports.s.needs_attention'), $attention->count())
            ->addTable(new ReportTable(__('reports.t.by_category'), $countCols('common.category'), $byCategory))
            ->addTable(new ReportTable(__('reports.t.by_status'), $countCols('common.status'), $byStatus))
            ->addTable(new ReportTable(__('reports.t.by_confidentiality'), $countCols('reports.c.confidentiality'), $byConfidentiality))
            ->addTable(new ReportTable(__('reports.t.review_expiry'), [
                $this->col('common.reference'), $this->col('common.title'), $this->col('common.category'), $this->col('common.owner'),
                $this->col('reports.c.confidentiality'), $this->col('reports.c.review_date', 'date'), $this->col('reports.c.expiry_date', 'date'),
                $this->col('reports.c.expired'),
            ], $rows, $links));
    }
}
