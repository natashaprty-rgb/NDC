<x-layouts.app :title="__('Users')">
    <x-page-header :title="__('Users')" :subtitle="__('System accounts, roles and access status.')">
        <x-slot:actions>
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary"><x-icon name="plus" /> {{ __('New user') }}</a>
        </x-slot:actions>
    </x-page-header>
    <div class="card">
        <x-filters>
            <x-search-field :placeholder="__('Name or e-mail')" />
            <x-filter-select name="role" :label="__('Role')" :options="$roles" />
            <x-filter-select name="department" :label="__('Department')" :options="$departments" />
            <x-filter-select name="status" :label="__('Status')" :options="\App\Support\Status::options('user')" />
        </x-filters>
        @if ($users->isEmpty())
            <x-empty icon="users" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr>
                    <th><x-sort-link column="name" :label="__('Name')" /></th>
                    <th>{{ __('Roles') }}</th>
                    <th>{{ __('Department') }}</th>
                    <th><x-sort-link column="status" :label="__('Status')" /></th>
                    <th><x-sort-link column="last_login_at" :label="__('Last sign-in')" /></th>
                </tr></thead>
                <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td class="title-cell"><a href="{{ route('admin.users.show', $user) }}">{{ $user->name }}</a><span class="sub">{{ $user->email }}</span></td>
                        <td>{{ $user->roles->pluck('name')->map(fn ($r) => __($r))->join(', ') ?: '—' }}</td>
                        <td>{{ $user->department?->displayName() ?? '—' }}</td>
                        <td><x-status domain="user" :value="$user->status" /> @if ($user->hasTwoFactorEnabled())<span class="badge badge--plain badge--info" title="{{ __('Two-factor enabled') }}">2FA</span>@endif</td>
                        <td class="nowrap">{{ fmt_date($user->last_login_at, true) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
        {{ $users->links() }}
    </div>
</x-layouts.app>
