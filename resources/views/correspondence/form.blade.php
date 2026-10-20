@php
    $isIncoming = $letter->direction === 'incoming';
    $title = $letter->exists
        ? __('Edit letter :ref', ['ref' => $letter->reference_number])
        : ($isIncoming ? __('Register incoming letter') : __('Register outgoing letter'));
@endphp
<x-layouts.app :title="$title">
    <x-page-header :title="$title" :breadcrumbs="[__('Correspondence') => route('correspondence.index')]"
        :subtitle="$letter->exists ? null : __('A unique reference number is assigned automatically when the letter is saved.')" />

    <form method="POST" action="{{ $letter->exists ? route('correspondence.update', $letter) : route('correspondence.store') }}" class="card form" enctype="multipart/form-data">
        @csrf
        @if ($letter->exists) @method('PUT') @endif
        <div class="card__body">
            <fieldset>
                <legend>{{ __('Letter details') }}</legend>
                <div class="form-grid">
                    @if ($letter->exists)
                        <div class="field">
                            <span class="label">{{ __('Direction') }}</span>
                            <div><x-status domain="correspondence_direction" :value="$letter->direction" /></div>
                        </div>
                    @else
                        <x-form.select name="direction" :label="__('Direction')" :options="\App\Support\Status::options('correspondence_direction')" :value="$letter->direction" required :placeholder="false" />
                    @endif
                    <x-form.input name="external_reference" :label="__('Sender\'s reference number')" :value="$letter->external_reference" maxlength="255"
                        :help="__('The number written on the letter by the sending office, if any.')" />
                    <x-form.input name="subject" :label="__('Subject')" :value="$letter->subject" required maxlength="255" class="span-2" />
                    <x-form.input name="sender" :label="__('Sender')" :value="$letter->sender" required maxlength="255" />
                    <x-form.input name="recipient" :label="__('Recipient')" :value="$letter->recipient" required maxlength="255" />
                    <x-form.input name="letter_date" type="date" :label="__('Letter date')" :value="$letter->letter_date" />
                    <x-form.input name="received_or_sent_date" type="date" :label="$isIncoming ? __('Date received') : __('Date sent')" :value="$letter->received_or_sent_date"
                        :help="$isIncoming ? null : __('Leave empty until the letter is dispatched.')" />
                    <x-form.select name="department_id" :label="__('Department')" :options="$departments" :value="$letter->department_id" />
                    <x-form.select name="responsible_officer_id" :label="__('Responsible officer')" :options="$users" :value="$letter->responsible_officer_id" />
                    <x-form.select name="priority" :label="__('Priority')" :options="\App\Support\Status::options('correspondence_priority')" :value="$letter->priority" required :placeholder="false" />
                    <x-form.checkbox name="is_confidential" :label="__('Confidential — visible only to authorised officers and the people handling it')" :checked="$letter->is_confidential" />
                    <x-form.textarea name="summary" :label="__('Summary')" :value="$letter->summary" rows="4" class="span-2" maxlength="10000" />
                    <x-form.textarea name="notes" :label="__('Notes')" :value="$letter->notes" rows="3" class="span-2" maxlength="10000" />
                </div>
            </fieldset>

            @unless ($letter->exists)
                <fieldset>
                    <legend>{{ __('Assignment for action (optional)') }}</legend>
                    <div class="form-grid">
                        <x-form.select name="assigned_to" :label="__('Assign to')" :options="$users" :value="$letter->assigned_to" />
                        <x-form.input name="response_due_date" type="date" :label="__('Response due date')" :value="$letter->response_due_date" />
                        <x-form.textarea name="assignment_note" :label="__('Instructions')" rows="2" class="span-2" maxlength="2000" />
                    </div>
                </fieldset>
                <fieldset>
                    <legend>{{ __('Scanned letter') }}</legend>
                    <x-form.input type="file" name="scan" :label="__('Upload scanned copy')"
                        :help="__('Allowed: :types (max :size MB). More files can be attached after saving.', ['types' => strtoupper(str_replace(',', ', ', setting('upload_allowed_extensions'))), 'size' => round(setting('upload_max_kb') / 1024)])" />
                </fieldset>
            @endunless
        </div>
        <div class="card__foot form-actions">
            <button class="btn btn-primary" type="submit">{{ $letter->exists ? __('Save') : __('Register letter') }}</button>
            <a href="{{ $letter->exists ? route('correspondence.show', $letter) : route('correspondence.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
