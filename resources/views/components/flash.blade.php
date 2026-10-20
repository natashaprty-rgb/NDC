@foreach (['success' => 'success', 'status' => 'info', 'warning' => 'warning', 'error' => 'error'] as $key => $tone)
    @if (session($key))
        <div class="alert alert--{{ $tone }}" role="{{ $tone === 'error' ? 'alert' : 'status' }}">{{ session($key) }}</div>
    @endif
@endforeach
@if ($errors->any())
    <div class="alert alert--error" role="alert">
        <strong>{{ __('Please correct the errors below.') }}</strong>
        @if ($errors->count() <= 6)
            <ul>
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
