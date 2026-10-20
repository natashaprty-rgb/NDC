<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /** Date formats offered in the settings form (PHP format => example). */
    public const DATE_FORMATS = ['d M Y', 'd/m/Y', 'Y-m-d', 'm/d/Y', 'j F Y'];

    public function edit(Settings $settings): View
    {
        return view('admin.settings.edit', [
            'settings' => $settings->all(),
            'dateFormats' => collect(self::DATE_FORMATS)->mapWithKeys(fn ($f) => [$f => now()->format($f)])->all(),
        ]);
    }

    public function update(Request $request, Settings $settings): RedirectResponse
    {
        $data = $request->validate([
            'organisation_name' => ['required', 'string', 'max:255'],
            'default_locale' => ['required', Rule::in(array_keys(config('ndc.locales')))],
            'date_format' => ['required', Rule::in(self::DATE_FORMATS)],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'upload_max_kb' => ['required', 'integer', 'min:256', 'max:204800'],
            'upload_allowed_extensions' => ['required', 'string', 'max:500', 'regex:/^[a-z0-9]+(\s*,\s*[a-z0-9]+)*$/i'],
            'mail_notifications_enabled' => ['boolean'],
            'reminder_days_before_due' => ['required', 'integer', 'min:0', 'max:60'],
            'document_expiry_reminder_days' => ['required', 'integer', 'min:0', 'max:365'],
            'warranty_reminder_days' => ['required', 'integer', 'min:0', 'max:365'],
            'audit_retention_days' => ['required', 'integer', 'min:365', 'max:7300'],
            'notification_retention_days' => ['required', 'integer', 'min:30', 'max:3650'],
            'backup_retention_days' => ['required', 'integer', 'min:7', 'max:3650'],
            'require_two_factor_for_admins' => ['boolean'],
            'password_min_length' => ['required', 'integer', 'min:8', 'max:64'],
        ]);

        // Executable and script types are never accepted, whatever is configured.
        $blocked = ['php', 'phtml', 'phar', 'exe', 'sh', 'bat', 'cmd', 'js', 'html', 'htm', 'svg', 'com', 'msi', 'jar'];
        $extensions = collect(explode(',', strtolower($data['upload_allowed_extensions'])))
            ->map(fn ($e) => trim($e))->filter()->reject(fn ($e) => in_array($e, $blocked, true))->unique()->values();
        $data['upload_allowed_extensions'] = $extensions->implode(',');
        $data['currency'] = strtoupper($data['currency']);
        $data['datetime_format'] = $data['date_format'].' H:i';

        $before = array_intersect_key($settings->all(), $data);
        $settings->set($data, $request->user()->id);

        $changed = array_keys(array_filter($data, fn ($v, $k) => ($before[$k] ?? null) != $v, ARRAY_FILTER_USE_BOTH));
        Audit::log('settings_updated', null, implode(', ', $changed), array_intersect_key($before, array_flip($changed)), array_intersect_key($data, array_flip($changed)));

        return back()->with('success', __('Settings saved.'));
    }
}
