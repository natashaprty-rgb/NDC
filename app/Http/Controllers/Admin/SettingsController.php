<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /** Editable settings: key => [validation rules, cast]. Secrets never live here (they belong in .env). */
    public static function definitions(): array
    {
        $prefixRule = ['required', 'string', 'max:10', 'regex:/^[A-Z0-9]+$/'];

        return [
            'org' => [
                'org.name' => [['required', 'string', 'max:200'], 'string'],
                'org.short_name' => [['required', 'string', 'max:20'], 'string'],
                'org.address' => [['nullable', 'string', 'max:500'], 'string'],
                'org.email' => [['nullable', 'email', 'max:255'], 'string'],
                'org.phone' => [['nullable', 'string', 'max:40'], 'string'],
            ],
            'formats' => [
                'locale.default' => [['required', Rule::in(config('app.supported_locales'))], 'string'],
                'format.date' => [['required', Rule::in(['d/m/Y', 'd-m-Y', 'Y-m-d', 'd.m.Y', 'm/d/Y'])], 'string'],
                'format.datetime' => [['required', Rule::in(['d/m/Y H:i', 'd-m-Y H:i', 'Y-m-d H:i', 'd.m.Y H:i', 'm/d/Y h:i A'])], 'string'],
                'format.currency' => [['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'], 'string'],
                'format.currency_symbol' => [['nullable', 'string', 'max:5'], 'string'],
            ],
            'numbering' => array_merge(
                ['numbering.org_prefix' => [$prefixRule, 'string'], 'numbering.padding' => [['required', 'integer', 'min:3', 'max:8'], 'int']],
                collect(config('ndc.settings_defaults'))->keys()->filter(fn ($k) => str_starts_with($k, 'numbering.prefix.'))
                    ->mapWithKeys(fn ($k) => [$k => [$prefixRule, 'string']])->all(),
            ),
            'uploads' => [
                'uploads.max_kb' => [['required', 'integer', 'min:100', 'max:102400'], 'int'],
                'uploads.allowed_extensions' => [['required', 'array', 'min:1'], 'csv'],
            ],
            'notifications' => [
                'notifications.email_enabled' => [['boolean'], 'bool'],
                'notifications.deadline_days_before' => [['required', 'integer', 'min:0', 'max:30'], 'int'],
                'notifications.meeting_hours_before' => [['required', 'integer', 'min:1', 'max:168'], 'int'],
                'notifications.expiry_days_before' => [['required', 'integer', 'min:1', 'max:365'], 'int'],
            ],
            'retention' => [
                'retention.audit_logs_years' => [['required', 'integer', 'min:1', 'max:50'], 'int'],
                'retention.closed_correspondence_years' => [['required', 'integer', 'min:1', 'max:100'], 'int'],
            ],
            'security' => [
                'security.session_timeout_minutes' => [['required', 'integer', 'min:5', 'max:480'], 'int'],
                'security.permission_review_days' => [['required', 'integer', 'min:7', 'max:365'], 'int'],
            ],
        ];
    }

    public function edit(): View
    {
        return view('admin.settings.edit', [
            'groups' => self::definitions(),
            'values' => Settings::all(),
            'uploadExtensions' => array_keys(config('ndc.upload_mime_map')),
        ]);
    }

    public function update(Request $request, string $group): RedirectResponse
    {
        $definitions = self::definitions()[$group] ?? abort(404);

        // Form field names cannot contain dots, so keys are posted with "__" instead.
        $input = collect($definitions)->keys()->mapWithKeys(fn ($key) => [$key => $request->input(str_replace('.', '__', $key))])->all();
        if ($group === 'notifications') {
            $input['notifications.email_enabled'] = $request->boolean('notifications__email_enabled');
        }
        if ($group === 'uploads') {
            $input['uploads.allowed_extensions'] = array_values(array_intersect((array) $request->input('uploads__allowed_extensions', []), array_keys(config('ndc.upload_mime_map'))));
        }

        $validator = validator(
            collect($input)->mapWithKeys(fn ($v, $k) => [str_replace('.', '__', $k) => $v])->all(),
            collect($definitions)->mapWithKeys(fn ($def, $k) => [str_replace('.', '__', $k) => $def[0]])->all(),
            [],
            collect($definitions)->keys()->mapWithKeys(fn ($k) => [str_replace('.', '__', $k) => __('settings.keys.'.str_replace('.', '_', $k))])->all(),
        );
        $validator->validate();

        $old = [];
        $new = [];
        foreach ($definitions as $key => [, $cast]) {
            $value = $input[$key];
            $value = match ($cast) {
                'int' => (int) $value,
                'bool' => (bool) $value,
                'csv' => implode(',', $value),
                default => $value === null ? '' : trim((string) $value),
            };
            $current = Settings::get($key);
            if ((string) $current !== (string) $value) {
                $old[$key] = $current;
                $new[$key] = $value;
                Settings::set($key, $value, $request->user()->id);
            }
        }

        if ($new !== []) {
            AuditLogger::log('settings_updated', null, 'Settings changed: '.$group, $old, $new);
        }

        return redirect()->route('admin.settings.edit', ['tab' => $group])->with('success', __('common.saved'));
    }
}
