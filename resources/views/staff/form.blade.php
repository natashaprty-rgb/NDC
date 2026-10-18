@php $editing = $member->exists; @endphp
<x-layout :title="$editing ? __('staff.edit') : __('staff.new')"
          :breadcrumbs="[__('staff.title') => route('staff.index'), ($editing ? $member->full_name : __('staff.new'))]">
    <x-card>
        <form method="POST" action="{{ $editing ? route('staff.update', $member) : route('staff.store') }}">
            @csrf
            @if($editing) @method('PUT') @endif
            <fieldset>
                <legend>{{ __('staff.official_details') }}</legend>
                <div class="form-grid">
                    <x-field.input name="staff_number" :label="__('staff.staff_number')" :value="$member->staff_number" :required="true" maxlength="30" />
                    <x-field.input name="full_name" :label="__('staff.full_name')" :value="$member->full_name" :required="true" maxlength="150" />
                    <x-field.input name="job_title" :label="__('staff.job_title')" :value="$member->job_title" maxlength="150" />
                    <x-field.select name="department_id" :label="__('common.department')" :options="$departments" :value="$member->department_id" :placeholder="__('common.not_set')" />
                    <x-field.input name="official_email" type="email" :label="__('staff.official_email')" :value="$member->official_email" />
                    <x-field.input name="official_phone" :label="__('staff.official_phone')" :value="$member->official_phone" />
                    <x-field.input name="office_location" :label="__('staff.office_location')" :value="$member->office_location" />
                    <x-field.select name="user_id" :label="__('staff.user_account')" :options="$users" :value="$member->user_id" :placeholder="__('common.none')" :help="__('staff.user_account_help')" />
                </div>
            </fieldset>
            <fieldset>
                <legend>{{ __('staff.employment_details') }}</legend>
                <p class="small muted">{{ __('staff.sensitive_notice') }}</p>
                <div class="form-grid">
                    <x-field.select name="employment_status" :label="__('staff.employment_status')" :options="enum_options(\App\Models\Staff::EMPLOYMENT_STATUSES)" :value="$member->employment_status" :required="true" :placeholder="false" />
                    <x-field.select name="employment_type" :label="__('staff.employment_type')" :options="enum_options(\App\Models\Staff::EMPLOYMENT_TYPES)" :value="$member->employment_type" :required="true" :placeholder="false" />
                    <x-field.input name="start_date" type="date" :label="__('staff.start_date')" :value="$member->start_date" />
                    <x-field.input name="end_date" type="date" :label="__('staff.end_date')" :value="$member->end_date" />
                    <x-field.textarea name="notes" :label="__('common.notes')" :value="$member->notes" class="span-2" />
                </div>
            </fieldset>
            <div class="form-actions">
                <a href="{{ $editing ? route('staff.show', $member) : route('staff.index') }}" class="btn">{{ __('common.cancel') }}</a>
                <button type="submit" class="btn btn--primary">{{ $editing ? __('common.save_changes') : __('common.create') }}</button>
            </div>
        </form>
    </x-card>
</x-layout>
