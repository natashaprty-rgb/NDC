<x-layouts.app :title="__('Suppliers and contractors')">
    <x-page-header :title="__('Suppliers and contractors')" :subtitle="__('Registered suppliers, contractors and consultants with their performance records.')">
        <x-slot:actions>
            <a href="{{ route('suppliers.report', request()->query()) }}" class="btn"><x-icon name="printer" /> {{ __('Supplier list') }}</a>
            @can('create', \App\Models\Supplier::class)
                <a href="{{ route('suppliers.create') }}" class="btn btn-primary"><x-icon name="plus" /> {{ __('Register supplier') }}</a>
            @endcan
        </x-slot:actions>
    </x-page-header>
    <div class="card">
        <x-filters>
            <x-search-field :placeholder="__('Name, contact, e-mail or registration number')" />
            <x-filter-select name="type" :label="__('Type')" :options="\App\Support\Status::options('supplier_type')" />
            <x-filter-select name="category" :label="__('Category')" :options="$categories" />
            <x-filter-select name="status" :label="__('Status')" :options="\App\Support\Status::options('supplier')" />
        </x-filters>
        @if ($suppliers->isEmpty())
            <x-empty icon="truck" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr>
                    <th><x-sort-link column="name" :label="__('Name')" /></th>
                    <th><x-sort-link column="type" :label="__('Type')" /></th>
                    <th>{{ __('Category') }}</th>
                    <th>{{ __('Contact') }}</th>
                    <th class="text-right">{{ __('Orders') }}</th>
                    <th>{{ __('Rating') }}</th>
                    <th><x-sort-link column="status" :label="__('Status')" /></th>
                </tr></thead>
                <tbody>
                @foreach ($suppliers as $supplier)
                    <tr>
                        <td class="title-cell"><a href="{{ route('suppliers.show', $supplier) }}">{{ $supplier->name }}</a>
                            @if ($supplier->registration_number)<span class="sub">{{ __('Reg. no.') }} {{ $supplier->registration_number }}</span>@endif</td>
                        <td><x-status domain="supplier_type" :value="$supplier->type" /></td>
                        <td>{{ $supplier->category?->displayName() ?? '—' }}</td>
                        <td>{{ $supplier->contact_person ?? '—' }}<span class="sub">{{ collect([$supplier->phone, $supplier->email])->filter()->join(' · ') }}</span></td>
                        <td class="num text-right">{{ $supplier->purchase_orders_count }}</td>
                        <td class="nowrap">@include('suppliers.partials.rating', ['value' => $supplier->overallRating()])
                            @if ($supplier->evaluations_count)<span class="sub">{{ trans_choice(':count evaluation|:count evaluations', $supplier->evaluations_count) }}</span>@endif</td>
                        <td><x-status domain="supplier" :value="$supplier->status" /></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
        {{ $suppliers->links() }}
    </div>
</x-layouts.app>
