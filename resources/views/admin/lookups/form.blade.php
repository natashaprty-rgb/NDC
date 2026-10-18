@php $editing = $item->exists; @endphp
<x-layout :title="($editing ? __('common.edit') : __('common.add')).' — '.__('lookups.types.'.$type)"
          :breadcrumbs="[__('settings.title') => route('admin.settings.edit'), __('lookups.types.'.$type) => route('admin.lookups.index', $type), ($editing ? $item->name : __('common.add'))]">
    <x-card>
        <form method="POST" action="{{ $editing ? route('admin.lookups.update', [$type, $item->id]) : route('admin.lookups.store', $type) }}">
            @csrf
            @if($editing) @method('PUT') @endif
            <div class="form-grid">
                @foreach($fields as $f)
                    @if($f === 'description')
                        <x-field.textarea name="description" :label="__('lookups.fields.description')" :value="$item->description" class="span-2" />
                    @elseif($f === 'asset_class')
                        <x-field.select name="asset_class" :label="__('lookups.fields.asset_class')" :options="enum_options(\App\Models\AssetCategory::CLASSES)" :value="$item->asset_class" :required="true" />
                    @elseif($f === 'type')
                        <x-field.select name="type" :label="__('lookups.fields.type')" :options="enum_options(\App\Models\FundingSource::TYPES)" :value="$item->type" :required="true" />
                    @else
                        <x-field.input :name="$f" :label="__('lookups.fields.'.$f)" :value="$item->$f" :type="$f === 'retention_years' ? 'number' : 'text'" :required="in_array($f, ['code', 'name'], true)" />
                    @endif
                @endforeach
                <x-field.checkbox name="is_active" :label="__('lookups.active_help')" :checked="(bool) $item->is_active" />
            </div>
            <div class="form-actions">
                <a href="{{ route('admin.lookups.index', $type) }}" class="btn">{{ __('common.cancel') }}</a>
                <button type="submit" class="btn btn--primary">{{ __('common.save') }}</button>
            </div>
        </form>
    </x-card>
</x-layout>
