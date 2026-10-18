@php
    $editing = $pr->exists;
    $rows = old('items', $items);
@endphp
<x-layout :title="$editing ? __('procurement.edit_pr') : __('procurement.new_pr')"
          :breadcrumbs="[__('procurement.title') => route('purchase-requests.index'), ($editing ? $pr->reference : __('procurement.new_pr'))]">
    <form method="POST" action="{{ $editing ? route('purchase-requests.update', $pr) : route('purchase-requests.store') }}">
        @csrf
        @if($editing) @method('PUT') @endif
        <x-card :title="__('common.details')">
            <div class="form-grid">
                <x-field.input name="title" :label="__('procurement.pr_title')" :value="$pr->title" :required="true" maxlength="250" class="span-2" />
                <x-field.select name="department_id" :label="__('common.department')" :options="$departments" :value="$pr->department_id" :placeholder="__('common.not_set')" />
                <x-field.select name="project_id" :label="__('common.project')" :options="$projects" :value="$pr->project_id" :placeholder="__('common.none')" />
                <x-field.select name="budget_id" :label="__('procurement.budget')" :options="$budgets" :value="$pr->budget_id" :placeholder="__('common.none')" :help="__('procurement.budget_help')" />
                <x-field.input name="needed_by" type="date" :label="__('procurement.needed_by')" :value="$pr->needed_by" />
                <x-field.input name="currency" :label="__('procurement.currency')" :value="$pr->currency" :required="true" maxlength="3" />
                <x-field.textarea name="justification" :label="__('procurement.justification')" :value="$pr->justification" class="span-2" />
            </div>
        </x-card>

        <section class="card" data-repeater>
            <div class="card__header"><h2>{{ __('procurement.items') }}</h2>
                <button type="button" class="btn btn--sm" data-repeater-add><x-icon name="plus" width="14" height="14" /> {{ __('procurement.add_item') }}</button>
            </div>
            <div class="card__body card__body--flush">
                @error('items')<p class="error-text" style="padding:.6rem 1.1rem 0">{{ $message }}</p>@enderror
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>{{ __('procurement.item_description') }}</th><th style="width:110px">{{ __('procurement.quantity') }}</th><th style="width:110px">{{ __('procurement.unit') }}</th><th style="width:150px">{{ __('procurement.unit_cost') }}</th><th></th></tr></thead>
                    <tbody data-repeater-list>
                    @foreach($rows as $i => $row)
                        <tr data-repeater-row>
                            <td><input class="input @error("items.$i.description") is-invalid @enderror" name="items[{{ $i }}][description]" value="{{ $row['description'] ?? '' }}" maxlength="500" required aria-label="{{ __('procurement.item_description') }}">
                                @error("items.$i.description")<span class="error">{{ $message }}</span>@enderror</td>
                            <td><input class="input @error("items.$i.quantity") is-invalid @enderror" name="items[{{ $i }}][quantity]" value="{{ $row['quantity'] ?? '' }}" inputmode="decimal" required data-qty aria-label="{{ __('procurement.quantity') }}">
                                @error("items.$i.quantity")<span class="error">{{ $message }}</span>@enderror</td>
                            <td><input class="input" name="items[{{ $i }}][unit]" value="{{ $row['unit'] ?? 'unit' }}" maxlength="30" aria-label="{{ __('procurement.unit') }}"></td>
                            <td><input class="input @error("items.$i.estimated_unit_cost") is-invalid @enderror" name="items[{{ $i }}][estimated_unit_cost]" value="{{ $row['estimated_unit_cost'] ?? '' }}" inputmode="decimal" required data-price aria-label="{{ __('procurement.unit_cost') }}">
                                @error("items.$i.estimated_unit_cost")<span class="error">{{ $message }}</span>@enderror</td>
                            <td class="text-right"><button type="button" class="btn btn--ghost btn--sm" data-repeater-remove aria-label="{{ __('common.remove') }}"><x-icon name="trash" width="14" height="14" /></button></td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot><tr><td colspan="3" class="text-right">{{ __('procurement.estimated_total') }}</td><td class="num"><span data-repeater-total>0.00</span></td><td></td></tr></tfoot>
                </table></div>
                <template data-repeater-template>
                    <tr data-repeater-row>
                        <td><input class="input" name="items[__INDEX__][description]" maxlength="500" required aria-label="{{ __('procurement.item_description') }}"></td>
                        <td><input class="input" name="items[__INDEX__][quantity]" value="1" inputmode="decimal" required data-qty aria-label="{{ __('procurement.quantity') }}"></td>
                        <td><input class="input" name="items[__INDEX__][unit]" value="unit" maxlength="30" aria-label="{{ __('procurement.unit') }}"></td>
                        <td><input class="input" name="items[__INDEX__][estimated_unit_cost]" inputmode="decimal" required data-price aria-label="{{ __('procurement.unit_cost') }}"></td>
                        <td class="text-right"><button type="button" class="btn btn--ghost btn--sm" data-repeater-remove aria-label="{{ __('common.remove') }}"><x-icon name="trash" width="14" height="14" /></button></td>
                    </tr>
                </template>
                <p class="small muted" style="padding:0 1.1rem .8rem">{{ __('procurement.total_note') }}</p>
            </div>
        </section>

        <div class="form-actions">
            <a href="{{ $editing ? route('purchase-requests.show', $pr) : route('purchase-requests.index') }}" class="btn">{{ __('common.cancel') }}</a>
            <button type="submit" class="btn btn--primary">{{ __('procurement.save_draft') }}</button>
        </div>
    </form>
</x-layout>
