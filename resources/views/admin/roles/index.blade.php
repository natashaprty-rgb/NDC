<x-layout :title="__('roles.title')" :breadcrumbs="[__('roles.title')]">
    <x-slot:actions>
        @can('create', \App\Models\Role::class)
            <a href="{{ route('admin.roles.create') }}" class="btn btn--primary"><x-icon name="plus" width="16" height="16" /> {{ __('roles.new') }}</a>
        @endcan
    </x-slot:actions>
    <x-card :flush="true">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>{{ __('roles.name') }}</th><th>{{ __('common.description') }}</th><th class="num">{{ __('roles.permissions') }}</th><th class="num">{{ __('roles.users') }}</th><th>{{ __('common.type') }}</th></tr></thead>
                <tbody>
                @foreach($roles as $role)
                    <tr>
                        <td><a href="{{ route('admin.roles.show', $role) }}">{{ $role->name }}</a></td>
                        <td class="small">{{ $role->description ?? '—' }}</td>
                        <td class="num">{{ $role->permissions_count }}</td>
                        <td class="num">{{ $role->users_count }}</td>
                        <td>{{ $role->is_system ? __('roles.system') : __('roles.custom') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
</x-layout>
