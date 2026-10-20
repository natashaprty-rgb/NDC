<x-layouts.app :title="__('Settings')">
    <x-page-header :title="__('Settings')" :subtitle="__('System-wide preferences. Changes are recorded in the audit log.')" />
    <form method="POST" action="{{ route('admin.settings.update') }}" class="stack">
        @csrf @method('PUT')
        <div class="card">
            <div class="card__head"><h2>{{ __('General') }}</h2></div>
            <div class="card__body form-grid">
                <x-form.input name="organisation_name" :label="__('Organisation name')" :value="$settings['organisation_name']" required class="span-2" />
                <x-form.select name="default_locale" :label="__('Default language')" :options="collect(config('ndc.locales'))->map(fn ($l) => $l['native'])" :value="$settings['default_locale']" required :placeholder="false" />
                <x-form.select name="date_format" :label="__('Date format')" :options="$dateFormats" :value="$settings['date_format']" required :placeholder="false" />
                <x-form.input name="currency" :label="__('Default currency (ISO code)')" :value="$settings['currency']" required maxlength="3" />
            </div>
        </div>
        <div class="card">
            <div class="card__head"><h2>{{ __('Notifications & reminders') }}</h2></div>
            <div class="card__body form-grid">
                <x-form.checkbox name="mail_notifications_enabled" :label="__('Send notifications by e-mail as well as in the application')" :checked="$settings['mail_notifications_enabled']" class="span-2" />
                <x-form.input name="reminder_days_before_due" type="number" min="0" max="60" :label="__('Deadline reminder (days before)')" :value="$settings['reminder_days_before_due']" required />
                <x-form.input name="document_expiry_reminder_days" type="number" min="0" max="365" :label="__('Document expiry / contract renewal reminder (days before)')" :value="$settings['document_expiry_reminder_days']" required />
                <x-form.input name="warranty_reminder_days" type="number" min="0" max="365" :label="__('Warranty expiry reminder (days before)')" :value="$settings['warranty_reminder_days']" required />
            </div>
        </div>
        <div class="card">
            <div class="card__head"><h2>{{ __('File uploads') }}</h2></div>
            <div class="card__body form-grid">
                <x-form.input name="upload_max_kb" type="number" min="256" :label="__('Maximum file size (KB)')" :value="$settings['upload_max_kb']" required />
                <x-form.input name="upload_allowed_extensions" :label="__('Permitted file types')" :value="$settings['upload_allowed_extensions']" required :help="__('Comma-separated extensions. Executable and script types are always rejected.')" />
            </div>
        </div>
        <div class="card">
            <div class="card__head"><h2>{{ __('Retention') }}</h2></div>
            <div class="card__body form-grid">
                <x-form.input name="audit_retention_days" type="number" min="365" :label="__('Keep audit log entries (days)')" :value="$settings['audit_retention_days']" required />
                <x-form.input name="notification_retention_days" type="number" min="30" :label="__('Keep read notifications (days)')" :value="$settings['notification_retention_days']" required />
                <x-form.input name="backup_retention_days" type="number" min="7" :label="__('Keep backup archives (days)')" :value="$settings['backup_retention_days']" required :help="__('The most recent verified backup is always kept.')" />
            </div>
        </div>
        <div class="card">
            <div class="card__head"><h2>{{ __('Security') }}</h2></div>
            <div class="card__body form-grid">
                <x-form.input name="password_min_length" type="number" min="8" max="64" :label="__('Minimum password length')" :value="$settings['password_min_length']" required />
                <x-form.checkbox name="require_two_factor_for_admins" :label="__('Require two-factor authentication for administrators')" :checked="$settings['require_two_factor_for_admins']" />
            </div>
        </div>
        <div class="form-actions"><button class="btn btn-primary">{{ __('Save settings') }}</button></div>
    </form>
</x-layouts.app>
