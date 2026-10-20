<div class="card" id="quotations">
    <div class="card__head"><h2>{{ __('Quotations') }}</h2>
        <span class="muted small">{{ trans_choice(':count quotation|:count quotations', $pr->quotations->count()) }}</span></div>
    @if ($pr->quotations->isEmpty())
        <x-empty icon="file" :message="in_array($pr->status, ['draft', 'submitted', 'rejected'], true) ? __('Quotations can be recorded once the request is approved.') : __('No quotations recorded.')" />
    @else
        <div class="table-wrap"><table class="table">
            <thead><tr><th>{{ __('Supplier') }}</th><th>{{ __('Reference') }}</th><th>{{ __('Received') }}</th><th>{{ __('Valid until') }}</th><th class="text-right">{{ __('Amount') }}</th><th></th></tr></thead>
            <tbody>
            @foreach ($pr->quotations as $quotation)
                <tr>
                    <td>@if ($quotation->supplier)<a href="{{ route('suppliers.show', $quotation->supplier) }}">{{ $quotation->supplier->name }}</a>@else — @endif
                        @if ($quotation->is_selected) <span class="badge badge--success">{{ __('Selected') }}</span>@endif
                        @if ($quotation->notes)<span class="sub">{{ $quotation->notes }}</span>@endif
                        @foreach ($quotation->attachments as $file)
                            <span class="sub"><x-icon name="paperclip" /> <a href="{{ route('attachments.download', $file) }}">{{ $file->original_name }}</a></span>
                        @endforeach
                    </td>
                    <td>{{ $quotation->reference ?? '—' }}</td>
                    <td class="nowrap">{{ fmt_date($quotation->received_on) }}</td>
                    <td class="nowrap">{{ fmt_date($quotation->valid_until) }} @if ($quotation->isExpired())<span class="badge badge--neutral">{{ __('Expired') }}</span>@endif</td>
                    <td class="num text-right nowrap">{{ money($quotation->amount, $quotation->currency) }}</td>
                    <td class="actions">
                        @if ($canManage)
                            @unless ($quotation->is_selected)
                                <form method="POST" action="{{ route('procurement.quotations.select', [$pr, $quotation]) }}" class="inline-form">@csrf<button class="btn btn-sm">{{ __('Select') }}</button></form>
                            @endunless
                            <x-delete-button :action="route('procurement.quotations.destroy', [$pr, $quotation])" :label="__('Remove')" class="btn btn-sm btn-ghost" :confirm="__('Remove this quotation?')" />
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    @endif
    @if ($canManage)
        <div class="card__foot">
            <details @if ($errors->hasAny(['supplier_id', 'amount'])) open @endif>
                <summary><strong>{{ __('Record a quotation') }}</strong></summary>
                <form method="POST" action="{{ route('procurement.quotations.store', $pr) }}" enctype="multipart/form-data" class="form-grid" style="margin-top:.75rem">
                    @csrf
                    <x-form.select name="supplier_id" :label="__('Supplier')" :options="$suppliers" required />
                    <x-form.input name="reference" :label="__('Quotation reference')" maxlength="255" />
                    <x-form.input name="amount" type="number" step="0.01" min="0" :label="__('Amount')" required />
                    <x-form.input name="currency" :label="__('Currency')" :value="$pr->currency" required maxlength="3" />
                    <x-form.input name="received_on" type="date" :label="__('Received on')" :value="today()->toDateString()" />
                    <x-form.input name="valid_until" type="date" :label="__('Valid until')" />
                    <x-form.textarea name="notes" :label="__('Notes')" rows="2" class="span-2" />
                    <x-form.input type="file" name="file" :label="__('Quotation document')" class="span-2" />
                    <div class="span-2"><button class="btn btn-primary">{{ __('Save quotation') }}</button></div>
                </form>
                @if (empty($suppliers) && Route::has('suppliers.create'))
                    <p class="small muted">{{ __('No active suppliers yet.') }} @can('create', \App\Models\Supplier::class)<a href="{{ route('suppliers.create') }}">{{ __('Register a supplier') }}</a>@endcan</p>
                @endif
            </details>
        </div>
    @endif
</div>
