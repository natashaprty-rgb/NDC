<x-layout :title="$role->name" :subtitle="$role->description"
          :breadcrumbs="[__('roles.title') => route('admin.roles.index'), $role->name]">
    <x-slot:actions>
        @can('update', $role)
            <a href="{{ route('admin.roles.edit', $role) }}" class="btn"><x-icon name="edit" width="16" height="16" /> {{ __('roles.edit_permissions') }}</a>
        @endcan
        @can('delete', $role)
            <x-delete-button :action="route('admin.roles.destroy', $role)" class="btn btn--danger" />
        @endcan
    </x-slot:actions>

    @if($role->slug === 'super-admin')
        <div class="alert alert--info">{{ __('roles.super_admin_note') }}</div>
    @endif

    <div class="layout-main-side">
        <x-card :title="__('roles.permissions')">
            @include('admin.roles._matrix', ['editable' => false])
        </x-card>
        <div>
            <x-card :title="__('roles.users').' ('.$users->count().')'" :flush="true">
                @if($users->isEmpty())
                    <x-empty :message="__('roles.no_users')" icon="users" />
                @else
                    <div class="table-wrap"><table class="table"><tbody>
                        @foreach($users as $u)
                            <tr><td>@can('view', $u)<a href="{{ route('admin.users.show', $u) }}">{{ $u->name }}</a>@else{{ $u->name }}@endcan</td><td><x-badge :value="$u->status" /></td></tr>
                        @endforeach
                    </tbody></table></div>
                @endif
            </x-card>
            <x-history :model="$role" />
        </div>
    </div>
</x-layout>
