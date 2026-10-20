{{-- Status of an action point, with a quick update form for the assignee or meeting administrators. --}}
@can('updateStatus', $item)
    <form method="POST" action="{{ route('meetings.action-items.status', $item) }}" class="inline-form" style="display:flex;gap:.35rem;align-items:center">
        @csrf @method('PATCH')
        <label class="sr-only" for="ais_{{ $item->id }}">{{ __('Status') }}</label>
        <select id="ais_{{ $item->id }}" name="status" class="select" style="min-width:8rem">
            @foreach (\App\Support\Status::options('action_item') as $value => $label)
                <option value="{{ $value }}" @selected($item->status === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-sm">{{ __('Update') }}</button>
    </form>
    @if ($item->completed_at)<div class="small muted">{{ __('Completed :date', ['date' => fmt_date($item->completed_at)]) }}</div>@endif
@else
    <x-status domain="action_item" :value="$item->status" />
    @if ($item->completed_at)<div class="small muted">{{ __('Completed :date', ['date' => fmt_date($item->completed_at)]) }}</div>@endif
@endcan
