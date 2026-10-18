@php $editing = $budget->exists; @endphp
<x-layout :title="$editing ? __('budgets.edit') : __('budgets.new')"
          :breadcrumbs="[__('budgets.title') => route('budgets.index'), ($editing ? $budget->reference : __('budgets.new'))]">
    <x-card>
        <form method="POST" action="{{ $editing ? route('budgets.update', $budget) : route('budgets.store') }}">
            @csrf
            @if($editing) @method('PUT') @endif
            <div class="form-grid">
                <x-field.input name="title" :label="__('budgets.title_field')" :value="$budget->title" :required="true" maxlength="200" class="span-2" />
                <x-field.input name="fiscal_year" type="number" :label="__('budgets.fiscal_year')" :value="$budget->fiscal_year" :required="true" min="2000" max="2100" />
                <x-field.input name="currency" :label="__('budgets.currency')" :value="$budget->currency" :required="true" maxlength="3" />
                <x-field.input name="period_start" type="date" :label="__('budgets.period_start')" :value="$budget->period_start" />
                <x-field.input name="period_end" type="date" :label="__('budgets.period_end')" :value="$budget->period_end" />
                <x-field.select name="project_id" :label="__('common.project')" :options="$projects" :value="$budget->project_id" :placeholder="__('common.none')" :help="__('budgets.owner_help')" />
                <x-field.select name="department_id" :label="__('common.department')" :options="$departments" :value="$budget->department_id" :placeholder="__('common.none')" />
                <x-field.select name="funding_source_id" :label="__('budgets.funding_source')" :options="$fundingSources" :value="$budget->funding_source_id" :placeholder="__('common.not_set')" />
                <x-field.textarea name="notes" :label="__('common.notes')" :value="$budget->notes" class="span-2" />
            </div>
            <div class="form-actions">
                <a href="{{ $editing ? route('budgets.show', $budget) : route('budgets.index') }}" class="btn">{{ __('common.cancel') }}</a>
                <button type="submit" class="btn btn--primary">{{ $editing ? __('common.save_changes') : __('common.create') }}</button>
            </div>
        </form>
    </x-card>
</x-layout>
