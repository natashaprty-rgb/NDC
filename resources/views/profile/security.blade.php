<x-layouts.app :title="__('Security & sessions')">
    <x-page-header :title="__('Security & sessions')" />
    <div class="tabs">
        <a href="{{ route('profile.edit') }}">{{ __('Profile') }}</a>
        <a href="{{ route('profile.security') }}" class="is-active">{{ __('Security & sessions') }}</a>
    </div>

    <div class="grid grid-2">
        <div class="card">
            <div class="card__head"><h2>{{ __('Change password') }}</h2></div>
            <div class="card__body">
                <form method="POST" action="{{ route('profile.password') }}">
                    @csrf @method('PUT')
                    <x-form.input name="current_password" type="password" :label="__('Current password')" required autocomplete="current-password" />
                    <x-form.input name="password" type="password" :label="__('New password')" required autocomplete="new-password"
                        :help="__('At least :n characters with upper and lower case letters, a number and a symbol.', ['n' => setting('password_min_length')])" />
                    <x-form.input name="password_confirmation" type="password" :label="__('Confirm new password')" required autocomplete="new-password" />
                    <button class="btn btn-primary">{{ __('Change password') }}</button>
                </form>
                <p class="small muted" style="margin-top:.75rem">{{ __('Last changed: :date', ['date' => fmt_date($user->password_changed_at, true)]) }}</p>
            </div>
        </div>

        <div class="card">
            <div class="card__head"><h2>{{ __('Two-factor authentication') }}</h2>
                @if ($user->hasTwoFactorEnabled())<span class="badge badge--success">{{ __('Enabled') }}</span>@else<span class="badge badge--neutral">{{ __('Not enabled') }}</span>@endif
            </div>
            <div class="card__body">
                @if ($recoveryCodes)
                    <div class="alert alert--warning">
                        <strong>{{ __('Store these recovery codes somewhere safe.') }}</strong> {{ __('Each can be used once if you lose access to your authenticator app. They will not be shown again.') }}
                        <ul class="mono">@foreach ($recoveryCodes as $code)<li>{{ $code }}</li>@endforeach</ul>
                    </div>
                @endif

                @if ($user->hasTwoFactorEnabled())
                    <p>{{ __('Your account is protected with a one-time code from an authenticator app.') }}</p>
                    <form method="POST" action="{{ route('profile.two-factor.disable') }}">
                        @csrf @method('DELETE')
                        <x-form.input name="current_password" type="password" :label="__('Confirm with your password to disable')" required id="disable_password" />
                        <button class="btn btn-danger">{{ __('Disable two-factor authentication') }}</button>
                    </form>
                @elseif ($setupSecret)
                    <p>{{ __('Scan this QR code with an authenticator app (for example Google Authenticator or Microsoft Authenticator), then enter the 6-digit code it shows.') }}</p>
                    <div style="background:#fff;display:inline-block;padding:.5rem;border:1px solid var(--line);border-radius:6px">{!! $qrCode !!}</div>
                    <p class="small muted">{{ __('Setup key:') }} <span class="mono">{{ trim(chunk_split($setupSecret, 4, ' ')) }}</span></p>
                    <form method="POST" action="{{ route('profile.two-factor.confirm') }}">
                        @csrf
                        <x-form.input name="code" :label="__('Verification code')" required autocomplete="one-time-code" inputmode="numeric" maxlength="6" />
                        <button class="btn btn-primary">{{ __('Confirm and enable') }}</button>
                    </form>
                @else
                    <p>{{ __('Add a second step to sign-in using an authenticator app on your phone.') }}</p>
                    <form method="POST" action="{{ route('profile.two-factor.start') }}">
                        @csrf
                        <button class="btn btn-primary">{{ __('Set up two-factor authentication') }}</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div class="card" style="margin-top:1rem">
        <div class="card__head"><h2>{{ __('Active sessions') }}</h2></div>
        @if (empty($sessions))
            <x-empty :message="__('Session listing is not available with the current session driver.')" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>{{ __('Device') }}</th><th>{{ __('IP address') }}</th><th>{{ __('Last active') }}</th></tr></thead>
                <tbody>
                @foreach ($sessions as $session)
                    <tr>
                        <td>{{ $session->user_agent ?: __('Unknown') }} @if ($session->is_current)<span class="badge badge--success">{{ __('This device') }}</span>@endif</td>
                        <td class="mono">{{ $session->ip_address }}</td>
                        <td class="nowrap">{{ fmt_date($session->last_active, true) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="card__foot">
                <form method="POST" action="{{ route('profile.sessions.destroy') }}" class="grid" style="grid-template-columns:minmax(0,320px) auto;align-items:end">
                    @csrf @method('DELETE')
                    <x-form.input name="current_password" type="password" :label="__('Password')" required id="sessions_password" />
                    <div class="field"><button class="btn">{{ __('Sign out all other sessions') }}</button></div>
                </form>
            </div>
        @endif
    </div>

    @can('api.access')
        <div class="card" style="margin-top:1rem">
            <div class="card__head"><h2>{{ __('API access tokens') }}</h2></div>
            @if ($newToken)
                <div class="card__body">
                    <div class="alert alert--warning"><strong>{{ __('Copy this token now. It will not be shown again.') }}</strong>
                        <div class="mono" style="word-break:break-all;margin-top:.4rem">{{ $newToken }}</div></div>
                </div>
            @endif
            @if ($tokens->isEmpty())
                <x-empty :message="__('No API tokens issued.')" />
            @else
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Created') }}</th><th>{{ __('Last used') }}</th><th>{{ __('Expires') }}</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($tokens as $token)
                        <tr>
                            <td>{{ $token->name }}</td>
                            <td class="nowrap">{{ fmt_date($token->created_at) }}</td>
                            <td class="nowrap">{{ fmt_date($token->last_used_at, true) }}</td>
                            <td class="nowrap">{{ fmt_date($token->expires_at) }}</td>
                            <td class="actions"><x-delete-button :action="route('profile.tokens.destroy', $token->id)" :label="__('Revoke')" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
            <div class="card__foot">
                <form method="POST" action="{{ route('profile.tokens.store') }}" class="grid" style="grid-template-columns:minmax(0,2fr) minmax(0,1fr) auto;align-items:end">
                    @csrf
                    <x-form.input name="token_name" :label="__('Token name')" required :help="__('For example the name of the integrating system.')" />
                    <x-form.select name="expires_in_days" :label="__('Expires after')" :options="[30 => __(':n days', ['n' => 30]), 90 => __(':n days', ['n' => 90]), 180 => __(':n days', ['n' => 180]), 365 => __(':n days', ['n' => 365])]" value="90" :placeholder="false" />
                    <div class="field"><button class="btn btn-primary">{{ __('Create token') }}</button></div>
                </form>
                <p class="small muted" style="margin:.5rem 0 0">{{ __('Tokens are read-only and act with your permissions. See docs/API.md.') }}</p>
            </div>
        </div>
    @endcan
</x-layouts.app>
