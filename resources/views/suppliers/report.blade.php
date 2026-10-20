<x-layouts.app :title="__('Supplier list')">
    <x-page-header :title="__('Supplier list')" :subtitle="__('Registered suppliers and contractors, as recorded on :date.', ['date' => fmt_date(now(), true)])" :breadcrumbs="[__('Suppliers') => route('suppliers.index')]">
        <x-slot:actions>
            <button type="button" class="btn btn-primary" data-print><x-icon name="printer" /> {{ __('Print') }}</button>
        </x-slot:actions>
    </x-page-header>
    <div class="card">
        <x-filters>
            <x-filter-select name="type" :label="__('Type')" :options="\App\Support\Status::options('supplier_type')" />
            <x-filter-select name="category" :label="__('Category')" :options="$categories" />
            <x-filter-select name="status" :label="__('Status')" :options="\App\Support\Status::options('supplier')" />
        </x-filters>
        @if ($suppliers->isEmpty())
            <x-empty icon="truck" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr>
                    <th>{{ __('Name') }}</th><th>{{ __('Type') }}</th><th>{{ __('Category') }}</th><th>{{ __('Registration / tax no.') }}</th>
                    <th>{{ __('Contact') }}</th><th class="text-right">{{ __('Orders') }}</th><th>{{ __('Rating') }}</th><th>{{ __('Status') }}</th>
                </tr></thead>
                <tbody>
                @foreach ($suppliers as $supplier)
                    @php $rating = $supplier->overallRating(); @endphp
                    <tr>
                        <td><a href="{{ route('suppliers.show', $supplier) }}">{{ $supplier->name }}</a></td>
                        <td>{{ \App\Support\Status::label('supplier_type', $supplier->type) }}</td>
                        <td>{{ $supplier->category?->displayName() ?? '—' }}</td>
                        <td>{{ collect([$supplier->registration_number, $supplier->tax_number])->filter()->join(' / ') ?: '—' }}</td>
                        <td>{{ $supplier->contact_person ?? '—' }}<span class="sub">{{ collect([$supplier->phone, $supplier->email])->filter()->join(' · ') }}</span></td>
                        <td class="num text-right">{{ $supplier->purchase_orders_count }}</td>
                        <td class="nowrap">{{ $rating === null ? __('Not rated') : number_format($rating, 1).' / 5' }}</td>
                        <td>{{ \App\Support\Status::label('supplier', $supplier->status) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="card__foot small muted">{{ trans_choice(':count supplier|:count suppliers', $suppliers->count()) }}</div>
        @endif
    </div>
</x-layouts.app>
