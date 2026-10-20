<x-layouts.app :title="$report->title()">
    <x-page-header :title="$report->title()" :subtitle="$report->description()" :breadcrumbs="[__('Reports') => route('reports.index')]">
        <x-slot:actions>
            <button type="button" class="btn" data-print><x-icon name="printer" /> {{ __('Print') }}</button>
            @can('reports.export')
                <a href="{{ route('reports.pdf', ['key' => $report->key()] + request()->query()) }}" class="btn"><x-icon name="download" /> PDF</a>
                <a href="{{ route('reports.csv', ['key' => $report->key()] + request()->query()) }}" class="btn"><x-icon name="download" /> {{ __('Excel (CSV)') }}</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if ($summary)
        <div class="grid grid-4" style="margin-bottom:1rem">
            @foreach ($summary as $label => $value)
                <div class="card stat"><span class="stat__label">{{ $label }}</span><span class="stat__value">{{ is_numeric($value) ? number_format($value) : $value }}</span></div>
            @endforeach
        </div>
    @endif

    <div class="card">
        @if ($report->filters())
            <x-filters>
                @foreach ($report->filters() as $name => $filter)
                    @if ($filter['type'] === 'select')
                        <x-filter-select :name="$name" :label="__($filter['label'])" :options="$filter['options']" />
                    @else
                        <div class="field">
                            <label for="r_{{ $name }}">{{ __($filter['label']) }}</label>
                            <input type="{{ $filter['type'] === 'date' ? 'date' : 'text' }}" id="r_{{ $name }}" name="{{ $name }}" value="{{ request($name) }}" class="input">
                        </div>
                    @endif
                @endforeach
            </x-filters>
        @endif
        <div class="card__body small muted" style="padding-bottom:0">{{ $report->describeFilters(request()) }} · {{ __('Generated :date', ['date' => fmt_date(now(), true)]) }} · {{ trans_choice(':count row|:count rows', $rows->count()) }}</div>
        @if ($rows->isEmpty())
            <x-empty icon="chart" :message="__('No records match these filters.')" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr>
                    @foreach ($report->columns() as $i => $column)
                        <th @class(['num' => in_array($i, $report->numericColumns(), true)])>{{ __($column) }}</th>
                    @endforeach
                </tr></thead>
                <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($row as $i => $cell)
                            <td @class(['num' => in_array($i, $report->numericColumns(), true), 'nowrap' => $i !== 1 && mb_strlen((string) $cell) <= 24])>{{ $cell ?? '—' }}</td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </div>
</x-layouts.app>
