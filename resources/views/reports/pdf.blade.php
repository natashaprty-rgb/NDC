<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $report->title() }}</title>
    <style>
        @page { margin: 18mm 12mm 16mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #161722; }
        .head { border-bottom: 2px solid #54a0d8; padding-bottom: 6px; margin-bottom: 10px; }
        .head img { height: 34px; }
        h1 { font-size: 13pt; margin: 8px 0 2px; }
        .meta { color: #6a7184; font-size: 7.5pt; }
        .summary { margin: 8px 0; }
        .summary span { display: inline-block; margin-right: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #eef6fc; text-align: left; font-size: 7.5pt; text-transform: uppercase; padding: 4px; border-bottom: 1px solid #c9d6e3; }
        td { padding: 4px; border-bottom: 1px solid #e2e6ee; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .foot { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7pt; color: #6a7184; border-top: 1px solid #e2e6ee; padding-top: 3px; }
        .accent { color: #a86b00; }
    </style>
</head>
<body>
    <div class="head">
        <img src="{{ public_path('images/logo-horizontal.png') }}" alt="">
        <h1>{{ $report->title() }}</h1>
        <div class="meta">{{ $filters }}<br>{{ __('Generated :date by :name', ['date' => fmt_date(now(), true), 'name' => $generatedBy]) }}</div>
    </div>
    @if ($summary)
        <div class="summary">
            @foreach ($summary as $label => $value)<span><strong>{{ is_numeric($value) ? number_format($value) : $value }}</strong> {{ $label }}</span>@endforeach
        </div>
    @endif
    @if ($rows->isEmpty())
        <p>{{ __('No records match these filters.') }}</p>
    @else
        <table>
            <thead><tr>@foreach ($report->columns() as $i => $column)<th class="{{ in_array($i, $report->numericColumns(), true) ? 'num' : '' }}">{{ __($column) }}</th>@endforeach</tr></thead>
            <tbody>
            @foreach ($rows as $row)
                <tr>@foreach ($row as $i => $cell)<td class="{{ in_array($i, $report->numericColumns(), true) ? 'num' : '' }}">{{ $cell ?? '—' }}</td>@endforeach</tr>
            @endforeach
            </tbody>
        </table>
    @endif
    <div class="foot">{{ setting('organisation_name') }} · {{ __('NDC Management System') }} · {{ __('Official use') }}</div>
</body>
</html>
