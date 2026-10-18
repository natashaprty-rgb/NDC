<x-layout :title="__('lookups.types.'.$type)" :breadcrumbs="[__('settings.title') => route('admin.settings.edit'), __('lookups.types.'.$type)]">
    <x-slot:actions>
        <a href="{{ route('admin.lookups.create', $type) }}" class="btn btn--primary"><x-icon name="plus" width="16" height="16" /> {{ __('common.add') }}</a>
    </x-slot:actions>
    <div class="tabs">
        @foreach($types as $t)
            <a href="{{ route('admin.lookups.index', $t) }}" class="{{ $t === $type ? 'is-active' : '' }}">{{ __('lookups.types.'.$t) }}</a>
        @endforeach
    </div>
    <x-card :flush="true">
        @if($items->isEmpty())
            <x-empty />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr>
                    @foreach(array_diff($fields, ['description']) as $f)<th>{{ __('lookups.fields.'.$f) }}</th>@endforeach
                    <th>{{ __('common.status') }}</th><th></th>
                </tr></thead>
                <tbody>
                @foreach($items as $item)
                    <tr>
                        @foreach(array_diff($fields, ['description']) as $f)
                            <td>{{ in_array($f, ['asset_class', 'type'], true) ? status_label($item->$f) : ($item->$f ?? '—') }}</td>
                        @endforeach
                        <td><x-badge :value="$item->is_active ? 'active' : 'inactive'" /></td>
                        <td class="text-right"><a class="btn btn--sm" href="{{ route('admin.lookups.edit', [$type, $item->id]) }}">{{ __('common.edit') }}</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            {{ $items->links() }}
        @endif
    </x-card>
</x-layout>
