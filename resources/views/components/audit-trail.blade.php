@props(['model', 'limit' => 15])
{{-- Change history for a record, from the audit log. --}}
@php
    $entries = $model->auditLogs()->with('user')->limit($limit)->get();
@endphp
<div class="card" id="history">
    <div class="card__head"><h2>{{ __('History') }}</h2></div>
    <div class="card__body">
        @if ($entries->isEmpty())
            <p class="muted">{{ __('No history recorded.') }}</p>
        @else
            <ul class="timeline">
                @foreach ($entries as $entry)
                    <li>
                        <div><strong>{{ __(str($entry->event)->replace('_', ' ')->ucfirst()->toString()) }}</strong>
                            <span class="muted small">· {{ $entry->user?->name ?? __('System') }} · {{ fmt_date($entry->created_at, true) }}</span></div>
                        @if ($entry->event === 'updated' && $entry->new_values)
                            <div class="small muted">
                                @foreach ($entry->new_values as $field => $new)
                                    {{ __(str($field)->replace('_id', '')->replace('_', ' ')->ucfirst()->toString()) }}:
                                    <span>{{ is_scalar($entry->old_values[$field] ?? null) ? \Illuminate\Support\Str::limit((string) $entry->old_values[$field], 40) : '—' }}</span>
                                    → <span>{{ is_scalar($new) ? \Illuminate\Support\Str::limit((string) $new, 40) : '—' }}</span>@if (! $loop->last); @endif
                                @endforeach
                            </div>
                        @elseif ($entry->description && $entry->event !== 'created')
                            <div class="small muted">{{ $entry->description }}</div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
