{{-- One planned-allocation row in the budget form repeater. $index is a number or "__INDEX__" in the template. --}}
<div class="repeater-row" data-repeater-row style="grid-template-columns:minmax(0,2fr) minmax(0,1fr) minmax(0,3fr) auto">
    <input type="hidden" name="allocations[{{ $index }}][id]" value="{{ $row['id'] ?? '' }}">
    <x-form.select name="allocations[{{ $index }}][category_id]" :label="__('Expense category')" :options="$categories" :value="$row['category_id'] ?? null" required />
    <x-form.input name="allocations[{{ $index }}][planned_amount]" type="number" step="0.01" min="0" inputmode="decimal" :label="__('Planned amount')" :value="$row['planned_amount'] ?? null" required />
    <x-form.input name="allocations[{{ $index }}][description]" :label="__('Description')" :value="$row['description'] ?? null" maxlength="255" />
    <div class="field"><button type="button" class="btn btn-sm btn-ghost" data-repeater-remove aria-label="{{ __('Remove allocation') }}"><x-icon name="trash" /> {{ __('Remove') }}</button></div>
</div>
