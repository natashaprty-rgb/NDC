{{-- Permission matrix grouped by permission group. $editable toggles checkboxes. --}}
@foreach($groups as $group => $permissions)
    <fieldset>
        <legend>{{ __('roles.group.'.$group) }}</legend>
        <div class="grid grid-2">
            @foreach($permissions as $slug => $description)
                @if($editable)
                    <label class="checkbox">
                        <input type="checkbox" name="permissions[]" value="{{ $slug }}" @checked(in_array($slug, old('permissions', $granted), true))>
                        <span><code class="small">{{ $slug }}</code><br><span class="small muted">{{ __('permissions.'.str_replace('.', '_', $slug)) }}</span></span>
                    </label>
                @else
                    <div class="checkbox">
                        <x-badge :value="in_array($slug, $granted, true) ? 'active' : 'inactive'" :label="in_array($slug, $granted, true) ? __('common.yes') : __('common.no')" />
                        <span><code class="small">{{ $slug }}</code><br><span class="small muted">{{ __('permissions.'.str_replace('.', '_', $slug)) }}</span></span>
                    </div>
                @endif
            @endforeach
        </div>
    </fieldset>
@endforeach
