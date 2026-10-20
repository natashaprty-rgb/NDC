<x-layouts.app :title="__('Reference Data')">
    <x-page-header :title="__('Reference Data')" :subtitle="__('Lists used in forms across the system. Deactivate items instead of deleting them so existing records keep their values.')" />
    <div class="tabs">
        @foreach ($types as $key => $label)
            <a href="{{ route('admin.categories.index', ['type' => $key]) }}" @class(['is-active' => $key === $type])>{{ __($label) }}</a>
        @endforeach
    </div>
    <div class="grid grid-main">
        <div class="card">
            @if ($categories->isEmpty())
                <x-empty icon="tags" />
            @else
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Somali') }}</th><th>{{ __('Code') }}</th><th class="num">{{ __('Order') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($categories as $category)
                        <tr>
                            <td class="title-cell">{{ $category->name }}@if ($category->description)<span class="sub">{{ $category->description }}</span>@endif</td>
                            <td lang="so">{{ $category->name_so ?? '—' }}</td>
                            <td class="mono">{{ $category->code ?? '—' }}</td>
                            <td class="num">{{ $category->sort_order }}</td>
                            <td>@if ($category->is_active)<span class="badge badge--success">{{ __('Active') }}</span>@else<span class="badge">{{ __('Inactive') }}</span>@endif</td>
                            <td class="actions"><a class="btn btn-sm" href="{{ route('admin.categories.index', ['type' => $type, 'edit' => $category->id]) }}">{{ __('Edit') }}</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </div>
        <div class="card">
            <div class="card__head"><h2>{{ $editing ? __('Edit item') : __('Add item') }}</h2></div>
            <form method="POST" action="{{ $editing ? route('admin.categories.update', $editing) : route('admin.categories.store') }}" class="card__body">
                @csrf
                @if ($editing) @method('PUT') @endif
                <input type="hidden" name="type" value="{{ $type }}">
                <x-form.input name="name" :label="__('Name (English)')" :value="$editing?->name" required />
                <x-form.input name="name_so" :label="__('Name (Somali)')" :value="$editing?->name_so" lang="so" />
                <x-form.input name="code" :label="__('Code')" :value="$editing?->code" />
                <x-form.input name="sort_order" type="number" min="0" :label="__('Display order')" :value="$editing?->sort_order ?? 0" />
                <x-form.textarea name="description" :label="__('Description')" :value="$editing?->description" rows="2" />
                <x-form.checkbox name="is_active" :label="__('Active')" :checked="$editing?->is_active ?? true" />
                <div class="form-actions">
                    <button class="btn btn-primary">{{ $editing ? __('Save') : __('Add') }}</button>
                    @if ($editing)<a href="{{ route('admin.categories.index', ['type' => $type]) }}" class="btn btn-ghost">{{ __('Cancel') }}</a>@endif
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>
