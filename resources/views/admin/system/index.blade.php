<x-layouts.app :title="__('System Health')">
    <x-page-header :title="__('System Health')" :subtitle="__('Live checks of the application, background jobs and backups.')">
        <x-slot:actions><a href="{{ route('admin.backups.index') }}" class="btn"><x-icon name="database" /> {{ __('Backups') }}</a></x-slot:actions>
    </x-page-header>
    <div class="grid grid-main">
        <div class="card">
            <div class="table-wrap"><table class="table">
                <thead><tr><th>{{ __('Check') }}</th><th>{{ __('Status') }}</th><th>{{ __('Detail') }}</th></tr></thead>
                <tbody>
                @php $labels = ['database' => __('Database'), 'storage' => __('File storage'), 'scheduler' => __('Scheduler'), 'queue' => __('Background jobs'), 'backup' => __('Backups'), 'debug' => __('Environment'), 'https' => __('HTTPS'), 'mail' => __('E-mail')]; @endphp
                @foreach ($checks as $key => [$state, $detail])
                    <tr>
                        <td>{{ $labels[$key] ?? $key }}</td>
                        <td><span class="badge badge--{{ ['ok' => 'success', 'warn' => 'warning', 'fail' => 'danger'][$state] }}">{{ ['ok' => __('OK'), 'warn' => __('Attention'), 'fail' => __('Problem')][$state] }}</span></td>
                        <td class="small">{{ $detail }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </div>
        <div class="stack">
            <div class="card">
                <div class="card__head"><h2>{{ __('Application') }}</h2></div>
                <div class="card__body"><dl class="dl">
                    @foreach ($info as $label => $value)<dt>{{ $label }}</dt><dd>{{ $value }}</dd>@endforeach
                </dl></div>
            </div>
            <div class="card">
                <div class="card__head"><h2>{{ __('Latest backup') }}</h2></div>
                <div class="card__body">
                    @if ($lastBackup)
                        <x-status domain="backup" :value="$lastBackup->status" />
                        <p class="small">{{ fmt_date($lastBackup->started_at, true) }} · {{ $lastBackup->size ? format_bytes($lastBackup->size) : '—' }}</p>
                    @else
                        <p class="muted">{{ __('No backups yet.') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="card" style="margin-top:1rem">
        <div class="card__head"><h2>{{ __('Failed background jobs') }}</h2></div>
        @if ($failed->isEmpty())
            <x-empty :message="__('No failed jobs.')" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>{{ __('Failed at') }}</th><th>{{ __('Queue') }}</th><th>{{ __('Error') }}</th></tr></thead>
                <tbody>@foreach ($failed as $job)
                    <tr><td class="nowrap">{{ $job->failed_at }}</td><td>{{ $job->queue }}</td><td class="small mono">{{ \Illuminate\Support\Str::limit(strtok($job->exception, "\n"), 200) }}</td></tr>
                @endforeach</tbody>
            </table></div>
            <div class="card__foot small muted">{{ __('Retry with: php artisan queue:retry all') }}</div>
        @endif
    </div>
</x-layouts.app>
