<x-layouts.app :title="__('Departments')">
    <x-page-header :title="__('Departments')" :subtitle="__('Organisational units used across staff, projects, documents and budgets.')">
        <x-slot:actions><a href="{{ route('admin.departments.create') }}" class="btn btn-primary"><x-icon name="plus" /> {{ __('New department') }}</a></x-slot:actions>
    </x-page-header>
    <div class="card">
        <x-filters>
            <x-search-field />
            <x-filter-select name="status" :label="__('Status')" :options="['active' => __('Active'), 'inactive' => __('Inactive')]" />
        </x-filters>
        @if ($departments->isEmpty())
            <x-empty icon="building" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr>
                    <th><x-sort-link column="code" :label="__('Code')" /></th>
                    <th><x-sort-link column="name" :label="__('Name')" /></th>
                    <th>{{ __('Parent') }}</th><th>{{ __('Head') }}</th>
                    <th class="num">{{ __('Users') }}</th><th class="num">{{ __('Staff') }}</th><th>{{ __('Status') }}</th><th></th>
                </tr></thead>
                <tbody>
                @foreach ($departments as $department)
                    <tr>
                        <td class="mono">{{ $department->code }}</td>
                        <td class="title-cell">{{ $department->displayName() }}@if ($department->name_so && app()->getLocale() !== 'so')<span class="sub" lang="so">{{ $department->name_so }}</span>@endif</td>
                        <td>{{ $department->parent?->displayName() ?? '—' }}</td>
                        <td>{{ $department->head?->name ?? '—' }}</td>
                        <td class="num">{{ $department->users_count }}</td>
                        <td class="num">{{ $department->staff_count }}</td>
                        <td>@if ($department->is_active)<span class="badge badge--success">{{ __('Active') }}</span>@else<span class="badge">{{ __('Inactive') }}</span>@endif</td>
                        <td class="actions"><a href="{{ route('admin.departments.edit', $department) }}" class="btn btn-sm">{{ __('Edit') }}</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
        {{ $departments->links() }}
    </div>
</x-layouts.app>
