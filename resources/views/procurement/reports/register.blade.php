<x-layouts.app :title="__('Procurement register')">
    <x-page-header :title="__('Procurement register')" :subtitle="__('Purchase requests with status, estimated totals and orders, as recorded on :date.', ['date' => fmt_date(now(), true)])"
        :breadcrumbs="[__('Purchase requests') => route('procurement.index')]">
        <x-slot:actions>
            <button type="button" class="btn btn-primary" data-print><x-icon name="printer" /> {{ __('Print') }}</button>
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <x-filters>
            <x-filter-select name="status" :label="__('Status')" :options="\App\Support\Status::options('procurement')" />
            <x-filter-select name="department" :label="__('Department')" :options="$departments" />
            <x-filter-select name="project" :label="__('Project')" :options="$projects" />
            <div class="field"><label for="flt_from">{{ __('From') }}</label><input type="date" id="flt_from" name="from" value="{{ request('from') }}" class="input"></div>
            <div class="field"><label for="flt_to">{{ __('To') }}</label><input type="date" id="flt_to" name="to" value="{{ request('to') }}" class="input"></div>
        </x-filters>
        <div class="card__body">
            <dl class="dl">
                <dt>{{ __('Requests') }}</dt><dd>{{ $requests->count() }}</dd>
                <dt>{{ __('By status') }}</dt>
                <dd>{{ $byStatus->isEmpty() ? '—' : $byStatus->map(fn ($n, $s) => \App\Support\Status::label('procurement', $s).': '.$n)->join(' · ') }}</dd>
                <dt>{{ __('Estimated value (excluding cancelled)') }}</dt>
                <dd>{{ $estimatedByCurrency->isEmpty() ? '—' : $estimatedByCurrency->map(fn ($sum, $cur) => money($sum, $cur))->join(' · ') }}</dd>
                <dt>{{ __('Ordered value') }}</dt>
                <dd>{{ $orderedByCurrency->isEmpty() ? '—' : $orderedByCurrency->map(fn ($sum, $cur) => money($sum, $cur))->join(' · ') }}</dd>
            </dl>
        </div>
    </div>

    <div class="card">
        @if ($requests->isEmpty())
            <x-empty icon="cart" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr>
                    <th>{{ __('Reference') }}</th><th>{{ __('Title') }}</th><th>{{ __('Department') }}</th><th>{{ __('Requested by') }}</th>
                    <th>{{ __('Created') }}</th><th>{{ __('Status') }}</th><th class="text-right">{{ __('Estimated total') }}</th><th>{{ __('Purchase orders') }}</th>
                </tr></thead>
                <tbody>
                @foreach ($requests as $pr)
                    <tr>
                        <td class="nowrap"><a href="{{ route('procurement.show', $pr) }}">{{ $pr->reference_number }}</a></td>
                        <td>{{ $pr->title }}</td>
                        <td>{{ $pr->department?->displayName() ?? '—' }}</td>
                        <td>{{ $pr->requester?->name ?? '—' }}</td>
                        <td class="nowrap">{{ fmt_date($pr->created_at) }}</td>
                        <td>{{ \App\Support\Status::label('procurement', $pr->status) }}</td>
                        <td class="num text-right nowrap">{{ money($pr->estimated_total, $pr->currency) }}</td>
                        <td>
                            @forelse ($pr->purchaseOrders as $po)
                                <div class="small">{{ $po->po_number }} · {{ $po->supplier?->name }} · {{ money($po->total_amount, $po->currency) }} · {{ \App\Support\Status::label('purchase_order', $po->status) }}</div>
                            @empty
                                —
                            @endforelse
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </div>
</x-layouts.app>
