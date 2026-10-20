<x-layouts.app :title="__('Audit entry')">
    <x-page-header :title="__('Audit entry #:id', ['id' => $log->id])" :breadcrumbs="[__('Audit Log') => route('admin.audit.index')]" />
    <div class="card">
        <div class="card__body">
            <dl class="dl">
                <dt>{{ __('Date') }}</dt><dd>{{ fmt_date($log->created_at, true) }}</dd>
                <dt>{{ __('User') }}</dt><dd>{{ $log->user?->name ?? __('System') }} @if($log->user)<span class="muted">({{ $log->user->email }})</span>@endif</dd>
                <dt>{{ __('Event') }}</dt><dd class="mono">{{ $log->event }}</dd>
                <dt>{{ __('Record') }}</dt><dd>{{ $log->subjectLabel() }}</dd>
                <dt>{{ __('Description') }}</dt><dd>{{ $log->description ?? '—' }}</dd>
                <dt>{{ __('IP address') }}</dt><dd class="mono">{{ $log->ip_address ?? '—' }}</dd>
                <dt>{{ __('Browser') }}</dt><dd class="small">{{ $log->user_agent ?? '—' }}</dd>
                <dt>{{ __('URL') }}</dt><dd class="small mono">{{ $log->url ?? '—' }}</dd>
            </dl>
        </div>
    </div>
    @if ($log->old_values || $log->new_values)
        <div class="card">
            <div class="card__head"><h2>{{ __('Changes') }}</h2></div>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>{{ __('Field') }}</th><th>{{ __('Before') }}</th><th>{{ __('After') }}</th></tr></thead>
                <tbody>
                @foreach (array_unique(array_merge(array_keys($log->old_values ?? []), array_keys($log->new_values ?? []))) as $field)
                    <tr>
                        <td class="mono">{{ $field }}</td>
                        <td class="small">{{ is_scalar($log->old_values[$field] ?? null) ? $log->old_values[$field] : json_encode($log->old_values[$field] ?? null) }}</td>
                        <td class="small">{{ is_scalar($log->new_values[$field] ?? null) ? $log->new_values[$field] : json_encode($log->new_values[$field] ?? null) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </div>
    @endif
</x-layouts.app>
