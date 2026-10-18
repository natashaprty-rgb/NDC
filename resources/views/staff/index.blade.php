<x-layout :title="__('staff.title')" :breadcrumbs="[__('staff.title')]">
    <x-slot:actions>
        @can('create', \App\Models\Staff::class)
            <a href="{{ route('staff.create') }}" class="btn btn--primary"><x-icon name="plus" width="16" height="16" /> {{ __('staff.new') }}</a>
        @endcan
    </x-slot:actions>

    <section class="card">
        <x-filter-bar :action="route('staff.index')">
            <x-field.input name="q" :label="__('common.search')" :value="request('q')" class="field--search" :placeholder="__('staff.search_hint')" />
            <x-field.select name="department" :label="__('common.department')" :options="$departments" :value="request('department')" :placeholder="__('common.all')" />
            @if($sensitive)
                <x-field.select name="status" :label="__('staff.employment_status')" :options="enum_options(\App\Models\Staff::EMPLOYMENT_STATUSES)" :value="request('status')" :placeholder="__('common.all')" />
            @endif
        </x-filter-bar>

        @if($staff->isEmpty())
            <x-empty :message="request()->hasAny(['q', 'department', 'status']) ? __('common.no_records') : __('staff.empty')" icon="users" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr>
                        <th>{{ __('staff.staff_number') }}</th><th>{{ __('staff.full_name') }}</th><th>{{ __('staff.job_title') }}</th>
                        <th>{{ __('common.department') }}</th><th>{{ __('staff.official_email') }}</th><th>{{ __('staff.official_phone') }}</th>
                        @if($sensitive)<th>{{ __('staff.employment_status') }}</th>@endif
                    </tr></thead>
                    <tbody>
                    @foreach($staff as $member)
                        <tr>
                            <td class="nowrap">{{ $member->staff_number }}</td>
                            <td><a href="{{ route('staff.show', $member) }}">{{ $member->full_name }}</a></td>
                            <td>{{ $member->job_title ?? '—' }}</td>
                            <td>{{ $member->department?->displayName() ?? '—' }}</td>
                            <td>{{ $member->official_email ?? '—' }}</td>
                            <td class="nowrap">{{ $member->official_phone ?? '—' }}</td>
                            @if($sensitive)<td><x-badge :value="$member->employment_status" /></td>@endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $staff->links() }}
        @endif
    </section>
</x-layout>
