<x-layout :title="__('reports.title')" :subtitle="__('reports.subtitle')" :breadcrumbs="[__('reports.title')]">
    @if(empty($groups))
        <x-card><x-empty :message="__('reports.none_available')" icon="chart" /></x-card>
    @endif

    @foreach($groups as $group => $reports)
        <x-card :title="__('reports.groups.'.$group)" :flush="true">
            <div class="table-wrap"><table class="table">
                <tbody>
                @foreach($reports as $key => $report)
                    <tr>
                        <td style="width:32%"><a href="{{ route('reports.show', $key) }}"><strong>{{ $report->title() }}</strong></a></td>
                        <td class="small muted">{{ $report->description() }}</td>
                        <td class="text-right nowrap">
                            @if($report->sensitive())<x-badge value="restricted" :label="__('reports.sensitive')" />@endif
                            <a href="{{ route('reports.show', $key) }}" class="btn btn--sm">{{ __('reports.open') }}</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </x-card>
    @endforeach
</x-layout>
