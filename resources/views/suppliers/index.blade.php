<x-layout :title="__('suppliers.title')" :breadcrumbs="[__('suppliers.title')]">
    <x-slot:actions>
        <a href="{{ route('contracts.index') }}" class="btn">{{ __('suppliers.contracts') }}</a>
        @can('create', \App\Models\Supplier::class)
            <a href="{{ route('suppliers.create') }}" class="btn btn--primary"><x-icon name="plus" width="16" height="16" /> {{ __('suppliers.new') }}</a>
        @endcan
    </x-slot:actions>

    <section class="card">
        <x-filter-bar :action="route('suppliers.index')">
            <x-field.input name="q" :label="__('common.search')" :value="request('q')" class="field--search" :placeholder="__('suppliers.search_hint')" />
            <x-field.select name="status" :label="__('common.status')" :options="enum_options(\App\Models\Supplier::STATUSES)" :value="request('status')" :placeholder="__('common.all')" />
        </x-filter-bar>

        @if($suppliers->isEmpty())
            <x-empty :message="request()->hasAny(['q', 'status']) ? __('common.no_records') : __('suppliers.empty')" icon="truck" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr>
                        <th>{{ __('suppliers.code') }}</th><th>{{ __('suppliers.name') }}</th><th>{{ __('suppliers.contact_person') }}</th>
                        <th>{{ __('suppliers.services') }}</th><th class="num">{{ __('suppliers.contracts') }}</th><th class="num">{{ __('suppliers.orders') }}</th><th>{{ __('common.status') }}</th>
                    </tr></thead>
                    <tbody>
                    @foreach($suppliers as $supplier)
                        <tr>
                            <td class="nowrap">{{ $supplier->code }}</td>
                            <td><a href="{{ route('suppliers.show', $supplier) }}">{{ $supplier->name }}</a></td>
                            <td>{{ $supplier->contact_person ?? '—' }}<br><span class="small muted">{{ $supplier->email }}</span></td>
                            <td class="small">{{ \Illuminate\Support\Str::limit($supplier->services, 60) ?: '—' }}</td>
                            <td class="num">{{ $supplier->contracts_count }}</td>
                            <td class="num">{{ $supplier->purchase_orders_count }}</td>
                            <td><x-badge :value="$supplier->status" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $suppliers->links() }}
        @endif
    </section>
</x-layout>
