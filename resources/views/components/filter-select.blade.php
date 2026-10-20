@props(['name', 'label', 'options' => []])
<div class="field">
    <label for="flt_{{ $name }}">{{ $label }}</label>
    <select id="flt_{{ $name }}" name="{{ $name }}" class="select">
        <option value="">{{ __('All') }}</option>
        @foreach ($options as $value => $text)
            <option value="{{ $value }}" @selected((string) request($name) === (string) $value)>{{ $text }}</option>
        @endforeach
    </select>
</div>
