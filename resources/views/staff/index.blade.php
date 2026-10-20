<x-layouts.app :title="__('Staff')">
    <x-page-header :title="__('Staff')" :subtitle="__('Staff directory and department records.')">
        <x-slot:actions>
            <a href="{{ route('staff.directory', request()->only(['q', 'department', 'status', 'type'])) }}" class="btn"><x-icon name="printer" /> {{ __('Printable directory') }}</a>
            @can('create', \App\Models\Staff::class)
                <a href="{{ route('staff.create') }}" class="btn btn-primary"><x-icon name="plus" /> {{ __('New staff member') }}</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if ($department)
        <div class="card" style="margin-bottom:1rem">
            <div class="card__body">
                <dl class="dl">
                    <dt>{{ __('Department') }}</dt><dd><strong>{{ $department->displayName() }}</strong> <span class="muted mono small">{{ $department->code }}</span></dd>
                    <dt>{{ __('Head of department') }}</dt><dd>{{ $department->head?->name ?? '—' }}</dd>
                    <dt>{{ __('Staff records') }}</dt><dd>{{ $department->staff_count }}</dd>
                    @if ($department->email || $department->phone)
                        <dt>{{ __('Contact') }}</dt><dd>{{ collect([$department->email, $department->phone])->filter()->join(' · ') }}</dd>
                    @endif
                </dl>
            </div>
        </div>
    @endif

    <div class="card">
        <x-filters>
            <x-search-field :placeholder="__('Name, staff number, job title or e-mail')" />
            <x-filter-select name="department" :label="__('Department')" :options="$departments" />
            <x-filter-select name="status" :label="__('Employment status')" :options="\App\Support\Status::options('employment')" />
            <x-filter-select name="type" :label="__('Employment type')" :options="\App\Support\Status::options('employment_type')" />
        </x-filters>
        @if ($staff->isEmpty())
            <x-empty icon="users" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr>
                    <th><x-sort-link column="staff_number" :label="__('Staff number')" /></th>
                    <th><x-sort-link column="full_name" :label="__('Name')" /></th>
                    <th>{{ __('Department') }}</th>
                    <th>{{ __('Contact') }}</th>
                    <th><x-sort-link column="employment_type" :label="__('Type')" /></th>
                    <th><x-sort-link column="employment_status" :label="__('Status')" /></th>
                    <th><x-sort-link column="hire_date" :label="__('Hire date')" /></th>
                </tr></thead>
                <tbody>
                @foreach ($staff as $member)
                    <tr>
                        <td class="mono nowrap">{{ $member->staff_number }}</td>
                        <td class="title-cell"><a href="{{ route('staff.show', $member) }}">{{ $member->full_name }}</a><span class="sub">{{ $member->job_title ?? '—' }}</span></td>
                        <td>{{ $member->department?->displayName() ?? '—' }}</td>
                        <td>@if ($member->email)<a href="mailto:{{ $member->email }}">{{ $member->email }}</a>@endif<span class="sub">{{ $member->phone }}</span></td>
                        <td>{{ \App\Support\Status::label('employment_type', $member->employment_type) }}</td>
                        <td><x-status domain="employment" :value="$member->employment_status" /></td>
                        <td class="nowrap">{{ fmt_date($member->hire_date) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
        {{ $staff->links() }}
    </div>
</x-layouts.app>
