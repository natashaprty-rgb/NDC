<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Reports\Report;
use App\Reports\ReportExporter;
use App\Reports\ReportRegistry;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function __construct(private readonly ReportRegistry $registry) {}

    public function index(Request $request): View
    {
        return view('reports.index', ['groups' => $this->registry->availableFor($request->user())]);
    }

    public function show(Request $request, string $report): View
    {
        $definition = $this->resolve($request, $report);
        $filters = $this->filters($request, $definition);

        return view('reports.show', [
            'report' => $definition,
            'filterDefs' => $definition->filters($request->user()),
            'result' => $definition->build($request->user(), $filters),
        ]);
    }

    public function export(Request $request, string $report, ReportExporter $exporter): Response
    {
        $definition = $this->resolve($request, $report);
        abort_unless($request->user()->can('reports.export'), 403);

        $format = $request->query('format', 'csv');
        abort_unless(in_array($format, ['csv', 'pdf'], true), 404);

        $filters = $this->filters($request, $definition);
        $result = $definition->build($request->user(), $filters);

        AuditLogger::log('exported', null, 'Report exported: '.$definition->key().' ('.$format.')', [], ['report' => $definition->key(), 'format' => $format, 'filters' => $filters]);

        return $format === 'pdf'
            ? $exporter->pdf($definition, $result, $filters, $request->user())
            : $exporter->csv($definition, $result, $filters);
    }

    private function resolve(Request $request, string $key): Report
    {
        $report = $this->registry->find($key);
        abort_if($report === null, 404);
        abort_unless($report->canRun($request->user()), 403);

        return $report;
    }

    private function filters(Request $request, Report $report): array
    {
        $rules = $report->rules($request->user());

        return Validator::make($request->only(array_keys($rules)), $rules)->validate();
    }
}
