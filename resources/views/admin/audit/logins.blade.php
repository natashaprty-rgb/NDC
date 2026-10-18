<x-layout :title="__('audit.title')" :subtitle="__('audit.logins_subtitle')" :breadcrumbs="[__('audit.title') => route('admin.audit.index'), __('audit.logins')]">
    @include('admin.audit._tabs')
    <section class="card">
        <x-filter-bar :action="route('admin.audit.logins')">
            <x-field.input name="email" :label="__('auth.email')" :value="request('email')" class="field--search" />
            <x-field.select name="event" :label="__('audit.event')" :options="$events" :value="request('event')" :placeholder="__('common.all')" />
            <x-field.input name="from" type="date" :label="__('common.from')" :value="request('from')" />
            <x-field.input name="to" type="date" :label="__('common.to')" :value="request('to')" />
        </x-filter-bar>
        @if($activities->isEmpty())
            <x-empty icon="shield" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>{{ __('common.date') }}</th><th>{{ __('audit.event') }}</th><th>{{ __('auth.email') }}</th><th>{{ __('audit.user') }}</th><th>{{ __('users.ip') }}</th><th>{{ __('audit.user_agent') }}</th></tr></thead>
                    <tbody>
                    @foreach($activities as $a)
                        <tr>
                            <td class="small nowrap">{{ fmt_datetime($a->created_at) }}</td>
                            <td><x-badge :value="$a->event" /></td>
                            <td>{{ $a->email ?? '—' }}</td>
                            <td>{{ $a->user?->name ?? '—' }}</td>
                            <td class="small">{{ $a->ip_address }}</td>
                            <td class="small muted">{{ \Illuminate\Support\Str::limit($a->user_agent, 60) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $activities->links() }}
        @endif
    </section>
</x-layout>
