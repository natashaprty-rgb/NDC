<x-layout :title="$expense->description" :subtitle="$expense->reference"
          :breadcrumbs="[__('expenses.title') => route('expenses.index'), $expense->reference]">
    <x-slot:actions>
        @can('update', $expense)
            <a href="{{ route('expenses.edit', $expense) }}" class="btn"><x-icon name="edit" width="16" height="16" /> {{ __('common.edit') }}</a>
        @endcan
        @can('approve', $expense)
            <form method="POST" action="{{ route('expenses.approve', $expense) }}" class="inline-form" data-confirm="{{ __('expenses.confirm_approve') }}">@csrf<button class="btn btn--success" type="submit">{{ __('expenses.approve') }}</button></form>
        @endcan
        @can('reconcile', $expense)
            <form method="POST" action="{{ route('expenses.reconcile', $expense) }}" class="inline-form" data-confirm="{{ __('expenses.confirm_reconcile') }}">@csrf<button class="btn" type="submit">{{ __('expenses.reconcile') }}</button></form>
        @endcan
        @can('delete', $expense)
            <x-delete-button :action="route('expenses.destroy', $expense)" :message="__('expenses.confirm_delete')" class="btn btn--danger" />
        @endcan
    </x-slot:actions>

    <div class="alert alert--warning">{{ __('expenses.no_payment_note') }}</div>

    <div class="layout-main-side">
        <div>
            <x-card :title="__('common.details')">
                <dl class="dl">
                    <dt>{{ __('expenses.amount') }}</dt><dd><strong>{{ money($expense->amount, $expense->currency) }}</strong></dd>
                    <dt>{{ __('common.status') }}</dt><dd><x-badge :value="$expense->status" /></dd>
                    <dt>{{ __('expenses.budget') }}</dt><dd><a href="{{ route('budgets.show', $expense->budget) }}">{{ $expense->budget?->reference }} — {{ $expense->budget?->title }}</a></dd>
                    <dt>{{ __('expenses.category') }}</dt><dd>{{ $expense->category?->displayName() }}</dd>
                    <dt>{{ __('expenses.expense_date') }}</dt><dd>{{ fmt_date($expense->expense_date) }}</dd>
                    <dt>{{ __('expenses.payee') }}</dt><dd>{{ $expense->payee ?? '—' }}</dd>
                    <dt>{{ __('expenses.invoice_number') }}</dt><dd>{{ $expense->invoice_number ?? '—' }}</dd>
                    <dt>{{ __('expenses.supplier') }}</dt><dd>@if($expense->supplier && route_if('suppliers.show', $expense->supplier))<a href="{{ route('suppliers.show', $expense->supplier) }}">{{ $expense->supplier->name }}</a>@else{{ $expense->supplier?->name ?? '—' }}@endif</dd>
                    <dt>{{ __('expenses.purchase_order') }}</dt><dd>@if($expense->purchaseOrder && route_if('purchase-orders.show', $expense->purchaseOrder))<a href="{{ route('purchase-orders.show', $expense->purchaseOrder) }}">{{ $expense->purchaseOrder->reference }}</a>@else{{ $expense->purchaseOrder?->reference ?? '—' }}@endif</dd>
                </dl>
                @if($expense->notes)<p class="prose mt-2 mb-0">{{ $expense->notes }}</p>@endif
            </x-card>

            @can('approve', $expense)
                <x-card :title="__('expenses.reject')">
                    <form method="POST" action="{{ route('expenses.reject', $expense) }}" class="form-grid">
                        @csrf
                        <x-field.textarea name="review_note" :label="__('common.reason')" :required="true" :help="__('expenses.reject_help')" rows="2" class="span-2" />
                        <div class="span-2 text-right"><button class="btn btn--danger btn--sm" type="submit">{{ __('expenses.reject') }}</button></div>
                    </form>
                </x-card>
            @endcan

            @can('markPaid', $expense)
                <x-card :title="__('expenses.mark_paid')">
                    <p class="small muted">{{ __('expenses.no_payment_note') }}</p>
                    <form method="POST" action="{{ route('expenses.paid', $expense) }}" class="form-grid">
                        @csrf
                        <x-field.input name="paid_on" type="date" :label="__('expenses.paid_on')" :value="today()" :required="true" />
                        <x-field.input name="payment_reference" :label="__('expenses.payment_reference')" maxlength="100" />
                        <div class="span-2 text-right"><button class="btn btn--primary btn--sm" type="submit">{{ __('expenses.mark_paid') }}</button></div>
                    </form>
                </x-card>
            @endcan

            <x-attachments :model="$expense" :title="__('expenses.receipt')" :categories="['receipt', 'invoice', 'general']" />
        </div>
        <div>
            <x-card :title="__('expenses.workflow')">
                <ul class="timeline">
                    <li><strong>{{ status_label('recorded') }}</strong> — {{ $expense->recorder?->name ?? '—' }}<div class="when">{{ fmt_datetime($expense->created_at) }}</div></li>
                    @if($expense->approved_at)
                        <li><strong>{{ status_label('approved') }}</strong> — {{ $expense->approvedBy?->name ?? '—' }}<div class="when">{{ fmt_datetime($expense->approved_at) }}</div></li>
                    @endif
                    @if($expense->rejected_at)
                        <li><strong>{{ status_label('rejected') }}</strong> — {{ $expense->rejectedBy?->name ?? '—' }}<div class="when">{{ fmt_datetime($expense->rejected_at) }}</div>
                            @if($expense->review_note)<div class="small">{{ __('expenses.review_note') }}: {{ $expense->review_note }}</div>@endif</li>
                    @endif
                    @if($expense->paid_on)
                        <li><strong>{{ status_label('paid') }}</strong> — {{ $expense->settledBy?->name ?? '—' }}<div class="when">{{ fmt_date($expense->paid_on) }}</div>
                            @if($expense->payment_reference)<div class="small">{{ __('expenses.payment_reference') }}: {{ $expense->payment_reference }}</div>@endif</li>
                    @endif
                    @if($expense->reconciled_at)
                        <li><strong>{{ status_label('reconciled') }}</strong><div class="when">{{ fmt_datetime($expense->reconciled_at) }}</div></li>
                    @endif
                </ul>
            </x-card>
            <x-history :model="$expense" />
        </div>
    </div>
</x-layout>
