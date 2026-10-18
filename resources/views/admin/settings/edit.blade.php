@php $tab = request('tab', 'org'); if (! array_key_exists($tab, $groups)) { $tab = 'org'; } @endphp
<x-layout :title="__('settings.title')" :subtitle="__('settings.subtitle')" :breadcrumbs="[__('settings.title')]">
    <div class="tabs">
        @foreach(array_keys($groups) as $g)
            <a href="{{ route('admin.settings.edit', ['tab' => $g]) }}" class="{{ $tab === $g ? 'is-active' : '' }}">{{ __('settings.groups.'.$g) }}</a>
        @endforeach
        <a href="{{ route('admin.lookups.index', 'document-categories') }}">{{ __('settings.lookups') }}</a>
    </div>

    <x-card :title="__('settings.groups.'.$tab)">
        <p class="small muted">{{ __('settings.help.'.$tab) }}</p>
        <form method="POST" action="{{ route('admin.settings.update', $tab) }}">
            @csrf
            @method('PUT')
            <div class="form-grid">
                @foreach($groups[$tab] as $key => [$rules, $cast])
                    @php $field = str_replace('.', '__', $key); $label = __('settings.keys.'.str_replace('.', '_', $key)); $value = $values[$key] ?? null; @endphp
                    @if($cast === 'bool')
                        <x-field.checkbox :name="$field" :label="$label" :checked="(bool) $value" />
                    @elseif($cast === 'csv')
                        <fieldset class="span-2">
                            <legend>{{ $label }}</legend>
                            @php $selected = array_map('trim', explode(',', (string) $value)); @endphp
                            <div class="flex">
                                @foreach($uploadExtensions as $ext)
                                    <label class="checkbox"><input type="checkbox" name="{{ $field }}[]" value="{{ $ext }}" @checked(in_array($ext, $selected, true))> .{{ $ext }}</label>
                                @endforeach
                            </div>
                            <p class="help mb-0">{{ __('settings.extensions_help') }}</p>
                        </fieldset>
                    @elseif($key === 'locale.default')
                        <x-field.select :name="$field" :label="$label" :options="['en' => 'English', 'so' => 'Soomaali']" :value="$value" :placeholder="false" />
                    @elseif(in_array($key, ['format.date', 'format.datetime'], true))
                        @php $opts = $key === 'format.date' ? ['d/m/Y', 'd-m-Y', 'Y-m-d', 'd.m.Y', 'm/d/Y'] : ['d/m/Y H:i', 'd-m-Y H:i', 'Y-m-d H:i', 'd.m.Y H:i', 'm/d/Y h:i A']; @endphp
                        <x-field.select :name="$field" :label="$label" :options="collect($opts)->mapWithKeys(fn ($o) => [$o => $o.'  ('.now()->format($o).')'])->all()" :value="$value" :placeholder="false" />
                    @else
                        <x-field.input :name="$field" :label="$label" :value="$value" :type="$cast === 'int' ? 'number' : 'text'" :required="in_array('required', $rules, true)" />
                    @endif
                @endforeach
            </div>
            @if($tab === 'numbering')
                <p class="small muted mt-1">{{ __('settings.numbering_example', ['example' => \App\Support\Settings::get('numbering.org_prefix').'-'.\App\Support\Settings::get('numbering.prefix.project').'-'.now()->format('Y').'-'.str_pad('1', (int) \App\Support\Settings::get('numbering.padding'), '0', STR_PAD_LEFT)]) }}</p>
            @endif
            <div class="form-actions"><button class="btn btn--primary" type="submit">{{ __('common.save_changes') }}</button></div>
        </form>
    </x-card>
</x-layout>
