<x-layouts.app :title="$user->name">
    <x-page-header :title="$user->name" :subtitle="$user->email" :breadcrumbs="[__('Users') => route('admin.users.index')]">
        <x-slot:actions>
            <a href="{{ route('admin.users.edit', $user) }}" class="btn"><x-icon name="edit" /> {{ __('Edit') }}</a>
            <form method="POST" action="{{ route('admin.users.send-reset', $user) }}" class="inline-form">@csrf<button class="btn">{{ __('Send password reset') }}</button></form>
        </x-slot:actions>
    </x-page-header>
    <div class="grid grid-main">
        <div class="stack">
            <div class="card">
                <div class="card__body">
                    <dl class="dl">
                        <dt>{{ __('Status') }}</dt><dd><x-status domain="user" :value="$user->status" /></dd>
                        <dt>{{ __('Roles') }}</dt><dd>{{ $user->getRoleNames()->map(fn ($r) => __($r))->join(', ') ?: '—' }}</dd>
                        <dt>{{ __('Department') }}</dt><dd>{{ $user->department?->displayName() ?? '—' }}</dd>
                        <dt>{{ __('Job title') }}</dt><dd>{{ $user->job_title ?? '—' }}</dd>
                        <dt>{{ __('Phone') }}</dt><dd>{{ $user->phone ?? '—' }}</dd>
                        <dt>{{ __('Interface language') }}</dt><dd>{{ config('ndc.locales.'.$user->locale.'.native') }}</dd>
                        <dt>{{ __('Two-factor authentication') }}</dt><dd>{{ $user->hasTwoFactorEnabled() ? __('Enabled') : __('Not enabled') }}</dd>
                        <dt>{{ __('Last sign-in') }}</dt><dd>{{ fmt_date($user->last_login_at, true) }} @if($user->last_login_ip)<span class="muted mono small">({{ $user->last_login_ip }})</span>@endif</dd>
                        <dt>{{ __('Staff profile') }}</dt><dd>@if ($user->staffProfile && Route::has('staff.show'))<a href="{{ route('staff.show', $user->staffProfile) }}">{{ $user->staffProfile->staff_number }}</a>@else — @endif</dd>
                        <dt>{{ __('Created') }}</dt><dd>{{ fmt_date($user->created_at, true) }}</dd>
                    </dl>
                </div>
            </div>
            <div class="card">
                <div class="card__head"><h2>{{ __('Recent activity') }}</h2>
                    @can('audit.view')<a href="{{ route('admin.audit.index', ['user' => $user->id]) }}" class="small">{{ __('Full log') }}</a>@endcan</div>
                @if ($activity->isEmpty())
                    <x-empty :message="__('No activity recorded.')" />
                @else
                    <div class="table-wrap"><table class="table">
                        <tbody>
                        @foreach ($activity as $log)
                            <tr><td class="nowrap">{{ fmt_date($log->created_at, true) }}</td><td>{{ __(str($log->event)->replace('_', ' ')->ucfirst()->toString()) }}</td><td>{{ $log->subjectLabel() }}</td><td class="muted">{{ $log->description }}</td></tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </div>
        </div>
        <div class="stack">
            <div class="card">
                <div class="card__head"><h2>{{ __('Account access') }}</h2></div>
                <div class="card__body">
                    @if (! auth()->user()->is($user))
                        <form method="POST" action="{{ route('admin.users.status', $user) }}" class="stack">
                            @csrf @method('PATCH')
                            @foreach (\App\Support\Status::options('user') as $value => $label)
                                @if ($value !== $user->status)
                                    <button name="status" value="{{ $value }}" @class(['btn', 'btn-success' => $value === 'active', 'btn-danger' => $value !== 'active']) style="width:100%">
                                        {{ match ($value) { 'active' => __('Activate account'), 'suspended' => __('Suspend account'), default => __('Deactivate account') } }}
                                    </button>
                                @endif
                            @endforeach
                        </form>
                        <p class="small muted">{{ __('Suspending or deactivating signs the user out everywhere and revokes API tokens.') }}</p>
                    @else
                        <p class="muted">{{ __('You cannot change the status of your own account.') }}</p>
                    @endif
                    @if ($user->hasTwoFactorEnabled())
                        <hr>
                        <x-delete-button :action="route('admin.users.two-factor.reset', $user)" :label="__('Reset two-factor authentication')" :confirm="__('The user will be able to sign in with their password only until they set up two-factor again. Continue?')" class="btn btn-sm" />
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
