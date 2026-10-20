@props(['placeholder' => null])
<div class="field field--search">
    <label for="f_q">{{ __('Search') }}</label>
    <input type="search" id="f_q" name="q" value="{{ request('q') }}" class="input" placeholder="{{ $placeholder ?? __('Search…') }}">
</div>
