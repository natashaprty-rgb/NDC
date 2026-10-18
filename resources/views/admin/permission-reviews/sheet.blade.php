<x-layout :title="__('audit.review_sheet')" :subtitle="__('audit.sheet_subtitle')"
          :breadcrumbs="[__('audit.reviews_title') => route('admin.permission-reviews.index'), __('audit.review_sheet')]">
    <x-slot:actions>
        <a href="{{ route('admin.permission-reviews.export') }}" class="btn"><x-icon name="download" width="16" height="16" /> {{ __('common.export_csv') }}</a>
        <a href="{{ route('admin.permission-reviews.create') }}" class="btn btn--primary">{{ __('audit.record_review') }}</a>
    </x-slot:actions>
    <x-card :flush="true">
        @if($rows->isEmpty())
            <x-empty />
        @else
        <div class="table-wrap"><table class="table">
            <thead><tr><th>{{ __('common.name') }}</th><th>{{ __('common.status') }}</th><th>{{ __('users.roles') }}</th><th class="num">{{ __('audit.permission_count') }}</th><th>{{ __('users.last_login') }}</th><th>{{ __('users.mfa') }}</th><th>{{ __('audit.flags') }}</th></tr></thead>
            <tbody>@foreach($rows as $row)
                <tr>
                    <td><a href="{{ route('admin.users.show', $row['user']) }}">{{ $row['user']->name }}</a><br><span class="small muted">{{ $row['user']->email }}</span></td>
                    <td><x-badge :value="$row['user']->status" /></td>
                    <td class="small">{{ $row['roles'] ?: '—' }}</td>
                    <td class="num">{{ $row['permission_count'] }}</td>
                    <td class="small nowrap">{{ fmt_datetime($row['user']->last_login_at) }}</td>
                    <td>{{ $row['user']->hasMfaEnabled() ? __('common.yes') : __('common.no') }}</td>
                    <td>@foreach($row['flags'] as $flag)<span class="badge badge--{{ $flag === 'no_mfa' ? 'neutral' : 'warning' }}">{{ __('audit.flag.'.$flag) }}</span> @endforeach</td>
                </tr>
            @endforeach</tbody>
        </table></div>
        @endif
    </x-card>
</x-layout>
