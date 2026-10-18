<x-layout :title="$department->displayName()" :subtitle="$department->code"
          :breadcrumbs="[__('departments.title') => route('departments.index'), $department->name]">
    <x-slot:actions>
        @can('update', $department)
            <a href="{{ route('departments.edit', $department) }}" class="btn"><x-icon name="edit" width="16" height="16" /> {{ __('common.edit') }}</a>
        @endcan
        @can('departments.manage')
            <x-delete-button :action="route('departments.destroy', $department)" :label="__('common.archive')" :message="__('common.confirm_archive')" class="btn btn--danger" />
        @endcan
    </x-slot:actions>

    <div class="layout-main-side">
        <div>
            <x-card :title="__('departments.staff')" :flush="true">
                @if($staff->isEmpty())
                    <x-empty :message="__('departments.no_staff')" icon="users" />
                @else
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>{{ __('common.name') }}</th><th>{{ __('departments.job_title') }}</th><th>{{ __('departments.email') }}</th></tr></thead>
                        <tbody>@foreach($staff as $member)
                            <tr>
                                <td>@if(route_if('staff.show', $member))<a href="{{ route('staff.show', $member) }}">{{ $member->full_name }}</a>@else{{ $member->full_name }}@endif</td>
                                <td>{{ $member->job_title ?? '—' }}</td>
                                <td>{{ $member->official_email ?? '—' }}</td>
                            </tr>
                        @endforeach</tbody>
                    </table></div>
                    {{ $staff->links() }}
                @endif
            </x-card>
        </div>
        <div>
            <x-card :title="__('common.details')">
                <dl class="dl">
                    <dt>{{ __('common.status') }}</dt><dd><x-badge :value="$department->is_active ? 'active' : 'inactive'" /></dd>
                    <dt>{{ __('departments.parent') }}</dt><dd>{{ $department->parent?->displayName() ?? '—' }}</dd>
                    <dt>{{ __('departments.head') }}</dt><dd>{{ $department->head?->name ?? '—' }}</dd>
                    <dt>{{ __('departments.email') }}</dt><dd>{{ $department->email ?? '—' }}</dd>
                    <dt>{{ __('departments.phone') }}</dt><dd>{{ $department->phone ?? '—' }}</dd>
                    <dt>{{ __('departments.units') }}</dt><dd>{{ $department->children->map->displayName()->join(', ') ?: '—' }}</dd>
                </dl>
                @if($department->description)<p class="prose mt-2 mb-0">{{ $department->description }}</p>@endif
            </x-card>
            <x-history :model="$department" />
        </div>
    </div>
</x-layout>
