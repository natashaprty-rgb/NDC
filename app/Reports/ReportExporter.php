<?php

namespace App\Reports;

use App\Models\User;
use App\Support\Money;
use App\Support\Settings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExporter
{
    /** Characters that make spreadsheet software treat a cell as a formula. */
    private const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    public function csv(Report $report, ReportResult $result, array $filters): StreamedResponse
    {
        return response()->streamDownload(function () use ($report, $result, $filters) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel detects the encoding
            $this->put($out, [$report->title()]);
            foreach ($this->describeFilters($report, $filters) as $line) {
                $this->put($out, [$line]);
            }
            foreach ($result->summary as $item) {
                $this->put($out, [$item['label'], $item['value']]);
            }
            foreach ($result->tables as $table) {
                $this->put($out, []);
                $this->put($out, [$table->title]);
                $this->put($out, array_column($table->columns, 'label'));
                foreach ($table->rows as $row) {
                    $this->put($out, $row);
                }
                if ($table->totals) {
                    $this->put($out, $table->totals);
                }
            }
            fclose($out);
        }, $this->fileName($report, 'csv'), ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function pdf(Report $report, ReportResult $result, array $filters, User $user): Response
    {
        $pdf = Pdf::loadView('reports.pdf', [
            'report' => $report,
            'result' => $result,
            'filterLines' => $this->describeFilters($report, $filters),
            'orgName' => (string) Settings::get('org.name'),
            'generatedBy' => $user->name,
            'generatedAt' => fmt_datetime(now()),
        ])->setPaper('a4', 'landscape');

        $pdf->render();
        $canvas = $pdf->getDomPDF()->getCanvas();
        $canvas->page_text($canvas->get_width() - 120, $canvas->get_height() - 24, __('reports.page_x_of_y'), null, 8, [0.35, 0.35, 0.35]);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->fileName($report, 'pdf').'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Neutralise formula injection; numbers (including negative amounts) are left intact. */
    public static function sanitizeCell(mixed $value): string
    {
        $value = (string) ($value ?? '');
        if ($value !== '' && ! is_numeric($value) && in_array($value[0], self::FORMULA_PREFIXES, true)) {
            return "'".$value;
        }

        return $value;
    }

    /** Human-readable list of the filters applied. */
    public function describeFilters(Report $report, array $filters): array
    {
        $lines = [];
        foreach ($report->filters(auth()->user()) as $name => $definition) {
            $value = $filters[$name] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $display = $definition['type'] === 'select' ? ($definition['options'][$value] ?? $value) : ($definition['type'] === 'date' ? fmt_date($value) : $value);
            $lines[] = $definition['label'].': '.$display;
        }

        return $lines === [] ? [__('reports.no_filters')] : $lines;
    }

    public static function formatCell(mixed $value, string $type): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($type) {
            'money' => Money::format((string) $value),
            'percent' => $value.'%',
            default => (string) $value,
        };
    }

    private function put($handle, array $row): void
    {
        fputcsv($handle, array_map([self::class, 'sanitizeCell'], $row), ',', '"', '\\');
    }

    private function fileName(Report $report, string $ext): string
    {
        return $report->key().'-'.now()->format('Ymd-His').'.'.$ext;
    }
}
