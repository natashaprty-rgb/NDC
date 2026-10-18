<x-layout :title="$member->full_name" :subtitle="trim(($member->job_title ?? '').' · '.$member->staff_number, ' ·')"
          :breadcrumbs="[__('staff.title') => route('staff.index'), $member->full_name]">
    <x-slot:actions>
        @can('update', $member)
            <a href="{{ route('staff.edit', $member) }}" class="btn"><x-icon name="edit" width="16" height="16" /> {{ __('common.edit') }}</a>
        @endcan
        @can('delete', $member)
            <x-delete-button :action="route('staff.destroy', $member)" :label="__('common.archive')" :message="__('common.confirm_archive')" class="btn btn--danger" />
        @endcan
    </x-slot:actions>

    <div class="layout-main-side">
        <div>
            <x-card :title="__('staff.open_tasks')" :flush="true">
                @if($tasks->isEmpty())
                    <x-empty :message="__('staff.no_tasks')" icon="list" />
                @else
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>{{ __('common.reference') }}</th><th>{{ __('common.title') }}</th><th>{{ __('common.status') }}</th><th>{{ __('common.due') }}</th></tr></thead>
                        <tbody>@foreach($tasks as $task)
                            <tr class="{{ $task->isOverdue() ? 'row-overdue' : '' }}">
                                <td class="nowrap">{{ $task->reference }}</td>
                                <td>@if(route_if('tasks.show', $task))<a href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a>@else{{ $task->title }}@endif<br><span class="small muted">{{ $task->project?->name }}</span></td>
                                <td><x-badge :value="$task->status" /></td>
                                <td class="nowrap">{{ fmt_date($task->due_date) }}</td>
                            </tr>
                        @endforeach</tbody>
                    </table></div>
                @endif
            </x-card>

            <x-card :title="__('staff.assigned_assets')" :flush="true">
                @if($assets->isEmpty())
                    <x-empty :message="__('staff.no_assets')" icon="box" />
                @else
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>{{ __('staff.asset_tag') }}</th><th>{{ __('common.name') }}</th><th>{{ __('common.category') }}</th><th>{{ __('common.status') }}</th></tr></thead>
                        <tbody>@foreach($assets as $asset)
                            <tr>
                                <td class="nowrap">{{ $asset->asset_tag }}</td>
                                <td>@if(route_if('assets.show', $asset))<a href="{{ route('assets.show', $asset) }}">{{ $asset->name }}</a>@else{{ $asset->name }}@endif</td>
                                <td>{{ $asset->category?->displayName() }}</td>
                                <td><x-badge :value="$asset->status" /></td>
                            </tr>
                        @endforeach</tbody>
                    </table></div>
                @endif
            </x-card>
        </div>
        <div>
            <x-card :title="__('staff.official_details')">
                <dl class="dl">
                    <dt>{{ __('staff.staff_number') }}</dt><dd>{{ $member->staff_number }}</dd>
                    <dt>{{ __('staff.job_title') }}</dt><dd>{{ $member->job_title ?? '—' }}</dd>
                    <dt>{{ __('common.department') }}</dt><dd>{{ $member->department?->displayName() ?? '—' }}</dd>
                    <dt>{{ __('staff.official_email') }}</dt><dd>{{ $member->official_email ?? '—' }}</dd>
                    <dt>{{ __('staff.official_phone') }}</dt><dd>{{ $member->official_phone ?? '—' }}</dd>
                    <dt>{{ __('staff.office_location') }}</dt><dd>{{ $member->office_location ?? '—' }}</dd>
                    <dt>{{ __('staff.user_account') }}</dt><dd>{{ $member->user ? $member->user->email : __('common.none') }}</dd>
                </dl>
            </x-card>
            @if($canSensitive)
                <x-card :title="__('staff.employment_details')">
                    <dl class="dl">
                        <dt>{{ __('staff.employment_status') }}</dt><dd><x-badge :value="$member->employment_status" /></dd>
                        <dt>{{ __('staff.employment_type') }}</dt><dd>{{ status_label($member->employment_type) }}</dd>
                        <dt>{{ __('staff.start_date') }}</dt><dd>{{ fmt_date($member->start_date) }}</dd>
                        <dt>{{ __('staff.end_date') }}</dt><dd>{{ fmt_date($member->end_date) }}</dd>
                    </dl>
                    @if($member->notes)<p class="prose mt-2 mb-0">{{ $member->notes }}</p>@endif
                </x-card>
                <x-history :model="$member" />
            @endif
        </div>
    </div>
</x-layout>
