@props(['action', 'message' => null, 'label' => null, 'method' => 'DELETE', 'class' => 'btn btn--danger btn--sm'])
<form method="POST" action="{{ $action }}" class="inline-form" data-confirm="{{ $message ?? __('common.confirm_delete') }}">
    @csrf
    @method($method)
    <button type="submit" class="{{ $class }}">{{ $label ?? __('common.delete') }}</button>
</form>
