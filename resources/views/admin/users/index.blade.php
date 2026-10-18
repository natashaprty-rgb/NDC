<x-layout :title="__('users.title')" :breadcrumbs="[__('users.title')]">
    <x-slot:actions>
        @can('create', \App\Models\User::class)
            <a href="{{ route('admin.users.create') }}" class="btn btn--primary"><x-icon name="plus" width="16" height="16" /> {{ __('users.new') }}</a>
        @endcan
    </x-slot:actions>

    <section class="card">
        <x-filter-bar :action="route('admin.users.index')">
            <x-field.input name="q" :label="__('common.search')" :value="request('q')" class="field--search" :placeholder="__('users.search_hint')" />
            <x-field.select name="status" :label="__('common.status')" :options="enum_options(\App\Models\User::STATUSES)" :value="request('status')" :placeholder="__('common.all')" />
            <x-field.select name="role" :label="__('users.role')" :options="$roles" :value="request('role')" :placeholder="__('common.all')" />
        </x-filter-bar>

        @if($users->isEmpty())
            <x-empty />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr>
                        <th>{{ __('common.name') }}</th><th>{{ __('auth.email') }}</th><th>{{ __('users.roles') }}</th>
                        <th>{{ __('common.status') }}</th><th>{{ __('users.mfa') }}</th><th>{{ __('users.last_login') }}</th>
                    </tr></thead>
                    <tbody>
                    @foreach($users as $u)
                        <tr>
                            <td><a href="{{ route('admin.users.show', $u) }}">{{ $u->name }}</a></td>
                            <td>{{ $u->email }}</td>
                            <td class="small">{{ $u->roles->pluck('name')->join(', ') ?: '—' }}</td>
                            <td><x-badge :value="$u->status" /></td>
                            <td>{{ $u->hasMfaEnabled() ? __('common.yes') : __('common.no') }}</td>
                            <td class="small nowrap">{{ fmt_datetime($u->last_login_at) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $users->links() }}
        @endif
    </section>
</x-layout>
