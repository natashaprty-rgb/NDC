@php
    $title = $budget->exists ? __('Edit budget') : __('New budget');
    $rows = old('allocations', $budget->exists
        ? $budget->allocations->map(fn ($a) => ['id' => $a->id, 'category_id' => $a->category_id, 'planned_amount' => $a->planned_amount, 'description' => $a->description])->all()
        : []);
    $rows = is_array($rows) ? $rows : [];
    $nextIndex = $rows === [] ? 0 : max(array_map('intval', array_keys($rows))) + 1;
@endphp
<x-layouts.app :title="$title">
    <x-page-header :title="$title" :subtitle="$budget->exists ? $budget->code.' — '.$budget->name : null"
        :breadcrumbs="array_filter([__('Budgets') => route('budgets.index'), ($budget->exists ? $budget->code : '') => $budget->exists ? route('budgets.show', $budget) : null])" />

    <form method="POST" action="{{ $budget->exists ? route('budgets.update', $budget) : route('budgets.store') }}" class="card form">
        @csrf
        @if ($budget->exists) @method('PUT') @endif
        <div class="card__body">
            @unless ($canChangeAmounts)
                <div class="alert alert--info" role="status">{{ __('This budget has been approved. Only its name and notes can be changed; the amount, scope, period and allocations are locked.') }}</div>
            @endunless

            <fieldset>
                <legend>{{ __('Budget details') }}</legend>
                <div class="form-grid">
                    <x-form.input name="name" :label="__('Budget name')" :value="$budget->name" required maxlength="255" />
                    @if ($canChangeAmounts)
                        <x-form.input name="code" :label="__('Budget code')" :value="$budget->code" maxlength="40"
                            :help="__('Leave blank to generate the next official number automatically.')" />
                        <x-form.input name="fiscal_year" :label="__('Fiscal year')" :value="$budget->fiscal_year" required maxlength="9"
                            pattern="\d{4}(/\d{4})?" :help="__('For example 2026 or 2026/2027.')" />
                        <x-form.input name="amount" type="number" step="0.01" min="0.01" inputmode="decimal" :label="__('Approved amount')" :value="$budget->amount" required />
                        <x-form.input name="currency" :label="__('Currency')" :value="$budget->currency" required maxlength="3" minlength="3"
                            :help="__('Three-letter ISO code, for example USD.')" />
                        <x-form.input name="start_date" type="date" :label="__('Start date')" :value="$budget->start_date" />
                        <x-form.input name="end_date" type="date" :label="__('End date')" :value="$budget->end_date" />
                    @endif
                </div>
            </fieldset>

            @if ($canChangeAmounts)
                <fieldset>
                    <legend>{{ __('Budget holder') }}</legend>
                    <p class="help">{{ __('Select at least one of project, department or funding source.') }}</p>
                    <div class="form-grid">
                        <x-form.select name="project_id" :label="__('Project')" :options="$projects" :value="$budget->project_id" />
                        <x-form.select name="department_id" :label="__('Department')" :options="$departments" :value="$budget->department_id" />
                        <x-form.select name="funding_source_id" :label="__('Funding source')" :options="$fundingSources" :value="$budget->funding_source_id" />
                    </div>
                </fieldset>

                <fieldset data-repeater data-repeater-next="{{ $nextIndex }}">
                    <legend>{{ __('Planned expenditure by category') }}</legend>
                    <p class="help">{{ __('The total of the planned allocations may not exceed the approved amount.') }}</p>
                    @error('allocations')
                        <div class="alert alert--error" role="alert">{{ $message }}</div>
                    @enderror
                    <div data-repeater-rows>
                        @foreach ($rows as $index => $row)
                            @include('budgets.partials.allocation-row', ['index' => $index, 'row' => is_array($row) ? $row : []])
                        @endforeach
                    </div>
                    <template data-repeater-template>
                        @include('budgets.partials.allocation-row', ['index' => '__INDEX__', 'row' => []])
                    </template>
                    <button type="button" class="btn btn-sm" data-repeater-add><x-icon name="plus" /> {{ __('Add allocation') }}</button>
                </fieldset>
            @endif

            <x-form.textarea name="notes" :label="__('Notes')" :value="$budget->notes" rows="3" maxlength="5000" />
        </div>
        <div class="card__foot form-actions">
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
            <a href="{{ $budget->exists ? route('budgets.show', $budget) : route('budgets.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
