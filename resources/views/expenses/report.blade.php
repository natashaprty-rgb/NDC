<x-layouts.app :title="__('Expenditure report')">
    <x-page-header :title="__('Expenditure report')" :subtitle="__('Recorded expenditure by category and in detail. Generated :date.', ['date' => fmt_date(now(), true)])"
        :breadcrumbs="[__('Budgets') => route('budgets.index'), __('Expense register') => route('expenses.index', request()->query())]">
        <x-slot:actions>
            <button type="button" class="btn btn-primary" data-print><x-icon name="printer" /> {{ __('Print') }}</button>
        </x-slot:actions>
    </x-page-header>

    @if ($filterLabels)
        <p class="small muted">{{ __('Filters') }}:
            @foreach ($filterLabels as $label => $value){{ $label }}: <strong>{{ $value }}</strong>@if (! $loop->last) · @endif @endforeach
        </p>
    @endif

    @if ($count === 0)
        <div class="card"><x-empty icon="wallet" :message="__('No expenses match the selected filters.')" /></div>
    @else
        <div class="card">
            <div class="card__head"><h2>{{ __('Summary by category') }}</h2></div>
            <div class="table-wrap"><table class="table">
                <thead><tr>
                    <th scope="col">{{ __('Expense category') }}</th>
                    <th scope="col" class="num">{{ __('Lines') }}</th>
                    <th scope="col" class="num">{{ __('Approved') }}</th>
                    <th scope="col" class="num">{{ __('Pending') }}</th>
                    <th scope="col" class="num">{{ __('Rejected') }}</th>
                </tr></thead>
                <tbody>
                @foreach ($categoryRows as $row)
                    <tr>
                        <th scope="row" style="font-weight:500">{{ $row['category'] }}@if ($totals->count() > 1) <span class="muted small">({{ $row['currency'] }})</span>@endif</th>
                        <td class="num">{{ $row['lines'] }}</td>
                        <td class="num">{{ money($row['approved'], $row['currency']) }}</td>
                        <td class="num">{{ money($row['pending'], $row['currency']) }}</td>
                        <td class="num">{{ money($row['rejected'], $row['currency']) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                @foreach ($totals as $currency => $row)
                    <tr>
                        <th scope="row">{{ $totals->count() > 1 ? __('Total (:currency)', ['currency' => $currency]) : __('Total') }}</th>
                        <td></td>
                        <td class="num"><strong>{{ money($row['approved'], $currency) }}</strong></td>
                        <td class="num"><strong>{{ money($row['pending'], $currency) }}</strong></td>
                        <td class="num"><strong>{{ money($row['rejected'], $currency) }}</strong></td>
                    </tr>
                @endforeach
                </tfoot>
            </table></div>
        </div>

        <div class="card">
            <div class="card__head"><h2>{{ __('Expense lines') }}</h2>
                <span class="small muted">{{ trans_choice(':count expense|:count expenses', $count) }}</span></div>
            @if ($count > $limit)
                <div class="alert alert--warning" role="status" style="margin:1rem">{{ __('Showing the first :limit of :total expenses. Narrow the filters to include the rest.', ['limit' => $limit, 'total' => $count]) }}</div>
            @endif
            <div class="table-wrap"><table class="table">
                <thead><tr>
                    <th scope="col">{{ __('Date') }}</th>
                    <th scope="col">{{ __('Budget') }}</th>
                    <th scope="col">{{ __('Category') }}</th>
                    <th scope="col">{{ __('Description') }}</th>
                    <th scope="col">{{ __('Payee') }}</th>
                    <th scope="col">{{ __('Reference') }}</th>
                    <th scope="col" class="num">{{ __('Amount') }}</th>
                    <th scope="col">{{ __('Status') }}</th>
                </tr></thead>
                <tbody>
                @foreach ($expenses as $expense)
                    <tr>
                        <td class="nowrap">{{ fmt_date($expense->expense_date) }}</td>
                        <td>{{ $expense->budget?->code ?? '—' }}</td>
                        <td>{{ $expense->category?->displayName() ?? '—' }}</td>
                        <td>{{ $expense->description }}</td>
                        <td>{{ $expense->payee ?? '—' }}</td>
                        <td>{{ $expense->reference ?? '—' }}</td>
                        <td class="num">{{ money($expense->amount, $expense->budget?->currency) }}</td>
                        <td><x-status domain="expense" :value="$expense->status" /></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </div>
    @endif
</x-layouts.app>
