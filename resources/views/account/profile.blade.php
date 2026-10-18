<x-layout :title="__('account.my_profile')" :breadcrumbs="[__('account.my_profile')]">
    <div class="layout-main-side">
        <div>
            <x-card :title="__('account.account_details')">
                <dl class="dl">
                    <dt>{{ __('common.name') }}</dt><dd>{{ $user->name }}</dd>
                    <dt>{{ __('auth.email') }}</dt><dd>{{ $user->email }}</dd>
                    <dt>{{ __('account.roles') }}</dt><dd>{{ $user->roles->pluck('name')->join(', ') ?: __('common.no_role') }}</dd>
                    <dt>{{ __('common.department') }}</dt><dd>{{ $user->staff?->department?->displayName() ?? '—' }}</dd>
                    <dt>{{ __('account.last_login') }}</dt><dd>{{ fmt_datetime($user->last_login_at) }}</dd>
                    <dt>{{ __('account.password_changed') }}</dt><dd>{{ fmt_datetime($user->password_changed_at) }}</dd>
                </dl>
            </x-card>

            <x-card :title="__('account.mfa')" id="mfa">
                @if(session('recovery_codes'))
                    <div class="alert alert--warning">
                        <strong>{{ __('account.recovery_codes') }}</strong>
                        <p class="small">{{ __('account.recovery_codes_help') }}</p>
                        <code>{{ implode('  ', session('recovery_codes')) }}</code>
                    </div>
                @endif
                @if($user->hasMfaEnabled())
                    <p><x-badge value="active" :label="__('account.mfa_on')" /> {{ __('account.mfa_on_since', ['date' => fmt_datetime($user->mfa_enabled_at)]) }}</p>
                    <form method="POST" action="{{ route('profile.mfa.disable') }}" class="form-grid" data-confirm="{{ __('account.mfa_disable_confirm') }}">
                        @csrf
                        @method('DELETE')
                        <x-field.input name="current_password" type="password" :label="__('auth.current_password')" :required="true" />
                        <div class="span-2"><button class="btn btn--danger btn--sm" type="submit">{{ __('account.mfa_disable') }}</button></div>
                    </form>
                @elseif($pendingSecret)
                    <ol class="small">
                        <li>{{ __('account.mfa_step1') }}</li>
                        <li>{{ __('account.mfa_step2') }} <code style="user-select:all">{{ trim(chunk_split($pendingSecret, 4, ' ')) }}</code></li>
                        <li>{{ __('account.mfa_step3') }}</li>
                    </ol>
                    <p class="small muted">{{ __('account.mfa_uri') }} <code style="word-break:break-all;user-select:all">{{ $provisioningUri }}</code></p>
                    <form method="POST" action="{{ route('profile.mfa.enable') }}" class="form-grid">
                        @csrf
                        <x-field.input name="code" :label="__('auth.mfa_code')" :required="true" autocomplete="one-time-code" />
                        <div class="span-2"><button class="btn btn--primary btn--sm" type="submit">{{ __('account.mfa_confirm') }}</button></div>
                    </form>
                @else
                    <p class="muted">{{ __('account.mfa_off') }}</p>
                    <form method="POST" action="{{ route('profile.mfa.start') }}">
                        @csrf
                        <button class="btn btn--primary btn--sm" type="submit">{{ __('account.mfa_setup') }}</button>
                    </form>
                @endif
            </x-card>
        </div>
        <div>
            <x-card :title="__('account.preferences')">
                <form method="POST" action="{{ route('profile.preferences') }}">
                    @csrf
                    @method('PUT')
                    <x-field.select name="locale" :label="__('common.language')" :options="['en' => 'English', 'so' => 'Soomaali']" :value="$user->locale" :placeholder="false" />
                    <div class="form-actions"><button class="btn btn--primary btn--sm" type="submit">{{ __('common.save') }}</button></div>
                </form>
            </x-card>
            <x-card :title="__('account.recent_logins')" :flush="true">
                @if($recentLogins->isEmpty())
                    <x-empty />
                @else
                <div class="table-wrap"><table class="table">
                    <tbody>
                    @foreach($recentLogins as $login)
                        <tr><td><x-badge :value="$login->event" /></td><td class="small">{{ fmt_datetime($login->created_at) }}<br><span class="muted">{{ $login->ip_address }}</span></td></tr>
                    @endforeach
                    </tbody>
                </table></div>
                @endif
            </x-card>
        </div>
    </div>
</x-layout>
