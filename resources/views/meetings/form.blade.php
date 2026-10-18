@php
    $editing = $meeting->exists;
    $selected = collect(old('participant_ids', $editing ? $meeting->participants->whereNotNull('user_id')->pluck('user_id')->all() : []))->map(fn ($v) => (int) $v)->all();
    $externals = old('externals', $editing ? $meeting->participants->whereNull('user_id')->map(fn ($p) => ['name' => $p->external_name, 'organization' => $p->external_organization, 'email' => $p->external_email])->values()->all() : []);
    if ($externals === []) { $externals = [['name' => '', 'organization' => '', 'email' => '']]; }
@endphp
<x-layout :title="$editing ? __('meetings.edit') : __('meetings.new')"
          :breadcrumbs="[__('meetings.title') => route('meetings.index'), ($editing ? $meeting->title : __('meetings.new'))]">
    <x-card>
        <form method="POST" action="{{ $editing ? route('meetings.update', $meeting) : route('meetings.store') }}">
            @csrf
            @if($editing) @method('PUT') @endif
            <div class="form-grid">
                <x-field.input name="title" :label="__('meetings.meeting_title')" :value="$meeting->title" :required="true" maxlength="250" class="span-2" />
                <x-field.select name="meeting_type" :label="__('meetings.type')" :options="enum_options(\App\Models\Meeting::TYPES)" :value="$meeting->meeting_type" :required="true" :placeholder="false" />
                <x-field.select name="status" :label="__('common.status')" :options="enum_options(\App\Models\Meeting::STATUSES)" :value="$meeting->status" :required="true" :placeholder="false" />
                <x-field.input name="starts_at" type="datetime-local" :label="__('meetings.starts_at')" :value="$meeting->starts_at" :required="true" />
                <x-field.input name="ends_at" type="datetime-local" :label="__('meetings.ends_at')" :value="$meeting->ends_at" />
                <x-field.input name="location" :label="__('meetings.location')" :value="$meeting->location" maxlength="250" />
                <x-field.input name="online_link" type="url" :label="__('meetings.online_link')" :value="$meeting->online_link" maxlength="500" />
                <x-field.select name="organizer_id" :label="__('meetings.organizer')" :options="$users" :value="$meeting->organizer_id" />
                <x-field.select name="department_id" :label="__('common.department')" :options="$departments" :value="$meeting->department_id" :placeholder="__('common.none')" />
                <x-field.select name="project_id" :label="__('common.project')" :options="$projects" :value="$meeting->project_id" :placeholder="__('common.none')" />
                <x-field.textarea name="description" :label="__('common.description')" :value="$meeting->description" class="span-2" rows="3" />
                <x-field.textarea name="agenda" :label="__('meetings.agenda')" :value="$meeting->agenda" class="span-2" rows="6" />
            </div>

            <fieldset class="mt-2">
                <legend>{{ __('meetings.internal_participants') }}</legend>
                <div class="field">
                    <label for="f_participant_ids">{{ __('meetings.internal_participants') }}</label>
                    <select id="f_participant_ids" name="participant_ids[]" multiple size="8" class="input {{ $errors->has('participant_ids*') ? 'is-invalid' : '' }}">
                        @foreach($users as $id => $name)
                            <option value="{{ $id }}" @selected(in_array((int) $id, $selected, true))>{{ $name }}</option>
                        @endforeach
                    </select>
                    <span class="help">{{ __('meetings.internal_help') }}</span>
                    @error('participant_ids')<span class="error">{{ $message }}</span>@enderror
                </div>
            </fieldset>

            <fieldset data-repeater>
                <legend>{{ __('meetings.external_participants') }}</legend>
                <div data-repeater-list class="stack">
                    @foreach($externals as $i => $row)
                        <div class="form-grid" data-repeater-row style="grid-template-columns: 2fr 2fr 2fr auto; align-items:end">
                            <x-field.input name="externals[{{ $i }}][name]" :label="__('meetings.external_name')" :value="$row['name'] ?? ''" maxlength="150" />
                            <x-field.input name="externals[{{ $i }}][organization]" :label="__('meetings.external_organization')" :value="$row['organization'] ?? ''" maxlength="200" />
                            <x-field.input name="externals[{{ $i }}][email]" type="email" :label="__('meetings.external_email')" :value="$row['email'] ?? ''" />
                            <button type="button" class="btn btn--sm" data-repeater-remove>{{ __('common.remove') }}</button>
                        </div>
                    @endforeach
                </div>
                <template data-repeater-template>
                    <div class="form-grid" data-repeater-row style="grid-template-columns: 2fr 2fr 2fr auto; align-items:end">
                        <div class="field"><label for="ext___INDEX___name">{{ __('meetings.external_name') }}</label><input id="ext___INDEX___name" class="input" name="externals[__INDEX__][name]" maxlength="150"></div>
                        <div class="field"><label for="ext___INDEX___org">{{ __('meetings.external_organization') }}</label><input id="ext___INDEX___org" class="input" name="externals[__INDEX__][organization]" maxlength="200"></div>
                        <div class="field"><label for="ext___INDEX___email">{{ __('meetings.external_email') }}</label><input id="ext___INDEX___email" class="input" type="email" name="externals[__INDEX__][email]"></div>
                        <button type="button" class="btn btn--sm" data-repeater-remove>{{ __('common.remove') }}</button>
                    </div>
                </template>
                <button type="button" class="btn btn--sm mt-1" data-repeater-add><x-icon name="plus" width="14" height="14" /> {{ __('meetings.add_external') }}</button>
            </fieldset>

            <div class="form-actions">
                <a href="{{ $editing ? route('meetings.show', $meeting) : route('meetings.index') }}" class="btn">{{ __('common.cancel') }}</a>
                <button type="submit" class="btn btn--primary">{{ $editing ? __('common.save_changes') : __('common.create') }}</button>
            </div>
        </form>
    </x-card>
</x-layout>
