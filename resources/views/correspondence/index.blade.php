<x-layout :title="__('correspondence.title')" :subtitle="__('correspondence.subtitle')" :breadcrumbs="[__('correspondence.title')]">
    <x-slot:actions>
        @can('create', \App\Models\Correspondence::class)
            <a href="{{ route('correspondence.create', ['direction' => 'incoming']) }}" class="btn btn--primary"><x-icon name="plus" width="16" height="16" /> {{ __('correspondence.new_incoming') }}</a>
            <a href="{{ route('correspondence.create', ['direction' => 'outgoing']) }}" class="btn"><x-icon name="plus" width="16" height="16" /> {{ __('correspondence.new_outgoing') }}</a>
        @endcan
    </x-slot:actions>

    @php $dir = request('direction'); @endphp
    <div class="tabs">
        <a href="{{ route('correspondence.index', request()->except(['direction', 'page'])) }}" class="{{ ! $dir ? 'is-active' : '' }}">{{ __('common.all') }}</a>
        <a href="{{ route('correspondence.index', ['direction' => 'incoming'] + request()->except(['direction', 'page'])) }}" class="{{ $dir === 'incoming' ? 'is-active' : '' }}">{{ __('correspondence.incoming_register') }}</a>
        <a href="{{ route('correspondence.index', ['direction' => 'outgoing'] + request()->except(['direction', 'page'])) }}" class="{{ $dir === 'outgoing' ? 'is-active' : '' }}">{{ __('correspondence.outgoing_register') }}</a>
    </div>

    <section class="card">
        <x-filter-bar :action="route('correspondence.index')">
            @if($dir)<input type="hidden" name="direction" value="{{ $dir }}">@endif
            <x-field.input name="q" :label="__('common.search')" :value="request('q')" class="field--search" :placeholder="__('correspondence.search_hint')" />
            <x-field.select name="status" :label="__('common.status')" :options="['open' => __('correspondence.open_items')] + enum_options(\App\Models\Correspondence::STATUSES)" :value="request('status')" :placeholder="__('common.all')" />
            <x-field.select name="department" :label="__('common.department')" :options="$departments" :value="request('department')" :placeholder="__('common.all')" />
            <x-field.select name="assigned" :label="__('correspondence.assigned_officer')" :options="$users" :value="request('assigned')" :placeholder="__('common.all')" />
            <x-field.input name="from" type="date" :label="__('common.from')" :value="request('from')" />
            <x-field.input name="to" type="date" :label="__('common.to')" :value="request('to')" />
            <x-field.select name="overdue" :label="__('correspondence.response_deadline')" :options="['1' => __('common.overdue')]" :value="request('overdue')" :placeholder="__('common.all')" />
        </x-filter-bar>

        @if($items->isEmpty())
            <x-empty :message="request()->hasAny(['q', 'status', 'department', 'assigned', 'from', 'to', 'overdue', 'direction']) ? __('common.no_records') : __('correspondence.empty')" icon="mail" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr>
                        <th>{{ __('common.reference') }}</th><th>{{ __('correspondence.direction') }}</th><th>{{ __('correspondence.subject') }}</th>
                        <th>{{ __('correspondence.sender_recipient') }}</th><th>{{ __('common.date') }}</th><th>{{ __('correspondence.assigned_officer') }}</th>
                        <th>{{ __('correspondence.response_deadline') }}</th><th>{{ __('common.status') }}</th>
                    </tr></thead>
                    <tbody>
                    @foreach($items as $item)
                        <tr @class(['row-overdue' => $item->isOverdue()])>
                            <td class="nowrap">{{ $item->reference }}</td>
                            <td><x-badge :value="$item->direction" /></td>
                            <td><a href="{{ route('correspondence.show', $item) }}">{{ $item->subject }}</a>
                                @if($item->confidentiality === 'confidential') <x-badge value="confidential" />@endif
                                @if($item->external_reference)<br><span class="small muted">{{ $item->external_reference }}</span>@endif</td>
                            <td class="small">{{ $item->direction === 'incoming' ? $item->sender : $item->recipient }}</td>
                            <td class="nowrap">{{ fmt_date($item->received_or_sent_date) }}</td>
                            <td>{{ $item->assignee?->name ?? __('common.unassigned') }}</td>
                            <td class="nowrap">{{ fmt_date($item->response_deadline) }} @if($item->isOverdue())<x-badge value="overdue" />@endif</td>
                            <td><x-badge :value="$item->status" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $items->links() }}
        @endif
    </section>
</x-layout>
