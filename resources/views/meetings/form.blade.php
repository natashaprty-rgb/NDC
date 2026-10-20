@php
    $title = $meeting->exists ? __('Edit meeting') : __('Schedule meeting');
    $guestRows = old('guests', $guests->map(fn ($g) => ['id' => $g->id, 'name' => $g->name, 'organization' => $g->organization, 'email' => $g->email])->all());
    $guestRows = array_values(is_array($guestRows) ? $guestRows : []);
@endphp
<x-layouts.app :title="$title">
    <x-page-header :title="$title" :breadcrumbs="array_filter([__('Meetings & Calendar') => route('meetings.index'), ($meeting->exists ? $meeting->title : null) => $meeting->exists ? route('meetings.show', $meeting) : null])" />

    <form method="POST" action="{{ $meeting->exists ? route('meetings.update', $meeting) : route('meetings.store') }}" class="card form">
        @csrf
        @if ($meeting->exists) @method('PUT') @endif
        <div class="card__body">
            <fieldset>
                <legend>{{ __('Meeting details') }}</legend>
                <div class="form-grid">
                    <x-form.input name="title" :label="__('Title')" :value="$meeting->title" required maxlength="255" class="span-2" />
                    <x-form.select name="type" :label="__('Type')" :options="\App\Support\Status::options('meeting_type')" :value="$meeting->type" required :placeholder="false" />
                    <x-form.input name="location" :label="__('Location')" :value="$meeting->location" maxlength="255" />
                    <x-form.input name="start_at" type="datetime-local" :label="__('Starts')" :value="$meeting->start_at" required />
                    <x-form.input name="end_at" type="datetime-local" :label="__('Ends')" :value="$meeting->end_at" :help="__('Must be after the start time.')" />
                    <x-form.select name="organizer_id" :label="__('Organizer')" :options="$users" :value="$meeting->organizer_id" />
                    <x-form.select name="department_id" :label="__('Department')" :options="$departments" :value="$meeting->department_id" />
                    <x-form.select name="project_id" :label="__('Related project')" :options="$projects" :value="$meeting->project_id" />
                    <x-form.textarea name="description" :label="__('Description')" :value="$meeting->description" rows="3" class="span-2" maxlength="10000" />
                    <x-form.textarea name="agenda" :label="__('Agenda')" :value="$meeting->agenda" rows="6" class="span-2" maxlength="20000"
                        :help="__('One item per line. The agenda appears on the invitation page and in the printed minutes.')" />
                </div>
            </fieldset>

            <fieldset>
                <legend>{{ __('Internal participants') }}</legend>
                <x-form.select name="participants[]" :label="__('Staff invited')" :options="$users" :value="$selectedParticipants" multiple size="8"
                    :help="__('Hold Ctrl (or Cmd) to select several people. Newly invited staff are notified.')" />
                @error('participants.*')<div class="field-error">{{ $message }}</div>@enderror
            </fieldset>

            <fieldset>
                <legend>{{ __('External guests') }}</legend>
                <div data-repeater data-repeater-next="{{ count($guestRows) }}">
                    <div data-repeater-rows>
                        @foreach ($guestRows as $i => $guest)
                            <div class="repeater-row" data-repeater-row style="grid-template-columns:minmax(0,2fr) minmax(0,2fr) minmax(0,2fr) auto">
                                <input type="hidden" name="guests[{{ $i }}][id]" value="{{ $guest['id'] ?? '' }}">
                                <x-form.input name="guests[{{ $i }}][name]" :label="__('Name')" :value="$guest['name'] ?? ''" maxlength="255" />
                                <x-form.input name="guests[{{ $i }}][organization]" :label="__('Organization')" :value="$guest['organization'] ?? ''" maxlength="255" />
                                <x-form.input name="guests[{{ $i }}][email]" type="email" :label="__('Email')" :value="$guest['email'] ?? ''" maxlength="255" />
                                <div class="field"><button type="button" class="btn btn-sm btn-ghost" data-repeater-remove aria-label="{{ __('Remove guest') }}"><x-icon name="x" /></button></div>
                            </div>
                        @endforeach
                    </div>
                    <template data-repeater-template>
                        <div class="repeater-row" data-repeater-row style="grid-template-columns:minmax(0,2fr) minmax(0,2fr) minmax(0,2fr) auto">
                            <input type="hidden" name="guests[__INDEX__][id]" value="">
                            <div class="field"><label for="g___INDEX___name">{{ __('Name') }}</label><input class="input" id="g___INDEX___name" name="guests[__INDEX__][name]" maxlength="255"></div>
                            <div class="field"><label for="g___INDEX___org">{{ __('Organization') }}</label><input class="input" id="g___INDEX___org" name="guests[__INDEX__][organization]" maxlength="255"></div>
                            <div class="field"><label for="g___INDEX___email">{{ __('Email') }}</label><input class="input" type="email" id="g___INDEX___email" name="guests[__INDEX__][email]" maxlength="255"></div>
                            <div class="field"><button type="button" class="btn btn-sm btn-ghost" data-repeater-remove aria-label="{{ __('Remove guest') }}"><x-icon name="x" /></button></div>
                        </div>
                    </template>
                    <button type="button" class="btn btn-sm" data-repeater-add><x-icon name="plus" /> {{ __('Add guest') }}</button>
                </div>
            </fieldset>
        </div>
        <div class="card__foot form-actions">
            <button class="btn btn-primary" type="submit">{{ $meeting->exists ? __('Save') : __('Schedule meeting') }}</button>
            <a href="{{ $meeting->exists ? route('meetings.show', $meeting) : route('meetings.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
