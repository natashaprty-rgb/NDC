<div class="tabs">
    <a href="{{ route('admin.audit.index') }}" class="{{ request()->routeIs('admin.audit.index') ? 'is-active' : '' }}">{{ __('audit.trail') }}</a>
    <a href="{{ route('admin.audit.logins') }}" class="{{ request()->routeIs('admin.audit.logins') ? 'is-active' : '' }}">{{ __('audit.logins') }}</a>
</div>
