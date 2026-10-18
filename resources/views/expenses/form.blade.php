@php $editing = $expense->exists; @endphp
<x-layout :title="$editing ? __('expenses.edit') : __('expenses.new')"
          :breadcrumbs="[__('expenses.title') => route('expenses.index'), ($editing ? $expense->reference : __('expenses.new'))]">
    <div class="alert alert--info">{{ __('expenses.no_payment_note') }}</div>
    <x-card>
        <form method="POST" action="{{ $editing ? route('expenses.update', $expense) : route('expenses.store') }}" enctype="multipart/form-data">
            @csrf
            @if($editing) @method('PUT') @endif
            <div class="form-grid">
                <x-field.select name="budget_id" :label="__('expenses.budget')" :options="$budgets" :value="$expense->budget_id" :required="true" :help="__('expenses.currency_note')" />
                <x-field.select name="expense_category_id" :label="__('expenses.category')" :options="$categories" :value="$expense->expense_category_id" :required="true" />
                <x-field.input name="description" :label="__('common.description')" :value="$expense->description" :required="true" maxlength="500" class="span-2" />
                <x-field.input name="amount" :label="__('expenses.amount')" :value="$expense->amount" :required="true" inputmode="decimal" />
                <x-field.input name="expense_date" type="date" :label="__('expenses.expense_date')" :value="$expense->expense_date" :required="true" />
                <x-field.input name="payee" :label="__('expenses.payee')" :value="$expense->payee" maxlength="200" />
                <x-field.input name="invoice_number" :label="__('expenses.invoice_number')" :value="$expense->invoice_number" maxlength="100" />
                <x-field.select name="supplier_id" :label="__('expenses.supplier')" :options="$suppliers" :value="$expense->supplier_id" :placeholder="__('common.none')" />
                <x-field.select name="purchase_order_id" :label="__('expenses.purchase_order')" :options="$purchaseOrders" :value="$expense->purchase_order_id" :placeholder="__('common.none')" />
                <x-field.textarea name="notes" :label="__('common.notes')" :value="$expense->notes" class="span-2" rows="3" />
                <x-field.file name="receipt" :label="__('expenses.receipt')" class="span-2" />
            </div>
            <div class="form-actions">
                <a href="{{ $editing ? route('expenses.show', $expense) : route('expenses.index') }}" class="btn">{{ __('common.cancel') }}</a>
                <button type="submit" class="btn btn--primary">{{ $editing ? __('common.save_changes') : __('expenses.new') }}</button>
            </div>
        </form>
    </x-card>
</x-layout>
