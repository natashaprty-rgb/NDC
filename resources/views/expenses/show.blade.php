@php
    $budget = $expense->budget;
    $currency = $budget?->currency;
@endphp
<x-layouts.app :title="__('Expense')">
    <x-page-header :title="$expense->description" :breadcrumbs="array_filter([
        __('Budgets') => route('budgets.index'),
        ($budget?->code ?? '') => $budget ? route('budgets.show', $budget).'#expense-'.$expense->id : null,
    ])">
        <x-slot:meta>
            <p>{{ money($expense->amount, $currency) }} · {{ fmt_date($expense->expense_date) }} · <x-status domain="expense" :value="$expense->status" /></p>
        </x-slot:meta>
        <x-slot:actions>
            @if ($expense->permits('update'))
                <a href="{{ route('expenses.edit', $expense) }}" class="btn"><x-icon name="edit" /> {{ __('Edit') }}</a>
            @endif
            @if ($expense->permits('delete'))
                <x-delete-button :action="route('expenses.destroy', $expense)" :label="__('Withdraw')" class="btn btn-danger"
                    :confirm="__('Withdraw this expense? Its approval request will be cancelled.')" />
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($summary && $summary['over_committed'] && $expense->isPending())
        <div class="alert alert--warning" role="status">{{ __('Approved and pending expenses on this budget (:committed) exceed the budget amount of :amount. Approving this expense may overspend the budget.', ['committed' => money($summary['committed'], $currency), 'amount' => money($summary['amount'], $currency)]) }}</div>
    @endif

    <div class="grid grid-main">
        <div class="stack">
            <div class="card">
                <div class="card__head"><h2>{{ __('Expense details') }}</h2></div>
                <div class="card__body">
                    <dl class="dl">
                        <dt>{{ __('Budget') }}</dt>
                        <dd>@if ($budget)<a href="{{ route('budgets.show', $budget) }}">{{ $budget->code }} — {{ $budget->name }}</a> <x-status domain="budget" :value="$budget->status" />@else — @endif</dd>
                        <dt>{{ __('Expense category') }}</dt><dd>{{ $expense->category?->displayName() ?? '—' }}</dd>
                        <dt>{{ __('Description') }}</dt><dd>{{ $expense->description }}</dd>
                        <dt>{{ __('Amount') }}</dt><dd>{{ money($expense->amount, $currency) }}</dd>
                        <dt>{{ __('Expense date') }}</dt><dd>{{ fmt_date($expense->expense_date) }}</dd>
                        <dt>{{ __('Payee') }}</dt><dd>{{ $expense->payee ?? '—' }}</dd>
                        <dt>{{ __('Reference') }}</dt><dd>{{ $expense->reference ?? '—' }}</dd>
                        <dt>{{ __('Purchase order') }}</dt>
                        <dd>
                            @if ($expense->purchaseOrder)
                                @if (Route::has('purchase-orders.show') && auth()->user()->can('procurement.view'))
                                    <a href="{{ route('purchase-orders.show', $expense->purchaseOrder) }}">{{ $expense->purchaseOrder->po_number }}</a>
                                @else
                                    {{ $expense->purchaseOrder->po_number }}
                                @endif
                            @else — @endif
                        </dd>
                        <dt>{{ __('Recorded by') }}</dt><dd>{{ $expense->recorder?->name ?? '—' }} · {{ fmt_date($expense->created_at, true) }}</dd>
                        <dt>{{ __('Status') }}</dt><dd><x-status domain="expense" :value="$expense->status" /></dd>
                    </dl>
                </div>
            </div>

            @if ($summary)
                <div class="card">
                    <div class="card__head"><h2>{{ __('Budget position') }}</h2></div>
                    <div class="card__body">
                        <dl class="dl">
                            <dt>{{ __('Budget amount') }}</dt><dd>{{ money($summary['amount'], $currency) }}</dd>
                            <dt>{{ __('Approved spend') }}</dt><dd>{{ money($summary['approved'], $currency) }}</dd>
                            <dt>{{ __('Pending approval') }}</dt><dd>{{ money($summary['pending'], $currency) }}</dd>
                            <dt>{{ __('Remaining balance') }}</dt><dd>{{ money($summary['remaining'], $currency) }}</dd>
                            <dt>{{ __('Utilisation') }}</dt><dd><x-progress :value="$summary['utilisation']" :tone="\App\Models\Budget::utilisationTone($summary['utilisation'])" /></dd>
                        </dl>
                    </div>
                </div>
            @endif

            <x-attachments :model="$expense" collection="receipts" :title="__('Receipts and invoices')" :can-upload="$expense->permits('update')" />
        </div>

        <div class="stack">
            <x-approval-panel :model="$expense" :title="__('Expense approval')" />
            <x-audit-trail :model="$expense" />
        </div>
    </div>
</x-layouts.app>
