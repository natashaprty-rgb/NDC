<?php

namespace App\Reports;

/**
 * One table of a report. Columns: [['label' => string, 'type' => text|int|money|percent|date]].
 * Cells hold raw values (money as exact decimal strings); renderers format them.
 */
final class ReportTable
{
    /**
     * @param  array<int, array{label:string,type?:string}>  $columns
     * @param  array<int, array<int, scalar|null>>  $rows
     * @param  array<int, string|null>  $links  row index => URL for the first cell
     */
    public function __construct(
        public string $title,
        public array $columns,
        public array $rows = [],
        public array $links = [],
        public ?array $totals = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }
}
