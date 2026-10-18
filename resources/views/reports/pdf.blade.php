<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $report->title() }}</title>
    <style>
        @page { margin: 22mm 12mm 16mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #1d2733; }
        .head { border-bottom: 2px solid #12558f; padding-bottom: 6px; margin-bottom: 8px; }
        .org { font-size: 10pt; font-weight: bold; color: #12558f; }
        h1 { font-size: 13pt; margin: 2px 0; }
        .meta { color: #5b6878; font-size: 7.5pt; }
        .summary td { padding: 3px 10px 3px 0; }
        h2 { font-size: 10pt; margin: 12px 0 4px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #eef1f5; text-align: left; font-size: 7.5pt; padding: 4px; border-bottom: 1px solid #c5cfda; }
        table.data td { padding: 3px 4px; border-bottom: 1px solid #e3e8ee; vertical-align: top; }
        table.data tfoot td { font-weight: bold; background: #f8fafc; }
        .num { text-align: right; white-space: nowrap; }
        .empty { color: #5b6878; font-style: italic; }
    </style>
</head>
<body>
    <div class="head">
        <div class="org">{{ $orgName }}</div>
        <h1>{{ $report->title() }}</h1>
        <div class="meta">{{ __('reports.generated_by', ['name' => $generatedBy, 'date' => $generatedAt]) }}</div>
        <div class="meta">{{ __('reports.filters_used') }}: {{ implode(' · ', $filterLines) }}</div>
        @if($report->sensitive())<div class="meta"><strong>{{ __('reports.sensitive_notice') }}</strong></div>@endif
    </div>

    @if(count($result->summary))
        <table class="summary"><tr>
            @foreach($result->summary as $item)
                <td><span class="meta">{{ $item['label'] }}</span><br><strong>{{ \App\Reports\ReportExporter::formatCell($item['value'], $item['type'] ?? 'int') }}</strong></td>
            @endforeach
        </tr></table>
    @endif

    @foreach($result->tables as $table)
        <h2>{{ $table->title }}</h2>
        @if($table->isEmpty())
            <p class="empty">{{ __('reports.no_data') }}</p>
        @else
            <table class="data">
                <thead><tr>@foreach($table->columns as $column)<th class="{{ in_array($column['type'] ?? 'text', ['money', 'int', 'percent'], true) ? 'num' : '' }}">{{ $column['label'] }}</th>@endforeach</tr></thead>
                <tbody>
                @foreach($table->rows as $row)
                    <tr>@foreach($row as $c => $cell)@php $type = $table->columns[$c]['type'] ?? 'text'; @endphp<td class="{{ in_array($type, ['money', 'int', 'percent'], true) ? 'num' : '' }}">{{ \App\Reports\ReportExporter::formatCell($cell, $type) }}</td>@endforeach</tr>
                @endforeach
                </tbody>
                @if($table->totals)
                    <tfoot><tr>@foreach($table->totals as $c => $cell)@php $type = $table->columns[$c]['type'] ?? 'text'; @endphp<td class="{{ in_array($type, ['money', 'int', 'percent'], true) ? 'num' : '' }}">{{ $cell === '' ? '' : \App\Reports\ReportExporter::formatCell($cell, $type) }}</td>@endforeach</tr></tfoot>
                @endif
            </table>
        @endif
    @endforeach
</body>
</html>
