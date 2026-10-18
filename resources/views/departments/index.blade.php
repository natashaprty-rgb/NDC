<x-layout :title="__('departments.title')" :breadcrumbs="[__('departments.title')]">
    <x-slot:actions>
        @can('create', \App\Models\Department::class)
            <a href="{{ route('departments.create') }}" class="btn btn--primary"><x-icon name="plus" width="16" height="16" /> {{ __('departments.new') }}</a>
        @endcan
    </x-slot:actions>

    <section class="card">
        <x-filter-bar :action="route('departments.index')">
            <x-field.input name="q" :label="__('common.search')" :value="request('q')" class="field--search" :placeholder="__('departments.search_hint')" />
            <x-field.select name="active" :label="__('common.status')" :options="['1' => status_label('active'), '0' => status_label('inactive')]" :value="request('active')" :placeholder="__('common.all')" />
        </x-filter-bar>

        @if($departments->isEmpty())
            <x-empty :message="request()->hasAny(['q', 'active']) ? __('common.no_records') : __('departments.empty')" icon="building" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr>
                        <th>{{ __('departments.code') }}</th><th>{{ __('departments.name') }}</th><th>{{ __('departments.parent') }}</th>
                        <th>{{ __('departments.head') }}</th><th class="num">{{ __('departments.staff_count') }}</th><th>{{ __('common.status') }}</th>
                    </tr></thead>
                    <tbody>
                    @foreach($departments as $department)
                        <tr>
                            <td class="nowrap">{{ $department->code }}</td>
                            <td><a href="{{ route('departments.show', $department) }}">{{ $department->displayName() }}</a></td>
                            <td>{{ $department->parent?->displayName() ?? '—' }}</td>
                            <td>{{ $department->head?->name ?? '—' }}</td>
                            <td class="num">{{ $department->staff_count }}</td>
                            <td><x-badge :value="$department->is_active ? 'active' : 'inactive'" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $departments->links() }}
        @endif
    </section>
</x-layout>
