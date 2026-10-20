<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Reports\Report;
use App\Reports\ReportRegistry;
use App\Support\Audit;
use App\Support\CsvExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Report catalogue, on-screen reports and PDF/CSV exports. Each report
 * applies the same permission and record-level rules as its module.
 */
class ReportController extends Controller
{
    public function __construct(private readonly ReportRegistry $reports) {}

    public function index(Request $request): View
    {
        return view('reports.index', ['reports' => $this->reports->availableTo($request->user())]);
    }

    public function show(Request $request, string $key): View
    {
        $report = $this->resolve($request, $key);

        return view('reports.show', [
            'report' => $report,
            'rows' => collect($report->rows($request, $request->user()))->values(),
            'summary' => $report->summary($request, $request->user()),
        ]);
    }

    public function csv(Request $request, string $key): StreamedResponse
    {
        $report = $this->resolve($request, $key, export: true);

        return CsvExport::download('report-'.$report->key(), $report->columns(), $report->rows($request, $request->user()));
    }

    public function pdf(Request $request, string $key): Response
    {
        $report = $this->resolve($request, $key, export: true);
        Audit::log('exported', null, 'report-'.$report->key().'.pdf');

        $pdf = Pdf::loadView('reports.pdf', [
            'report' => $report,
            'rows' => collect($report->rows($request, $request->user()))->values(),
            'summary' => $report->summary($request, $request->user()),
            'filters' => $report->describeFilters($request),
            'generatedBy' => $request->user()->name,
        ])->setPaper('a4', $report->landscape() ? 'landscape' : 'portrait');

        return $pdf->download('report-'.$report->key().'-'.now()->format('Ymd-His').'.pdf');
    }

    private function resolve(Request $request, string $key, bool $export = false): Report
    {
        $report = $this->reports->find($key);
        abort_if($report === null, 404);
        abort_unless($request->user()->can($report->permission()), 403);
        abort_if($export && ! $request->user()->can('reports.export'), 403);

        $rules = [];
        foreach ($report->filters() as $name => $filter) {
            $rules[$name] = match ($filter['type']) {
                'date' => ['nullable', 'date'],
                'select' => ['nullable', 'in:'.implode(',', array_map('strval', array_keys($filter['options'])))],
                default => ['nullable', 'string', 'max:100'],
            };
        }
        $request->validate($rules);

        return $report;
    }
}
