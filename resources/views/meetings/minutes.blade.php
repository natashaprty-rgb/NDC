<x-layout :title="__('meetings.record_minutes')" :subtitle="$meeting->title"
          :breadcrumbs="[__('meetings.title') => route('meetings.index'), $meeting->title => route('meetings.show', $meeting), __('meetings.record_minutes')]">
    <x-card>
        <form method="POST" action="{{ route('meetings.minutes.update', $meeting) }}">
            @csrf
            @method('PUT')
            <x-field.textarea name="minutes" :label="__('meetings.minutes')" :value="$meeting->minutes" rows="14" />

            @if($meeting->participants->isNotEmpty())
                <fieldset class="mt-2">
                    <legend>{{ __('meetings.attendance') }}</legend>
                    <div class="form-grid">
                        @foreach($meeting->participants as $p)
                            <x-field.select name="attendance[{{ $p->id }}]" :label="$p->displayName().($p->external_organization ? ' ('.$p->external_organization.')' : '')"
                                            :options="enum_options(\App\Models\MeetingParticipant::ATTENDANCE)" :value="$p->attendance" :placeholder="false" />
                        @endforeach
                    </div>
                </fieldset>
            @endif

            <x-field.checkbox name="mark_completed" :label="__('meetings.mark_completed')" :checked="$meeting->status === 'completed'" />

            <div class="form-actions">
                <a href="{{ route('meetings.show', $meeting) }}" class="btn">{{ __('common.cancel') }}</a>
                <button type="submit" class="btn btn--primary">{{ __('meetings.save_minutes') }}</button>
            </div>
        </form>
    </x-card>
</x-layout>
