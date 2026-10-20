<x-layouts.app :title="__('Outstanding action points')">
    <x-page-header :title="__('Meetings & Calendar')" :subtitle="__('Track the completion of decisions and action points from all meetings.')">
        <x-slot:actions>
            @can('create', \App\Models\Meeting::class)
                <a href="{{ route('meetings.create') }}" class="btn btn-primary"><x-icon name="plus" /> {{ __('Schedule meeting') }}</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @include('meetings.partials.tabs', ['current' => 'actions'])

    <div class="grid grid-2">
        <div @class(['stat', 'stat--alert' => $overdueCount > 0])>
            <div class="stat__label">{{ __('Overdue action points') }}</div>
            <div class="stat__value">{{ number_format($overdueCount) }}</div>
            <div class="stat__meta"><a href="{{ route('meetings.action-items.index', ['overdue' => 1]) }}">{{ __('Show overdue') }}</a></div>
        </div>
        <div class="stat">
            <div class="stat__label">{{ __('Assigned to me') }}</div>
            <div class="stat__value">{{ number_format($mineCount) }}</div>
            <div class="stat__meta"><a href="{{ route('meetings.action-items.index', ['assignee' => 'me']) }}">{{ __('Show my action points') }}</a></div>
        </div>
    </div>

    <div class="card">
        <x-filters>
            <x-search-field :placeholder="__('Description')" />
            <x-filter-select name="assignee" :label="__('Assigned to')" :options="['me' => __('Me')] + $users" />
            <div class="field">
                <label for="flt_status">{{ __('Status') }}</label>
                <select id="flt_status" name="status" class="select">
                    <option value="">{{ __('Outstanding (open or in progress)') }}</option>
                    @foreach (\App\Support\Status::options('action_item') as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <x-filter-select name="type" :label="__('Type')" :options="\App\Support\Status::options('action_item_type')" />
            <div class="field" style="flex:0 0 auto">
                <label class="check" for="flt_overdue"><input type="checkbox" id="flt_overdue" name="overdue" value="1" @checked(request()->boolean('overdue'))> {{ __('Overdue only') }}</label>
            </div>
        </x-filters>

        @if ($items->isEmpty())
            <x-empty icon="check" :message="__('No action points match these filters.')" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr>
                    <th>{{ __('Action point') }}</th>
                    <th>{{ __('Meeting') }}</th>
                    <th>{{ __('Assigned to') }}</th>
                    <th><x-sort-link column="due_date" :label="__('Due date')" /></th>
                    <th><x-sort-link column="status" :label="__('Status')" /></th>
                </tr></thead>
                <tbody>
                @foreach ($items as $item)
                    <tr>
                        <td class="title-cell">
                            <x-status domain="action_item_type" :value="$item->type" class="badge--plain" />
                            {{ $item->description }}
                            @if ($item->notes)<span class="sub">{{ $item->notes }}</span>@endif
                        </td>
                        <td class="title-cell">
                            <a href="{{ route('meetings.show', $item->meeting) }}#action-items">{{ $item->meeting->title }}</a>
                            <span class="sub">{{ fmt_date($item->meeting->start_at) }}</span>
                        </td>
                        <td>{{ $item->assignee?->name ?? '—' }}</td>
                        <td class="nowrap">{{ fmt_date($item->due_date) }} @if ($item->isOverdue())<span class="badge badge--danger">{{ __('Overdue') }}</span>@endif</td>
                        <td>@include('meetings.partials.item-status', ['item' => $item])</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
        {{ $items->links() }}
    </div>
</x-layouts.app>
