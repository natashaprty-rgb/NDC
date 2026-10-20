<x-layouts.app :title="__('Purchase requests')">
    <x-page-header :title="__('Purchase requests')" :subtitle="__('Procurement from request and approval to ordering and delivery.')">
        <x-slot:actions>
            <a href="{{ route('purchase-orders.index') }}" class="btn"><x-icon name="file" /> {{ __('Purchase orders') }}</a>
            <a href="{{ route('procurement.register', request()->query()) }}" class="btn"><x-icon name="printer" /> {{ __('Procurement register') }}</a>
            @can('create', \App\Models\ProcurementRequest::class)
                <a href="{{ route('procurement.create') }}" class="btn btn-primary"><x-icon name="plus" /> {{ __('New purchase request') }}</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-4" style="margin-bottom:1rem">
        @foreach (['submitted' => __('Awaiting approval'), 'approved' => __('Approved, not ordered'), 'ordered' => __('Ordered'), 'partially_received' => __('Partially received')] as $state => $label)
            <a class="card stat" href="{{ route('procurement.index', ['status' => $state]) }}">
                <span class="stat__label">{{ $label }}</span>
                <span class="stat__value">{{ (int) ($summary[$state] ?? 0) }}</span>
            </a>
        @endforeach
    </div>

    <div class="card">
        <x-filters>
            <x-search-field :placeholder="__('Reference or title')" />
            <x-filter-select name="status" :label="__('Status')" :options="\App\Support\Status::options('procurement')" />
            <x-filter-select name="department" :label="__('Department')" :options="$departments" />
            <x-filter-select name="project" :label="__('Project')" :options="$projects" />
        </x-filters>
        @if ($requests->isEmpty())
            <x-empty icon="cart" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr>
                    <th><x-sort-link column="reference_number" :label="__('Reference')" /></th>
                    <th><x-sort-link column="title" :label="__('Title')" /></th>
                    <th>{{ __('Department') }}</th>
                    <th><x-sort-link column="needed_by" :label="__('Needed by')" /></th>
                    <th class="text-right"><x-sort-link column="estimated_total" :label="__('Estimated total')" /></th>
                    <th><x-sort-link column="status" :label="__('Status')" /></th>
                    <th><x-sort-link column="created_at" :label="__('Created')" /></th>
                </tr></thead>
                <tbody>
                @foreach ($requests as $pr)
                    <tr>
                        <td class="nowrap"><a href="{{ route('procurement.show', $pr) }}">{{ $pr->reference_number }}</a></td>
                        <td class="title-cell"><a href="{{ route('procurement.show', $pr) }}">{{ $pr->title }}</a>
                            <span class="sub">{{ $pr->requester?->name }} · {{ trans_choice(':count item|:count items', $pr->items_count) }}</span></td>
                        <td>{{ $pr->department?->displayName() ?? '—' }}</td>
                        <td class="nowrap">{{ fmt_date($pr->needed_by) }}</td>
                        <td class="num text-right nowrap">{{ money($pr->estimated_total, $pr->currency) }}</td>
                        <td><x-status domain="procurement" :value="$pr->status" /></td>
                        <td class="nowrap">{{ fmt_date($pr->created_at) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
        {{ $requests->links() }}
    </div>
</x-layouts.app>
