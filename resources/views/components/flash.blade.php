@foreach(['success' => 'success', 'status' => 'info', 'warning' => 'warning', 'error' => 'danger'] as $key => $tone)
    @if(session($key))
        <div class="alert alert--{{ $tone }}" role="{{ $tone === 'danger' ? 'alert' : 'status' }}">{{ session($key) }}</div>
    @endif
@endforeach
@if($errors->any())
    <div class="alert alert--danger" role="alert">
        <strong>{{ __('common.fix_errors') }}</strong>
        <ul>
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
