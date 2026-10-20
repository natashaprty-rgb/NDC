@php
    $title = $staff->exists ? __('Edit staff profile') : __('New staff member');
@endphp
<x-layouts.app :title="$title">
    <x-page-header :title="$title" :subtitle="$staff->exists ? $staff->full_name : null"
        :breadcrumbs="$staff->exists ? [__('Staff') => route('staff.index'), $staff->full_name => route('staff.show', $staff)] : [__('Staff') => route('staff.index')]" />
    <form method="POST" action="{{ $staff->exists ? route('staff.update', $staff) : route('staff.store') }}" class="card form">
        @csrf
        @if ($staff->exists) @method('PUT') @endif
        <div class="card__body">
            <fieldset>
                <legend>{{ __('Employment') }}</legend>
                <div class="form-grid">
                    <x-form.input name="full_name" :label="__('Full name')" :value="$staff->full_name" required maxlength="255" />
                    <x-form.input name="staff_number" :label="__('Staff number')" :value="$staff->staff_number" :required="$staff->exists" maxlength="40"
                        :help="$staff->exists ? null : __('Leave blank to assign the next number automatically.')" />
                    <x-form.input name="job_title" :label="__('Job title')" :value="$staff->job_title" maxlength="255" />
                    <x-form.select name="department_id" :label="__('Department')" :options="$departments" :value="$staff->department_id" />
                    <x-form.select name="supervisor_id" :label="__('Supervisor')" :options="$supervisors" :value="$staff->supervisor_id" />
                    <x-form.select name="user_id" :label="__('System user account')" :options="$users" :value="$staff->user_id"
                        :placeholder="__('— Not linked —')" :help="__('Links the profile to a login so assigned tasks can be shown.')" />
                    <x-form.select name="employment_status" :label="__('Employment status')" :options="\App\Support\Status::options('employment')" :value="$staff->employment_status" required :placeholder="false" />
                    <x-form.select name="employment_type" :label="__('Employment type')" :options="\App\Support\Status::options('employment_type')" :value="$staff->employment_type" required :placeholder="false" />
                    <x-form.input name="hire_date" type="date" :label="__('Hire date')" :value="$staff->hire_date" />
                    <x-form.input name="end_date" type="date" :label="__('End date')" :value="$staff->end_date" />
                </div>
            </fieldset>
            <fieldset>
                <legend>{{ __('Official contact') }}</legend>
                <div class="form-grid">
                    <x-form.input name="email" type="email" :label="__('Official e-mail')" :value="$staff->email" maxlength="255" />
                    <x-form.input name="phone" :label="__('Official phone')" :value="$staff->phone" maxlength="50" />
                    <x-form.select name="gender" :label="__('Gender')" :options="collect(\App\Models\Staff::GENDERS)->map(fn ($l) => __($l))" :value="$staff->gender" />
                </div>
            </fieldset>
            @if ($canEditSensitive)
                <fieldset>
                    <legend><x-icon name="lock" /> {{ __('Sensitive personal information') }}</legend>
                    <p class="small muted">{{ __('Record only what is necessary. These fields are encrypted and visible only to authorised users.') }}</p>
                    <div class="form-grid">
                        <x-form.input name="date_of_birth" type="date" :label="__('Date of birth')" :value="$staff->date_of_birth" />
                        <x-form.input name="national_id" :label="__('National ID number')" :value="$staff->national_id" maxlength="50" autocomplete="off" />
                        <x-form.input name="personal_phone" :label="__('Personal phone')" :value="$staff->personal_phone" maxlength="50" autocomplete="off" />
                        <x-form.input name="emergency_contact" :label="__('Emergency contact')" :value="$staff->emergency_contact" maxlength="500" autocomplete="off"
                            :help="__('Name, relationship and phone number.')" />
                        <x-form.textarea name="address" :label="__('Home address')" :value="$staff->address" rows="2" maxlength="1000" class="span-2" />
                    </div>
                </fieldset>
            @endif
            <x-form.textarea name="notes" :label="__('Notes')" :value="$staff->notes" rows="3" maxlength="5000" />
        </div>
        <div class="card__foot form-actions">
            <button class="btn btn-primary">{{ __('Save') }}</button>
            <a href="{{ $staff->exists ? route('staff.show', $staff) : route('staff.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
