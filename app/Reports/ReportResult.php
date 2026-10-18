<?php

namespace App\Reports;

final class ReportResult
{
    /** @var array<int, array{label:string,value:scalar|null,type?:string}> */
    public array $summary = [];

    /** @var array<int, ReportTable> */
    public array $tables = [];

    public function addSummary(string $label, string|int|float|null $value, string $type = 'int'): self
    {
        $this->summary[] = ['label' => $label, 'value' => $value, 'type' => $type];

        return $this;
    }

    public function addTable(ReportTable $table): self
    {
        $this->tables[] = $table;

        return $this;
    }

    public function isEmpty(): bool
    {
        foreach ($this->tables as $table) {
            if (! $table->isEmpty()) {
                return false;
            }
        }

        return true;
    }
}
