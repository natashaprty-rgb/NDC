<x-layouts.app :title="__('Notifications')">
    <x-page-header :title="__('Notifications')">
        <x-slot:actions>
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button class="btn">{{ __('Mark all as read') }}</button>
            </form>
        </x-slot:actions>
    </x-page-header>
    <div class="tabs">
        <a href="{{ route('notifications.index') }}" @class(['is-active' => request('filter') !== 'unread'])>{{ __('All') }}</a>
        <a href="{{ route('notifications.index', ['filter' => 'unread']) }}" @class(['is-active' => request('filter') === 'unread'])>{{ __('Unread') }}</a>
    </div>
    <div class="card">
        @if ($notifications->isEmpty())
            <x-empty icon="bell" :message="__('You have no notifications.')" />
        @else
            <ul class="list">
                @foreach ($notifications as $notification)
                    <li @class(['is-unread' => ! $notification->read_at])>
                        <div class="list__row">
                            <div>
                                <a href="{{ route('notifications.open', $notification->id) }}"><strong>{{ $notification->data['title'] ?? __('Notification') }}</strong></a>
                                <div>{{ $notification->data['body'] ?? '' }}</div>
                                <div class="list__meta">{{ fmt_date($notification->created_at, true) }} · {{ $notification->read_at ? __('Read') : __('Unread') }}</div>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
        {{ $notifications->links() }}
    </div>
</x-layouts.app>
