<x-layout :title="__('notifications.title')" :breadcrumbs="[__('notifications.title')]">
    <x-slot:actions>
        <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn" type="submit">{{ __('notifications.mark_all_read') }}</button></form>
    </x-slot:actions>

    <div class="tabs">
        <a href="{{ route('notifications.index') }}" class="{{ request('filter') !== 'unread' ? 'is-active' : '' }}">{{ __('common.all') }}</a>
        <a href="{{ route('notifications.index', ['filter' => 'unread']) }}" class="{{ request('filter') === 'unread' ? 'is-active' : '' }}">{{ __('notifications.unread') }}</a>
    </div>

    <x-card :flush="true">
        @if($notifications->isEmpty())
            <x-empty :message="__('notifications.none')" icon="bell" />
        @else
            <div class="table-wrap"><table class="table">
                <tbody>
                @foreach($notifications as $n)
                    <tr>
                        <td style="width:1%">@if(!$n->read_at)<span class="badge badge--info">{{ __('notifications.new') }}</span>@endif</td>
                        <td>
                            <a href="{{ route('notifications.open', $n->id) }}">{{ __($n->data['title_key'] ?? 'notifications.generic', $n->data['params'] ?? []) }}</a>
                        </td>
                        <td class="nowrap small muted">{{ fmt_datetime($n->created_at) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            {{ $notifications->links() }}
        @endif
    </x-card>

    @if($logs->isNotEmpty())
        <x-card :title="__('notifications.delivery_status')" :flush="true">
            <div class="table-wrap"><table class="table">
                <thead><tr><th>{{ __('common.date') }}</th><th>{{ __('common.type') }}</th><th>{{ __('notifications.channel') }}</th><th>{{ __('common.status') }}</th></tr></thead>
                <tbody>@foreach($logs as $log)
                    <tr><td class="small nowrap">{{ fmt_datetime($log->created_at) }}</td><td class="small">{{ $log->notification_type }}</td><td>{{ $log->channel === 'database' ? __('notifications.in_app') : __('notifications.email') }}</td><td><x-badge :value="$log->status" /></td></tr>
                @endforeach</tbody>
            </table></div>
        </x-card>
    @endif
</x-layout>
