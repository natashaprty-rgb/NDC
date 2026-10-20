@php
    $title = $pr->exists ? __('Edit purchase request') : __('New purchase request');
    $rows = old('items', $pr->exists ? $pr->items->map(fn ($i) => $i->only(['description', 'specifications', 'quantity', 'unit', 'estimated_unit_cost']))->all() : [[]]);
    $rows = $rows ?: [[]];
@endphp
<x-layouts.app :title="$title">
    <x-page-header :title="$title" :subtitle="$pr->exists ? $pr->reference_number : __('The request is saved as a draft; submit it for approval from its page.')"
        :breadcrumbs="[__('Purchase requests') => route('procurement.index')]" />
    <form method="POST" action="{{ $pr->exists ? route('procurement.update', $pr) : route('procurement.store') }}" class="card form">
        @csrf
        @if ($pr->exists) @method('PUT') @endif
        <div class="card__body">
            <div class="form-grid">
                <x-form.input name="title" :label="__('Title')" :value="$pr->title" required maxlength="255" class="span-2" />
                <x-form.select name="department_id" :label="__('Requesting department')" :options="$departments" :value="$pr->department_id" />
                <x-form.select name="project_id" :label="__('Project')" :options="$projects" :value="$pr->project_id" />
                <x-form.select name="budget_id" :label="__('Budget line')" :options="$budgets" :value="$pr->budget_id" :help="__('Only approved budgets are listed.')" />
                <x-form.input name="needed_by" type="date" :label="__('Needed by')" :value="$pr->needed_by" />
                <x-form.input name="currency" :label="__('Currency')" :value="$pr->currency" required maxlength="3" />
                <x-form.textarea name="justification" :label="__('Justification')" :value="$pr->justification" rows="4" class="span-2"
                    :help="__('Explain why the goods or services are needed.')" />
            </div>

            <fieldset data-repeater data-repeater-next="{{ (int) collect(array_keys($rows))->max() + 1 }}">
                <legend>{{ __('Items') }}</legend>
                @error('items')<div class="field-error">{{ $message }}</div>@enderror
                <div data-repeater-rows>
                    @foreach ($rows as $i => $row)
                        @include('procurement.partials.item-row', ['index' => $i, 'item' => $row])
                    @endforeach
                </div>
                <template data-repeater-template>
                    @include('procurement.partials.item-row', ['index' => '__INDEX__', 'item' => []])
                </template>
                <button type="button" class="btn btn-sm" data-repeater-add style="margin-top:.6rem"><x-icon name="plus" /> {{ __('Add item') }}</button>
                <p class="small muted">{{ __('The estimated total is calculated from the items when the request is saved.') }}</p>
            </fieldset>
        </div>
        <div class="card__foot form-actions">
            <button class="btn btn-primary">{{ $pr->exists ? __('Save') : __('Save draft') }}</button>
            <a href="{{ $pr->exists ? route('procurement.show', $pr) : route('procurement.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
