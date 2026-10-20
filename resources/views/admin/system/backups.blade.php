<x-layouts.app :title="__('Backups')">
    <x-page-header :title="__('Backups')" :subtitle="__('Database and uploaded-file archives. A backup counts as successful only after its contents have been verified.')" :breadcrumbs="[__('System Health') => route('admin.system.index')]">
        <x-slot:actions>
            <form method="POST" action="{{ route('admin.backups.store') }}" data-confirm="{{ __('Create a backup now? This may take a few minutes.') }}">
                @csrf<button class="btn btn-primary"><x-icon name="database" /> {{ __('Run backup now') }}</button>
            </form>
        </x-slot:actions>
    </x-page-header>
    <div class="card">
        @if ($runs->isEmpty())
            <x-empty icon="database" :message="__('No backups have been made yet.')" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>#</th><th>{{ __('Started') }}</th><th>{{ __('Status') }}</th><th>{{ __('Size') }}</th><th>{{ __('Verification') }}</th><th>{{ __('Triggered by') }}</th><th></th></tr></thead>
                <tbody>
                @foreach ($runs as $run)
                    <tr>
                        <td>{{ $run->id }}</td>
                        <td class="nowrap">{{ fmt_date($run->started_at, true) }}</td>
                        <td><x-status domain="backup" :value="$run->status" /></td>
                        <td class="num">{{ $run->size ? format_bytes($run->size) : '—' }}</td>
                        <td class="small prose">{{ $run->verification_result ?? $run->message ?? '—' }}</td>
                        <td>{{ $run->triggeredBy?->name ?? __('Scheduler') }}</td>
                        <td class="actions">
                            @if ($run->path)
                                <form method="POST" action="{{ route('admin.backups.verify', $run) }}" class="inline-form">@csrf<button class="btn btn-sm">{{ __('Verify') }}</button></form>
                                <a href="{{ route('admin.backups.download', $run) }}" class="btn btn-sm"><x-icon name="download" /></a>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
        {{ $runs->links() }}
    </div>
    <p class="small muted">{{ __('Restoration steps are documented in docs/BACKUP_AND_RESTORE.md. Run php artisan ndc:backup:test-restore periodically to prove a backup can be restored.') }}</p>
</x-layouts.app>
