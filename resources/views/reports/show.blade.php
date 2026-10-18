<x-layout :title="$report->title()" :subtitle="$report->description()"
          :breadcrumbs="[__('reports.title') => route('reports.index'), $report->title()]">
    <x-slot:actions>
        @can('reports.export')
            <a href="{{ route('reports.export', [$report->key()] + request()->query() + ['format' => 'csv']) }}" class="btn"><x-icon name="download" width="16" height="16" /> {{ __('common.export_csv') }}</a>
            <a href="{{ route('reports.export', [$report->key()] + array_merge(request()->query(), ['format' => 'pdf'])) }}" class="btn"><x-icon name="printer" width="16" height="16" /> {{ __('common.export_pdf') }}</a>
        @endcan
    </x-slot:actions>

    @if($report->sensitive())
        <div class="alert alert--warning" role="note">{{ __('reports.sensitive_notice') }}</div>
    @endif

    @if(count($filterDefs))
        <section class="card">
            <x-filter-bar :action="route('reports.show', $report->key())">
                @foreach($filterDefs as $name => $filter)
                    @if($filter['type'] === 'date')
                        <x-field.input :name="$name" type="date" :label="$filter['label']" :value="request($name)" />
                    @else
                        <x-field.select :name="$name" :label="$filter['label']" :options="$filter['options']" :value="request($name)" :placeholder="__('common.all')" />
                    @endif
                @endforeach
            </x-filter-bar>
        </section>
    @endif

    @if(count($result->summary))
        <div class="grid grid-4 mb-1">
            @foreach($result->summary as $item)
                <x-stat :label="$item['label']" :value="\App\Reports\ReportExporter::formatCell($item['value'], $item['type'] ?? 'int')" />
            @endforeach
        </div>
    @endif

    @foreach($result->tables as $table)
        <x-card :title="$table->title" :flush="true" class="mt-2">
            @if($table->isEmpty())
                <x-empty :message="__('reports.no_data')" icon="chart" />
            @else
                <div class="table-wrap"><table class="table">
                    <thead><tr>
                        @foreach($table->columns as $column)
                            <th class="{{ in_array($column['type'] ?? 'text', ['money', 'int', 'percent'], true) ? 'num' : '' }}">{{ $column['label'] }}</th>
                        @endforeach
                    </tr></thead>
                    <tbody>
                    @foreach($table->rows as $i => $row)
                        <tr>
                            @foreach($row as $c => $cell)
                                @php $type = $table->columns[$c]['type'] ?? 'text'; @endphp
                                <td class="{{ in_array($type, ['money', 'int', 'percent'], true) ? 'num' : '' }}">
                                    @if($c === 0 && !empty($table->links[$i]))
                                        <a href="{{ $table->links[$i] }}">{{ \App\Reports\ReportExporter::formatCell($cell, $type) }}</a>
                                    @else
                                        {{ \App\Reports\ReportExporter::formatCell($cell, $type) }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                    @if($table->totals)
                        <tfoot><tr>
                            @foreach($table->totals as $c => $cell)
                                @php $type = $table->columns[$c]['type'] ?? 'text'; @endphp
                                <td class="{{ in_array($type, ['money', 'int', 'percent'], true) ? 'num' : '' }}">{{ $cell === '' ? '' : \App\Reports\ReportExporter::formatCell($cell, $type) }}</td>
                            @endforeach
                        </tr></tfoot>
                    @endif
                </table></div>
            @endif
        </x-card>
    @endforeach
    <p class="small muted mt-1">{{ __('reports.generated_note', ['date' => fmt_datetime(now())]) }}</p>
</x-layout>
