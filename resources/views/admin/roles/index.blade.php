<x-layouts.app :title="__('Roles & Permissions')">
    <x-page-header :title="__('Roles & Permissions')" :subtitle="__('Roles group permissions. Adjust them to match NDC\'s approved organisational structure.')">
        <x-slot:actions><a href="{{ route('admin.roles.create') }}" class="btn btn-primary"><x-icon name="plus" /> {{ __('New role') }}</a></x-slot:actions>
    </x-page-header>
    <div class="card">
        <div class="table-wrap"><table class="table">
            <thead><tr><th>{{ __('Role') }}</th><th class="num">{{ __('Users') }}</th><th class="num">{{ __('Permissions') }}</th><th></th></tr></thead>
            <tbody>
            @foreach ($roles as $role)
                <tr>
                    <td class="title-cell">{{ __($role->name) }}</td>
                    <td class="num"><a href="{{ route('admin.users.index', ['role' => $role->name]) }}">{{ $role->users_count }}</a></td>
                    <td class="num">{{ $role->name === config('ndc.super_admin_role') ? __('All') : $role->permissions_count }}</td>
                    <td class="actions">
                        @if ($role->name !== config('ndc.super_admin_role'))
                            <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-sm">{{ __('Edit permissions') }}</a>
                            @if ($role->users_count === 0)
                                <x-delete-button :action="route('admin.roles.destroy', $role)" />
                            @endif
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </div>
</x-layouts.app>
