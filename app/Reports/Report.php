<?php

namespace App\Reports;

use App\Models\Department;
use App\Models\Project;
use App\Models\User;
use App\Support\Money;

/**
 * Base class of every report. A report declares the permissions it needs
 * (in addition to reports.view), its filters, and builds its tables from
 * live database records using the same visibility rules as its module.
 */
abstract class Report
{
    abstract public function key(): string;

    /** Group used on the report index: operations, records, resources, finance, administration. */
    abstract public function group(): string;

    /** Permissions required in addition to reports.view. */
    public function permissions(): array
    {
        return [];
    }

    /** Sensitive reports contain restricted personal or security data. */
    public function sensitive(): bool
    {
        return false;
    }

    /**
     * Filter definitions: name => ['type' => date|select, 'label' => string, 'options' => array].
     */
    public function filters(User $user): array
    {
        return [];
    }

    abstract public function build(User $user, array $filters): ReportResult;

    public function title(): string
    {
        return __('reports.r.'.$this->key().'.title');
    }

    public function description(): string
    {
        return __('reports.r.'.$this->key().'.description');
    }

    public function canRun(User $user): bool
    {
        if (! $user->can('reports.view')) {
            return false;
        }
        foreach ($this->permissions() as $permission) {
            if (! $user->can($permission)) {
                return false;
            }
        }

        return true;
    }

    /** Validation rules derived from the filter definitions. */
    public function rules(User $user): array
    {
        $rules = [];
        foreach ($this->filters($user) as $name => $filter) {
            $rules[$name] = match ($filter['type']) {
                'date' => ['nullable', 'date'],
                'select' => ['nullable', 'in:'.implode(',', array_map('strval', array_keys($filter['options'] ?? [])))],
                default => ['nullable', 'string', 'max:100'],
            };
        }
        if (isset($rules['date_from'], $rules['date_to'])) {
            $rules['date_to'][] = 'after_or_equal:date_from';
        }

        return $rules;
    }

    // ---- shared helpers -------------------------------------------------

    protected function dateRange(): array
    {
        return [
            'date_from' => ['type' => 'date', 'label' => __('reports.f.date_from')],
            'date_to' => ['type' => 'date', 'label' => __('reports.f.date_to')],
        ];
    }

    protected function departmentFilter(): array
    {
        return ['type' => 'select', 'label' => __('common.department'), 'options' => Department::query()->orderBy('name')->get()->mapWithKeys(fn ($d) => [$d->id => $d->displayName()])->all()];
    }

    protected function projectFilter(): array
    {
        return ['type' => 'select', 'label' => __('common.project'), 'options' => Project::query()->notArchived()->orderBy('name')->pluck('name', 'id')->all()];
    }

    protected function statusFilter(array $values): array
    {
        return ['type' => 'select', 'label' => __('common.status'), 'options' => enum_options($values)];
    }

    protected function applyDateRange($query, string $column, array $filters)
    {
        if (! empty($filters['date_from'])) {
            $query->whereDate($column, '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate($column, '<=', $filters['date_to']);
        }

        return $query;
    }

    protected function yesNo(bool $value): string
    {
        return $value ? __('common.yes') : __('common.no');
    }

    /** Exact sum of decimal strings. */
    protected function sum(iterable $amounts): string
    {
        return Money::sum($amounts);
    }

    protected function col(string $labelKey, string $type = 'text'): array
    {
        return ['label' => __($labelKey), 'type' => $type];
    }
}
