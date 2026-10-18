<x-layout :title="__('expenses.title')" :subtitle="__('expenses.no_payment_note')" :breadcrumbs="[__('expenses.title')]">
    <x-slot:actions>
        @can('create', \App\Models\Expense::class)
            <a href="{{ route('expenses.create') }}" class="btn btn--primary"><x-icon name="plus" width="16" height="16" /> {{ __('expenses.new') }}</a>
        @endcan
    </x-slot:actions>

    <section class="card">
        <x-filter-bar :action="route('expenses.index')">
            <x-field.input name="q" :label="__('common.search')" :value="request('q')" class="field--search" :placeholder="__('expenses.search_hint')" />
            <x-field.select name="budget_id" :label="__('expenses.budget')" :options="$budgets" :value="request('budget_id')" :placeholder="__('common.all')" />
            <x-field.select name="expense_category_id" :label="__('expenses.category')" :options="$categories" :value="request('expense_category_id')" :placeholder="__('common.all')" />
            <x-field.select name="status" :label="__('common.status')" :options="enum_options(\App\Models\Expense::STATUSES)" :value="request('status')" :placeholder="__('common.all')" />
            <x-field.select name="project_id" :label="__('common.project')" :options="$projects" :value="request('project_id')" :placeholder="__('common.all')" />
            <x-field.select name="department_id" :label="__('common.department')" :options="$departments" :value="request('department_id')" :placeholder="__('common.all')" />
            <x-field.input name="from" type="date" :label="__('common.from')" :value="request('from')" />
            <x-field.input name="to" type="date" :label="__('common.to')" :value="request('to')" />
        </x-filter-bar>

        @if($expenses->isEmpty())
            <x-empty :message="request()->hasAny(['q', 'budget_id', 'expense_category_id', 'status', 'project_id', 'department_id', 'from', 'to']) ? __('common.no_records') : __('expenses.empty')" icon="receipt" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr>
                        <th>{{ __('common.reference') }}</th><th>{{ __('common.date') }}</th><th>{{ __('common.description') }}</th>
                        <th>{{ __('expenses.budget') }}</th><th>{{ __('expenses.category') }}</th><th class="num">{{ __('expenses.amount') }}</th><th>{{ __('common.status') }}</th>
                    </tr></thead>
                    <tbody>
                    @foreach($expenses as $expense)
                        <tr>
                            <td class="nowrap"><a href="{{ route('expenses.show', $expense) }}">{{ $expense->reference }}</a></td>
                            <td class="nowrap">{{ fmt_date($expense->expense_date) }}</td>
                            <td>{{ $expense->description }}@if($expense->payee)<br><span class="small muted">{{ $expense->payee }}</span>@endif</td>
                            <td class="small">{{ $expense->budget?->reference }}</td>
                            <td>{{ $expense->category?->displayName() }}</td>
                            <td class="num">{{ money($expense->amount, $expense->currency) }}</td>
                            <td><x-badge :value="$expense->status" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    @foreach($totals as $total)
                        <tr>
                            <td colspan="5">{{ __('expenses.totals') }} <span class="muted small">({{ __('expenses.totals_count', ['n' => $total->n]) }})</span></td>
                            <td class="num" data-total-currency="{{ $total->currency }}">{{ money((string) $total->total, $total->currency) }}</td>
                            <td></td>
                        </tr>
                    @endforeach
                    </tfoot>
                </table>
            </div>
            {{ $expenses->links() }}
        @endif
    </section>
</x-layout>
