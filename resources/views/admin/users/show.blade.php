<x-layout :title="$target->name" :subtitle="$target->email"
          :breadcrumbs="[__('users.title') => route('admin.users.index'), $target->name]">
    <x-slot:actions>
        @can('update', $target)
            <a href="{{ route('admin.users.edit', $target) }}" class="btn"><x-icon name="edit" width="16" height="16" /> {{ __('common.edit') }}</a>
        @endcan
        @can('resetPassword', $target)
            <form method="POST" action="{{ route('admin.users.reset-password', $target) }}" class="inline-form" data-confirm="{{ __('users.reset_confirm') }}">
                @csrf
                <button type="submit" class="btn">{{ __('users.reset_password') }}</button>
            </form>
        @endcan
        @can('suspend', $target)
            @if($target->isActive())
            @else
                <form method="POST" action="{{ route('admin.users.activate', $target) }}" class="inline-form">
                    @csrf
                    <button type="submit" class="btn btn--success">{{ __('users.activate') }}</button>
                </form>
            @endif
        @endcan
        @can('delete', $target)
            <x-delete-button :action="route('admin.users.destroy', $target)" :label="__('common.archive')" :message="__('users.archive_confirm')" class="btn btn--danger" />
        @endcan
    </x-slot:actions>

    @if(session('temporary_password'))
        <div class="alert alert--warning" role="status">
            <strong>{{ __('users.temporary_password_shown') }}</strong>
            <code style="user-select:all">{{ session('temporary_password') }}</code>
        </div>
    @endif

    <div class="layout-main-side">
        <div>
            <x-card :title="__('users.account')">
                <dl class="dl">
                    <dt>{{ __('common.status') }}</dt><dd><x-badge :value="$target->status" />
                        @if($target->suspension_reason)<span class="small muted"> — {{ $target->suspension_reason }} ({{ fmt_datetime($target->suspended_at) }})</span>@endif</dd>
                    <dt>{{ __('users.roles') }}</dt><dd>{{ $target->roles->pluck('name')->join(', ') ?: __('common.no_role') }}</dd>
                    <dt>{{ __('users.linked_staff') }}</dt><dd>
                        @if($target->staff)
                            @if(route_if('staff.show', $target->staff))<a href="{{ route('staff.show', $target->staff) }}">{{ $target->staff->full_name }}</a>@else{{ $target->staff->full_name }}@endif
                            <span class="small muted">{{ $target->staff->department?->displayName() }}</span>
                        @else — @endif</dd>
                    <dt>{{ __('common.language') }}</dt><dd>{{ $target->locale === 'so' ? 'Soomaali' : 'English' }}</dd>
                    <dt>{{ __('users.mfa') }}</dt><dd>{{ $target->hasMfaEnabled() ? __('users.mfa_enabled_since', ['date' => fmt_datetime($target->mfa_enabled_at)]) : __('users.mfa_not_enabled') }}</dd>
                    <dt>{{ __('users.must_change') }}</dt><dd>{{ $target->must_change_password ? __('common.yes') : __('common.no') }}</dd>
                    <dt>{{ __('users.last_login') }}</dt><dd>{{ fmt_datetime($target->last_login_at) }} <span class="small muted">{{ $target->last_login_ip }}</span></dd>
                    <dt>{{ __('users.password_changed') }}</dt><dd>{{ fmt_datetime($target->password_changed_at) }}</dd>
                    <dt>{{ __('common.created_at') }}</dt><dd>{{ fmt_datetime($target->created_at) }}</dd>
                </dl>
            </x-card>

            @can('suspend', $target)
                @if($target->isActive())
                    <x-card :title="__('users.suspend')">
                        <form method="POST" action="{{ route('admin.users.suspend', $target) }}" data-confirm="{{ __('users.suspend_confirm') }}">
                            @csrf
                            <x-field.input name="reason" :label="__('common.reason')" :required="true" maxlength="255" />
                            <div class="form-actions"><button type="submit" class="btn btn--danger">{{ __('users.suspend') }}</button></div>
                        </form>
                    </x-card>
                @endif
            @endcan

            <x-card :title="__('users.login_activity')" :flush="true">
                @if($logins->isEmpty())
                    <x-empty />
                @else
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>{{ __('common.date') }}</th><th>{{ __('common.type') }}</th><th>{{ __('users.ip') }}</th></tr></thead>
                        <tbody>@foreach($logins as $login)
                            <tr><td class="small nowrap">{{ fmt_datetime($login->created_at) }}</td><td><x-badge :value="$login->event" /></td><td class="small">{{ $login->ip_address }}</td></tr>
                        @endforeach</tbody>
                    </table></div>
                @endif
            </x-card>
        </div>
        <div>
            <x-history :model="$target" />
        </div>
    </div>
</x-layout>
