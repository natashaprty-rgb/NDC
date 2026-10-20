<div class="card" id="maintenance">
    <div class="card__head"><h2>{{ __('Maintenance') }}</h2>
        <span class="muted small">{{ trans_choice(':count record|:count records', $asset->maintenances->count()) }}</span></div>
    @if ($asset->maintenances->isEmpty())
        <x-empty icon="settings" :message="__('No maintenance has been recorded.')" />
    @else
        <ul class="list">
            @foreach ($asset->maintenances as $item)
                <li>
                    <div class="list__row">
                        <div>
                            <x-status domain="maintenance_type" :value="$item->type" />
                            <x-status domain="maintenance" :value="$item->status" />
                            <div class="list__meta">
                                {{ __('Scheduled') }}: {{ fmt_date($item->scheduled_date) }}
                                @if ($item->completed_date) · {{ __('Completed') }}: {{ fmt_date($item->completed_date) }} @endif
                                @if ($item->supplier) · {{ $item->supplier->name }} @endif
                                @if ($item->performed_by) · {{ $item->performed_by }} @endif
                                @if ($item->cost !== null) · {{ money($item->cost, $asset->currency) }} @endif
                            </div>
                            @if ($item->description)<div class="small prose">{{ $item->description }}</div>@endif
                        </div>
                        @if ($canUpdate && in_array($item->status, ['scheduled', 'in_progress'], true))
                            <div class="nowrap">
                                @if ($item->status === 'scheduled' && $inService)
                                    <form method="POST" action="{{ route('assets.maintenance.start', [$asset, $item]) }}" class="inline-form">@csrf<button class="btn btn-sm">{{ __('Start') }}</button></form>
                                @endif
                                <form method="POST" action="{{ route('assets.maintenance.cancel', [$asset, $item]) }}" class="inline-form" data-confirm="{{ __('Cancel this maintenance?') }}">@csrf<button class="btn btn-sm btn-ghost">{{ __('Cancel') }}</button></form>
                            </div>
                        @endif
                    </div>
                    @if ($canUpdate && in_array($item->status, ['scheduled', 'in_progress'], true))
                        <details style="margin-top:.5rem">
                            <summary class="small">{{ __('Record completion') }}</summary>
                            <form method="POST" action="{{ route('assets.maintenance.complete', [$asset, $item]) }}" class="form-grid" style="margin-top:.5rem">
                                @csrf
                                <x-form.input name="completed_date" id="m{{ $item->id }}_completed" type="date" :label="__('Completion date')" :value="today()->toDateString()" required />
                                <x-form.input name="cost" id="m{{ $item->id }}_cost" type="number" step="0.01" min="0" :label="__('Cost')" :value="$item->cost" />
                                <x-form.input name="performed_by" id="m{{ $item->id }}_by" :label="__('Performed by')" :value="$item->performed_by" maxlength="255" />
                                <x-form.select name="condition" id="m{{ $item->id }}_condition" :label="__('Condition after maintenance')" :options="\App\Support\Status::options('asset_condition')" :placeholder="__('Unchanged')" />
                                <div class="span-2"><button class="btn btn-success btn-sm">{{ __('Mark as completed') }}</button></div>
                            </form>
                        </details>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
    @if ($canUpdate && $inService)
        <div class="card__foot">
            <details @if ($errors->hasAny(['type', 'scheduled_date'])) open @endif>
                <summary><strong>{{ __('Schedule maintenance') }}</strong></summary>
                <form method="POST" action="{{ route('assets.maintenance.store', $asset) }}" class="form-grid" style="margin-top:.75rem">
                    @csrf
                    <x-form.select name="type" id="mnt_type" :label="__('Type')" :options="\App\Support\Status::options('maintenance_type')" required :placeholder="false" />
                    <x-form.input name="scheduled_date" id="mnt_date" type="date" :label="__('Scheduled date')" required />
                    <x-form.select name="supplier_id" id="mnt_supplier" :label="__('Service provider')" :options="$suppliers" />
                    <x-form.input name="performed_by" id="mnt_by" :label="__('Technician')" maxlength="255" />
                    <x-form.textarea name="description" id="mnt_description" :label="__('Description')" rows="2" class="span-2" />
                    <div class="span-2"><button class="btn btn-primary">{{ __('Schedule') }}</button></div>
                </form>
            </details>
        </div>
    @endif
</div>
