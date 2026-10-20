<x-layouts.app :title="__('Expense register')">
    <x-page-header :title="__('Expense register')" :subtitle="__('Actual expenditure recorded against approved budgets.')"
        :breadcrumbs="[__('Budgets') => route('budgets.index')]">
        <x-slot:actions>
            <a href="{{ route('expenses.report', request()->only(\App\Http\Controllers\Budgets\ExpenseController::FILTERS)) }}" class="btn"><x-icon name="printer" /> {{ __('Expenditure report') }}</a>
            @can('create', \App\Models\Expense::class)
                <a href="{{ route('expenses.create', array_filter(['budget' => request('budget')])) }}" class="btn btn-primary"><x-icon name="plus" /> {{ __('Record expense') }}</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <x-filters>
            <x-search-field :placeholder="__('Description, reference or payee')" />
            <x-filter-select name="budget" :label="__('Budget')" :options="$budgets" />
            <x-filter-select name="project" :label="__('Project')" :options="$projects" />
            <x-filter-select name="category" :label="__('Category')" :options="$categories" />
            <x-filter-select name="status" :label="__('Status')" :options="\App\Support\Status::options('expense')" />
            <div class="field">
                <label for="f_from">{{ __('From') }}</label>
                <input type="date" id="f_from" name="from" value="{{ request('from') }}" class="input">
            </div>
            <div class="field">
                <label for="f_to">{{ __('To') }}</label>
                <input type="date" id="f_to" name="to" value="{{ request('to') }}" class="input">
            </div>
        </x-filters>

        @if ($expenses->isEmpty())
            <x-empty icon="wallet" :message="__('No expenses match the selected filters.')" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr>
                    <th><x-sort-link column="expense_date" :label="__('Date')" /></th>
                    <th>{{ __('Description') }}</th>
                    <th>{{ __('Budget') }}</th>
                    <th>{{ __('Category') }}</th>
                    <th>{{ __('Payee') }}</th>
                    <th class="num"><x-sort-link column="amount" :label="__('Amount')" /></th>
                    <th><x-sort-link column="status" :label="__('Status')" /></th>
                </tr></thead>
                <tbody>
                @foreach ($expenses as $expense)
                    <tr id="expense-{{ $expense->id }}">
                        <td class="nowrap">{{ fmt_date($expense->expense_date) }}</td>
                        <td class="title-cell"><a href="{{ route('expenses.show', $expense) }}">{{ $expense->description }}</a>
                            @if ($expense->reference)<span class="sub">{{ $expense->reference }}</span>@endif</td>
                        <td>@if ($expense->budget)<a href="{{ route('budgets.show', $expense->budget) }}">{{ $expense->budget->code }}</a>@else — @endif</td>
                        <td>{{ $expense->category?->displayName() ?? '—' }}</td>
                        <td>{{ $expense->payee ?? '—' }}</td>
                        <td class="num">{{ money($expense->amount, $expense->budget?->currency) }}</td>
                        <td><x-status domain="expense" :value="$expense->status" /></td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                @foreach ($totals as $currency => $row)
                    <tr>
                        <th scope="row" colspan="5">{{ $totals->count() > 1 ? __('Total (:currency, all filtered expenses)', ['currency' => $currency]) : __('Total (all filtered expenses)') }}
                            <span class="sub small muted">{{ __('Approved :approved · Pending :pending · Rejected :rejected', ['approved' => money($row['approved'], $currency), 'pending' => money($row['pending'], $currency), 'rejected' => money($row['rejected'], $currency)]) }}</span>
                        </th>
                        <td class="num"><strong>{{ money($row['total'], $currency) }}</strong></td>
                        <td></td>
                    </tr>
                @endforeach
                </tfoot>
            </table></div>
        @endif
        {{ $expenses->links() }}
    </div>
</x-layouts.app>
