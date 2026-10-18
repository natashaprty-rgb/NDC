<x-layout :title="__('audit.title')" :subtitle="__('audit.subtitle')" :breadcrumbs="[__('audit.title')]">
    @include('admin.audit._tabs')
    <section class="card">
        <x-filter-bar :action="route('admin.audit.index')">
            <x-field.input name="q" :label="__('common.search')" :value="request('q')" class="field--search" :placeholder="__('audit.search_hint')" />
            <x-field.select name="user" :label="__('audit.user')" :options="$users" :value="request('user')" :placeholder="__('common.all')" />
            <x-field.select name="event" :label="__('audit.event')" :options="$events" :value="request('event')" :placeholder="__('common.all')" />
            <x-field.select name="type" :label="__('audit.record_type')" :options="$types" :value="request('type')" :placeholder="__('common.all')" />
            <x-field.input name="from" type="date" :label="__('common.from')" :value="request('from')" />
            <x-field.input name="to" type="date" :label="__('common.to')" :value="request('to')" />
        </x-filter-bar>

        @if($logs->isEmpty())
            <x-empty icon="shield" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>{{ __('common.date') }}</th><th>{{ __('audit.user') }}</th><th>{{ __('audit.event') }}</th><th>{{ __('audit.record') }}</th><th>{{ __('audit.details') }}</th><th>{{ __('users.ip') }}</th></tr></thead>
                    <tbody>
                    @foreach($logs as $log)
                        <tr>
                            <td class="small nowrap">{{ fmt_datetime($log->created_at) }}</td>
                            <td>{{ $log->user?->name ?? __('common.system') }}</td>
                            <td><x-badge :value="$log->event" /></td>
                            <td class="small nowrap">{{ $log->auditable_type ? __('dashboard.entity.'.$log->auditable_type) : '—' }}{{ $log->auditable_id ? ' #'.$log->auditable_id : '' }}</td>
                            <td class="small">
                                {{ $log->description }}
                                @if($log->old_values || $log->new_values)
                                    <details>
                                        <summary>{{ __('audit.show_changes') }}</summary>
                                        <table class="table small">
                                            <thead><tr><th>{{ __('audit.field') }}</th><th>{{ __('audit.old') }}</th><th>{{ __('audit.new') }}</th></tr></thead>
                                            <tbody>
                                            @foreach(array_unique(array_merge(array_keys($log->old_values ?? []), array_keys($log->new_values ?? []))) as $field)
                                                @php
                                                    $o = $log->old_values[$field] ?? null; $n = $log->new_values[$field] ?? null;
                                                    $fmt = fn ($v) => $v === null ? '—' : \Illuminate\Support\Str::limit(is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE), 200);
                                                @endphp
                                                <tr><td>{{ $field }}</td><td>{{ $fmt($o) }}</td><td>{{ $fmt($n) }}</td></tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                    </details>
                                @endif
                            </td>
                            <td class="small">{{ $log->ip_address }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $logs->links() }}
        @endif
    </section>
</x-layout>
