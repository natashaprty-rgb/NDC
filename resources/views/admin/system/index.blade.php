<x-layout :title="__('system.title')" :subtitle="__('system.subtitle')" :breadcrumbs="[__('system.title')]">
    <div class="grid grid-2">
        <x-card :title="__('system.checks')" :flush="true">
            <div class="table-wrap"><table class="table"><tbody>
                @foreach($checks as $key => $check)
                    <tr>
                        <td>{{ __('system.check.'.$key) }}</td>
                        <td><x-badge :value="$check['ok'] ? 'passed' : 'failed'" :label="$check['ok'] ? __('system.ok') : __('system.attention')" /></td>
                        <td class="small muted">{{ $check['detail'] }}</td>
                    </tr>
                @endforeach
            </tbody></table></div>
        </x-card>
        <x-card :title="__('system.environment')">
            <dl class="dl">
                @foreach($info as $key => $value)
                    <dt>{{ __('system.info.'.$key) }}</dt>
                    <dd>{{ $value instanceof \DateTimeInterface ? fmt_datetime($value) : ($value ?? __('system.never')) }}</dd>
                @endforeach
            </dl>
            <p class="small muted mt-2 mb-0">{{ __('system.no_secrets') }}</p>
        </x-card>
    </div>
    <x-card :title="__('system.recent_errors')" :flush="true">
        @if(empty($errorLog))
            <x-empty :message="__('system.no_errors')" icon="check-circle" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>{{ __('common.date') }}</th><th>{{ __('system.level') }}</th><th>{{ __('system.message') }}</th></tr></thead>
                <tbody>@foreach($errorLog as $e)
                    <tr><td class="nowrap small">{{ $e['time'] }}</td><td><x-badge :value="$e['level'] === 'WARNING' ? 'pending' : 'failed'" :label="$e['level']" /></td><td class="small">{{ $e['message'] }}</td></tr>
                @endforeach</tbody>
            </table></div>
        @endif
    </x-card>
</x-layout>
