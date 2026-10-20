<?php

namespace App\Reports;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * A tabular report computed from database records. Implementations declare
 * their filters and columns; the ReportController renders, prints and exports
 * them identically, so the screen, PDF and CSV always show the same data.
 */
abstract class Report
{
    abstract public function key(): string;

    abstract public function title(): string;

    abstract public function description(): string;

    /**
     * Permission needed in addition to reports.view.
     */
    abstract public function permission(): string;

    /**
     * Filter definitions: name => ['label' => ..., 'type' => 'select'|'date'|'text', 'options' => [...]].
     *
     * @return array<string, array<string, mixed>>
     */
    public function filters(): array
    {
        return [];
    }

    /**
     * Column headings (English; translated when rendered).
     *
     * @return list<string>
     */
    abstract public function columns(): array;

    /**
     * Report rows as lists of display values, in column order.
     *
     * @return iterable<list<string|int|float|null>>
     */
    abstract public function rows(Request $request, User $user): iterable;

    /**
     * Optional headline figures shown above the table: label => value.
     *
     * @return array<string, string|int>
     */
    public function summary(Request $request, User $user): array
    {
        return [];
    }

    /**
     * Indexes of numeric columns (right-aligned).
     *
     * @return list<int>
     */
    public function numericColumns(): array
    {
        return [];
    }

    public function landscape(): bool
    {
        return count($this->columns()) > 6;
    }

    /**
     * Human description of the active filters, for printed output.
     */
    public function describeFilters(Request $request): string
    {
        $parts = [];
        foreach ($this->filters() as $name => $filter) {
            $value = $request->query($name);
            if ($value === null || $value === '') {
                continue;
            }
            $display = $filter['type'] === 'select' ? ($filter['options'][$value] ?? $value) : $value;
            $parts[] = __($filter['label']).': '.$display;
        }

        return $parts ? implode(' · ', $parts) : __('No filters applied');
    }

    /**
     * Common date-range filters.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function dateRange(string $label = 'Date'): array
    {
        return [
            'from' => ['label' => $label.' from', 'type' => 'date'],
            'to' => ['label' => $label.' to', 'type' => 'date'],
        ];
    }
}
