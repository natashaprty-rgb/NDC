<x-layout :title="__('backups.title')" :subtitle="__('backups.subtitle')" :breadcrumbs="[__('backups.title')]">
    <div class="grid grid-3">
        <x-stat :label="__('backups.last_success')" :value="$lastSuccess ? fmt_datetime($lastSuccess->completed_at) : __('system.never')" :tone="$lastSuccess && $lastSuccess->completed_at->gt(now()->subDays(2)) ? 'success' : 'danger'" :meta="$lastSuccess?->file_name" />
        <x-stat :label="__('backups.last_restore_test')" :value="$lastRestoreTest ? fmt_date($lastRestoreTest->restore_tested_at) : __('system.never')" :tone="$lastRestoreTest && $lastRestoreTest->restore_test_result === 'passed' ? 'success' : 'warning'" :meta="$lastRestoreTest ? status_label($lastRestoreTest->restore_test_result) : __('backups.restore_never_help')" />
        <x-stat :label="__('backups.command')" value="ndc:backup" :meta="__('backups.command_help')" />
    </div>

    <div class="layout-main-side mt-2">
        <x-card :title="__('backups.history')" :flush="true">
            @if($records->isEmpty())
                <x-empty :message="__('backups.none')" icon="database" />
            @else
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>{{ __('backups.completed') }}</th><th>{{ __('common.type') }}</th><th>{{ __('common.status') }}</th><th>{{ __('backups.file') }}</th><th>{{ __('backups.restore_test') }}</th></tr></thead>
                    <tbody>
                    @foreach($records as $r)
                        <tr>
                            <td class="nowrap small">{{ fmt_datetime($r->completed_at ?? $r->started_at) }}<br><span class="muted">{{ status_label($r->method) }} · {{ $r->recorder?->name ?? __('common.system') }}</span></td>
                            <td>{{ status_label($r->type) }}</td>
                            <td><x-badge :value="$r->status" /></td>
                            <td class="small">{{ $r->file_name ?? '—' }}@if($r->size)<br><span class="muted">{{ round($r->size / 1048576, 2) }} MB · sha256 {{ \Illuminate\Support\Str::limit((string) $r->checksum, 12, '…') }}</span>@endif
                                @if($r->notes)<br><span class="muted">{{ \Illuminate\Support\Str::limit($r->notes, 120) }}</span>@endif</td>
                            <td class="small">
                                @if($r->restore_tested_at)
                                    <x-badge :value="$r->restore_test_result" /> {{ fmt_date($r->restore_tested_at) }}
                                @elseif($r->status === 'successful')
                                    <details><summary class="btn btn--sm">{{ __('backups.record_restore') }}</summary>
                                        <form method="POST" action="{{ route('admin.backups.restore-test', $r) }}" class="stack mt-1">
                                            @csrf @method('PUT')
                                            <x-field.select name="restore_test_result" :label="__('backups.result')" :options="enum_options(['passed', 'failed'])" :required="true" />
                                            <x-field.input name="restore_tested_at" type="date" :label="__('common.date')" :value="today()" :required="true" />
                                            <x-field.textarea name="notes" :label="__('common.notes')" rows="2" />
                                            <button class="btn btn--primary btn--sm" type="submit">{{ __('common.save') }}</button>
                                        </form>
                                    </details>
                                @else — @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
                {{ $records->links() }}
            @endif
        </x-card>

        <x-card :title="__('backups.record_manual')">
            <p class="small muted">{{ __('backups.manual_help') }}</p>
            <form method="POST" action="{{ route('admin.backups.store') }}" class="stack">
                @csrf
                <x-field.select name="type" :label="__('common.type')" :options="enum_options(\App\Models\BackupRecord::TYPES)" :required="true" />
                <x-field.select name="status" :label="__('common.status')" :options="enum_options(['successful', 'failed'])" :required="true" />
                <x-field.input name="location" :label="__('backups.location')" :required="true" :help="__('backups.location_help')" />
                <x-field.input name="file_name" :label="__('backups.file')" />
                <x-field.input name="completed_at" type="datetime-local" :label="__('backups.completed')" :value="now()->format('Y-m-d\TH:i')" :required="true" />
                <x-field.textarea name="notes" :label="__('common.notes')" rows="3" />
                <div class="text-right"><button class="btn btn--primary" type="submit">{{ __('common.save') }}</button></div>
            </form>
        </x-card>
    </div>
</x-layout>
