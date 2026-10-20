@props(['action', 'label' => null, 'confirm' => null, 'method' => 'DELETE', 'class' => 'btn btn-sm btn-danger'])
<form method="POST" action="{{ $action }}" class="inline-form" data-confirm="{{ $confirm ?? __('Are you sure? This cannot be undone.') }}">
    @csrf
    @method($method)
    <button type="submit" class="{{ $class }}">{{ $label ?? __('Delete') }}</button>
</form>
