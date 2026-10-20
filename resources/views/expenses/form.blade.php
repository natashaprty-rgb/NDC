@php
    $title = $expense->exists ? __('Edit expense') : __('Record expense');
@endphp
<x-layouts.app :title="$title">
    <x-page-header :title="$title" :breadcrumbs="array_filter([
        __('Budgets') => route('budgets.index'),
        ($budget?->code ?? '') => $budget ? route('budgets.show', $budget) : null,
    ])" />

    @if (! $expense->exists && empty($budgets))
        <div class="alert alert--warning" role="status">{{ __('There are no approved, open budgets. An expense can only be recorded against an approved budget.') }}</div>
    @endif

    <form method="POST" action="{{ $expense->exists ? route('expenses.update', $expense) : route('expenses.store') }}" class="card form" enctype="multipart/form-data">
        @csrf
        @if ($expense->exists) @method('PUT') @endif
        <div class="card__body">
            <div class="form-grid">
                @if ($expense->exists)
                    <div class="field">
                        <span class="label">{{ __('Budget') }}</span>
                        <div>{{ $budget?->code }} — {{ $budget?->name }}</div>
                    </div>
                @else
                    <x-form.select name="budget_id" :label="__('Budget')" :options="$budgets" :value="$budget?->id" required
                        :help="__('Only approved budgets that are still open are listed.')" />
                @endif
                <x-form.select name="category_id" :label="__('Expense category')" :options="$categories" :value="$expense->category_id" required />
                <x-form.input name="description" class="span-2" :label="__('Description')" :value="$expense->description" required maxlength="255" />
                <x-form.input name="amount" type="number" step="0.01" min="0.01" inputmode="decimal" :label="__('Amount')" :value="$expense->amount" required
                    :help="$budget ? __('Currency: :currency', ['currency' => $budget->currency]) : __('In the currency of the selected budget.')" />
                <x-form.input name="expense_date" type="date" :label="__('Expense date')" :value="$expense->expense_date" required
                    :help="$budget && ($budget->start_date || $budget->end_date) ? __('Budget period: :start – :end', ['start' => fmt_date($budget->start_date), 'end' => fmt_date($budget->end_date)]) : null" />
                <x-form.input name="payee" :label="__('Payee')" :value="$expense->payee" maxlength="255" />
                <x-form.input name="reference" :label="__('Reference')" :value="$expense->reference" maxlength="255"
                    :help="__('Invoice, receipt or voucher number.')" />
                @if (count($purchaseOrders))
                    <x-form.select name="purchase_order_id" :label="__('Purchase order')" :options="$purchaseOrders" :value="$expense->purchase_order_id"
                        :placeholder="__('— None —')" />
                @endif
                <x-form.input name="receipts[]" type="file" multiple :label="__('Receipts and invoices')" id="f_receipts"
                    :help="__('Optional. Up to 10 files. Allowed: :types (max :size MB each)', ['types' => strtoupper(str_replace(',', ', ', setting('upload_allowed_extensions'))), 'size' => round(setting('upload_max_kb') / 1024)])" />
            </div>
            @foreach ($errors->get('receipts.*') as $messages)
                @foreach ($messages as $message)<div class="field-error">{{ $message }}</div>@endforeach
            @endforeach
            <p class="small muted">{{ __('The expense will be sent for approval. It only counts towards budget utilisation once another authorised user approves it.') }}</p>
        </div>
        <div class="card__foot form-actions">
            <button type="submit" class="btn btn-primary">{{ $expense->exists ? __('Save') : __('Record expense') }}</button>
            <a href="{{ $expense->exists ? route('expenses.show', $expense) : ($budget ? route('budgets.show', $budget) : route('expenses.index')) }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
