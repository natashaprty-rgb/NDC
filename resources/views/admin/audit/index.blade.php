<x-layouts.app :title="__('Audit Log')">
    <x-page-header :title="__('Audit Log')" :subtitle="__('Sign-ins, administrative actions and changes to records.')">
        <x-slot:actions>
            @can('reports.export')
                <a href="{{ route('admin.audit.export', request()->query()) }}" class="btn"><x-icon name="download" /> {{ __('Export CSV') }}</a>
            @endcan
        </x-slot:actions>
    </x-page-header>
    <div class="card">
        <x-filters>
            <x-search-field :placeholder="__('Description or IP')" />
            <x-filter-select name="user" :label="__('User')" :options="$users" />
            <x-filter-select name="event" :label="__('Event')" :options="$events" />
            <x-filter-select name="type" :label="__('Record type')" :options="$types" />
            <div class="field"><label for="f_from">{{ __('From') }}</label><input type="date" id="f_from" name="from" value="{{ request('from') }}" class="input"></div>
            <div class="field"><label for="f_to">{{ __('To') }}</label><input type="date" id="f_to" name="to" value="{{ request('to') }}" class="input"></div>
        </x-filters>
        @if ($logs->isEmpty())
            <x-empty icon="history" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('User') }}</th><th>{{ __('Event') }}</th><th>{{ __('Record') }}</th><th>{{ __('Description') }}</th><th>{{ __('IP address') }}</th></tr></thead>
                <tbody>
                @foreach ($logs as $log)
                    <tr>
                        <td class="nowrap"><a href="{{ route('admin.audit.show', $log) }}">{{ fmt_date($log->created_at, true) }}</a></td>
                        <td>{{ $log->user?->name ?? __('System') }}</td>
                        <td><span @class(['badge', 'badge--plain', 'badge--danger' => in_array($log->event, ['login_failed', 'two_factor_failed', 'login_blocked', 'deleted']), 'badge--success' => $log->event === 'login', 'badge--info' => in_array($log->event, ['created', 'updated'])])>{{ $log->event }}</span></td>
                        <td class="nowrap">{{ $log->subjectLabel() }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($log->description, 80) }}</td>
                        <td class="mono small">{{ $log->ip_address }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
        {{ $logs->links() }}
    </div>
</x-layouts.app>
