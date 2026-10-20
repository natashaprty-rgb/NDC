<x-layouts.app :title="__('Staff directory')">
    <x-page-header :title="__('Staff directory')" :subtitle="__('Generated :date · :count staff', ['date' => fmt_date(now(), true), 'count' => $total])" :breadcrumbs="[__('Staff') => route('staff.index')]">
        <x-slot:actions>
            <button type="button" class="btn btn-primary" data-print><x-icon name="printer" /> {{ __('Print') }}</button>
        </x-slot:actions>
    </x-page-header>

    <div class="card no-print" style="margin-bottom:1rem">
        <x-filters>
            <x-search-field />
            <x-filter-select name="department" :label="__('Department')" :options="$departments" />
            <x-filter-select name="status" :label="__('Employment status')" :options="\App\Support\Status::options('employment')" />
            <x-filter-select name="type" :label="__('Employment type')" :options="\App\Support\Status::options('employment_type')" />
        </x-filters>
    </div>

    @forelse ($groups as $members)
        @php $dept = $members->first()->department; @endphp
        <div class="card" style="margin-bottom:1rem">
            <div class="card__head">
                <h2>{{ $dept?->displayName() ?? __('No department') }}</h2>
                <span class="muted small">
                    @if ($dept){{ __('Head') }}: {{ $dept->head?->name ?? '—' }} · @endif
                    {{ trans_choice(':count staff member|:count staff members', $members->count()) }}
                </span>
            </div>
            <div class="table-wrap"><table class="table">
                <thead><tr>
                    <th>{{ __('Staff number') }}</th><th>{{ __('Name') }}</th><th>{{ __('Job title') }}</th>
                    <th>{{ __('E-mail') }}</th><th>{{ __('Phone') }}</th><th>{{ __('Status') }}</th>
                </tr></thead>
                <tbody>
                @foreach ($members as $member)
                    <tr>
                        <td class="mono nowrap">{{ $member->staff_number }}</td>
                        <td>{{ $member->full_name }}</td>
                        <td>{{ $member->job_title ?? '—' }}</td>
                        <td>{{ $member->email ?? '—' }}</td>
                        <td class="nowrap">{{ $member->phone ?? '—' }}</td>
                        <td>{{ \App\Support\Status::label('employment', $member->employment_status) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </div>
    @empty
        <div class="card"><x-empty icon="users" /></div>
    @endforelse
</x-layouts.app>
