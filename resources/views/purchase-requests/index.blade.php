<x-layout :title="__('procurement.title')" :breadcrumbs="[__('procurement.title')]">
    <x-slot:actions>
        @can('viewAny', \App\Models\PurchaseOrder::class)
            <a href="{{ route('purchase-orders.index') }}" class="btn">{{ __('procurement.purchase_orders') }}</a>
        @endcan
        @can('create', \App\Models\PurchaseRequest::class)
            <a href="{{ route('purchase-requests.create') }}" class="btn btn--primary"><x-icon name="plus" width="16" height="16" /> {{ __('procurement.new_pr') }}</a>
        @endcan
    </x-slot:actions>

    <section class="card">
        <x-filter-bar :action="route('purchase-requests.index')">
            <x-field.input name="q" :label="__('common.search')" :value="request('q')" class="field--search" :placeholder="__('procurement.search_hint')" />
            <x-field.select name="status" :label="__('common.status')" :options="enum_options(\App\Models\PurchaseRequest::STATUSES)" :value="request('status')" :placeholder="__('common.all')" />
            <x-field.select name="department" :label="__('common.department')" :options="$departments" :value="request('department')" :placeholder="__('common.all')" />
            <x-field.select name="project" :label="__('common.project')" :options="$projects" :value="request('project')" :placeholder="__('common.all')" />
        </x-filter-bar>

        @if($requests->isEmpty())
            <x-empty :message="request()->hasAny(['q', 'status', 'department', 'project']) ? __('common.no_records') : __('procurement.empty')" icon="cart" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr>
                    <th>{{ __('common.reference') }}</th><th>{{ __('procurement.pr_title') }}</th><th>{{ __('procurement.requested_by') }}</th>
                    <th>{{ __('common.department') }}</th><th>{{ __('procurement.needed_by') }}</th><th class="num">{{ __('procurement.estimated_total') }}</th><th>{{ __('common.status') }}</th>
                </tr></thead>
                <tbody>@foreach($requests as $pr)
                    <tr>
                        <td class="nowrap"><a href="{{ route('purchase-requests.show', $pr) }}">{{ $pr->reference }}</a></td>
                        <td>{{ $pr->title }}@if($pr->project)<br><span class="small muted">{{ $pr->project->name }}</span>@endif</td>
                        <td>{{ $pr->requester?->name }}</td>
                        <td>{{ $pr->department?->displayName() ?? '—' }}</td>
                        <td class="nowrap">{{ fmt_date($pr->needed_by) }}</td>
                        <td class="num">{{ money($pr->estimated_total, $pr->currency) }}</td>
                        <td><x-badge :value="$pr->status" /></td>
                    </tr>
                @endforeach</tbody>
            </table></div>
            {{ $requests->links() }}
        @endif
    </section>
</x-layout>
