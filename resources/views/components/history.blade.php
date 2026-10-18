{{-- Audit history of a record (newest first). --}}
@props(['model', 'limit' => 25])
@php $logs = $model->auditLogs()->with('user')->limit($limit)->get(); @endphp
<x-card :title="__('common.history')">
    @if($logs->isEmpty())
        <p class="muted mb-0">{{ __('common.no_history') }}</p>
    @else
        <ul class="timeline">
            @foreach($logs as $log)
                <li>
                    <strong>{{ status_label($log->event) }}</strong>
                    <span class="muted">— {{ $log->user?->name ?? __('common.system') }}</span>
                    <div class="when">{{ fmt_datetime($log->created_at) }}</div>
                    @if($log->description)<div class="small">{{ $log->description }}</div>@endif
                    @if($log->event === 'updated' && $log->new_values)
                        <ul class="changes">
                            @foreach(array_slice($log->new_values, 0, 8, true) as $field => $new)
                                <li>{{ __('fields.'.$field) !== 'fields.'.$field ? __('fields.'.$field) : str_replace('_', ' ', $field) }}:
                                    {{ \Illuminate\Support\Str::limit(is_scalar($log->old_values[$field] ?? null) ? (string) $log->old_values[$field] : '—', 40) }}
                                    &rarr; {{ \Illuminate\Support\Str::limit(is_scalar($new) ? (string) $new : json_encode($new), 40) }}</li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
