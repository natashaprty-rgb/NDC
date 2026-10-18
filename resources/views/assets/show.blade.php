@php
    $canMove = auth()->user()->can('move', $asset);
    $canMaintain = auth()->user()->can('maintain', $asset);
@endphp
<x-layout :title="$asset->name" :subtitle="$asset->asset_tag"
          :breadcrumbs="[__('assets.title') => route('assets.index'), $asset->asset_tag]">
    <x-slot:actions>
        @can('update', $asset)
            <a href="{{ route('assets.edit', $asset) }}" class="btn"><x-icon name="edit" width="16" height="16" /> {{ __('common.edit') }}</a>
        @endcan
        @can('delete', $asset)
            <x-delete-button :action="route('assets.destroy', $asset)" :label="__('common.archive')" :message="__('common.confirm_archive')" class="btn btn--danger" />
        @endcan
    </x-slot:actions>

    @if($asset->isRetired())
        <div class="alert alert--warning">{{ __('assets.retired_info', ['status' => status_label($asset->status), 'date' => fmt_date($asset->retired_on), 'reason' => $asset->retirement_reason]) }}</div>
    @endif

    <div class="layout-main-side">
        <div>
            <x-card :title="__('common.details')">
                <dl class="dl">
                    <dt>{{ __('common.status') }}</dt><dd><x-badge :value="$asset->status" /></dd>
                    <dt>{{ __('assets.category') }}</dt><dd>{{ $asset->category?->displayName() ?? '—' }}</dd>
                    <dt>{{ __('assets.condition') }}</dt><dd><x-badge :value="$asset->condition" /></dd>
                    <dt>{{ __('assets.manufacturer') }} / {{ __('assets.model') }}</dt><dd>{{ $asset->manufacturer ?? '—' }} / {{ $asset->model ?? '—' }}</dd>
                    <dt>{{ __('assets.serial_number') }}</dt><dd>{{ $asset->serial_number ?? '—' }}</dd>
                    <dt>{{ __('assets.assigned_staff') }}</dt><dd>@if($asset->assignedStaff)@if(route_if('staff.show', $asset->assignedStaff))<a href="{{ route('staff.show', $asset->assignedStaff) }}">{{ $asset->assignedStaff->full_name }}</a>@else{{ $asset->assignedStaff->full_name }}@endif @else — @endif</dd>
                    <dt>{{ __('assets.location') }}</dt><dd>{{ $asset->location?->name ?? '—' }}</dd>
                    <dt>{{ __('common.department') }}</dt><dd>{{ $asset->department?->displayName() ?? '—' }}</dd>
                    <dt>{{ __('assets.purchase_date') }}</dt><dd>{{ fmt_date($asset->purchase_date) }}</dd>
                    <dt>{{ __('assets.purchase_cost') }}</dt><dd>{{ $asset->purchase_cost !== null ? money($asset->purchase_cost, $asset->currency) : '—' }}</dd>
                    <dt>{{ __('assets.supplier') }}</dt><dd>@if($asset->supplier)@if(route_if('suppliers.show', $asset->supplier))<a href="{{ route('suppliers.show', $asset->supplier) }}">{{ $asset->supplier->name }}</a>@else{{ $asset->supplier->name }}@endif @else — @endif</dd>
                    <dt>{{ __('assets.purchase_order') }}</dt><dd>{{ $asset->purchaseOrder?->reference ?? '—' }}</dd>
                    <dt>{{ __('assets.warranty_expiry') }}</dt><dd>{{ fmt_date($asset->warranty_expiry) }}
                        @if($asset->warranty_expiry && $asset->warranty_expiry->lt(today()))<x-badge value="expired" />@endif</dd>
                </dl>
                @if($asset->description)<p class="prose mt-2 mb-0">{{ $asset->description }}</p>@endif
                @if($asset->notes)<p class="prose mt-1 mb-0 small muted">{{ $asset->notes }}</p>@endif
            </x-card>

            <x-card :title="__('assets.specifications')" :flush="true">
                @if(empty($asset->specifications))
                    <x-empty :message="__('assets.no_specs')" icon="list" />
                @else
                    <div class="table-wrap"><table class="table"><tbody>
                        @foreach($asset->specifications as $spec)
                            <tr><th scope="row" style="width:35%">{{ $spec['key'] ?? '' }}</th><td>{{ $spec['value'] ?? '' }}</td></tr>
                        @endforeach
                    </tbody></table></div>
                @endif
            </x-card>

            <x-card :title="__('assets.movements')" :flush="true">
                @if($asset->movements->isEmpty())
                    <x-empty :message="__('assets.no_movements')" icon="truck" />
                @else
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>{{ __('common.date') }}</th><th>{{ __('assets.movement_action') }}</th><th>{{ __('common.from') }}</th><th>{{ __('common.to') }}</th><th>{{ __('assets.performed_by') }}</th></tr></thead>
                        <tbody>@foreach($asset->movements as $mv)
                            <tr>
                                <td class="nowrap">{{ fmt_date($mv->movement_date) }}</td>
                                <td><x-badge :value="$mv->action" />@if($mv->notes)<br><span class="small muted">{{ $mv->notes }}</span>@endif</td>
                                <td class="small">{{ $mv->fromStaff?->full_name ?? '—' }}<br><span class="muted">{{ $mv->fromLocation?->name }}</span></td>
                                <td class="small">{{ $mv->toStaff?->full_name ?? '—' }}<br><span class="muted">{{ $mv->toLocation?->name }} {{ $mv->toDepartment ? '· '.$mv->toDepartment->displayName() : '' }}</span></td>
                                <td class="small">{{ $mv->performer?->name ?? '—' }}</td>
                            </tr>
                        @endforeach</tbody>
                    </table></div>
                @endif
            </x-card>

            <x-card :title="__('assets.maintenance')" :flush="true" id="maintenance">
                @if($asset->maintenances->isEmpty())
                    <x-empty :message="__('assets.no_maintenance')" icon="cog" />
                @else
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>{{ __('common.type') }}</th><th>{{ __('common.description') }}</th><th>{{ __('assets.provider') }}</th><th>{{ __('assets.scheduled_date') }}</th><th>{{ __('assets.completed_date') }}</th><th class="num">{{ __('assets.cost') }}</th><th>{{ __('common.status') }}</th></tr></thead>
                        <tbody>@foreach($asset->maintenances as $m)
                            <tr>
                                <td>{{ status_label($m->type) }}</td>
                                <td class="prose">{{ $m->description }}</td>
                                <td>{{ $m->supplier?->name ?? $m->provider ?? '—' }}</td>
                                <td class="nowrap">{{ fmt_date($m->scheduled_date) }}</td>
                                <td class="nowrap">{{ fmt_date($m->completed_date) }}</td>
                                <td class="num">{{ $m->cost !== null ? money($m->cost, $asset->currency) : '—' }}</td>
                                <td>
                                    @if($canMaintain && in_array($m->status, ['scheduled', 'in_progress'], true))
                                        <form method="POST" action="{{ route('assets.maintenance.update', [$asset, $m]) }}" class="flex">
                                            @csrf @method('PUT')
                                            @foreach(['type', 'description', 'provider', 'supplier_id', 'scheduled_date', 'completed_date', 'cost', 'notes'] as $f)
                                                <input type="hidden" name="{{ $f }}" value="{{ $m->{$f} instanceof \DateTimeInterface ? $m->{$f}->format('Y-m-d') : $m->{$f} }}">
                                            @endforeach
                                            <label class="sr-only" for="mstatus_{{ $m->id }}">{{ __('common.status') }}</label>
                                            <select id="mstatus_{{ $m->id }}" name="status" class="input" style="width:auto">
                                                @foreach(\App\Models\AssetMaintenance::STATUSES as $s)<option value="{{ $s }}" @selected($m->status === $s)>{{ status_label($s) }}</option>@endforeach
                                            </select>
                                            <button type="submit" class="btn btn--sm">{{ __('assets.update_maintenance') }}</button>
                                        </form>
                                    @else
                                        <x-badge :value="$m->status" />
                                    @endif
                                </td>
                            </tr>
                        @endforeach</tbody>
                        <tfoot><tr><td colspan="5">{{ __('assets.total_maintenance_cost') }}</td><td class="num">{{ money($maintenanceTotal, $asset->currency) }}</td><td></td></tr></tfoot>
                    </table></div>
                @endif
                @if($canMaintain)
                    <x-slot:footer>
                        <form method="POST" action="{{ route('assets.maintenance.store', $asset) }}" class="form-grid">
                            @csrf
                            <x-field.select name="type" :label="__('common.type')" :options="enum_options(\App\Models\AssetMaintenance::TYPES)" :placeholder="false" />
                            <x-field.select name="status" :label="__('common.status')" :options="enum_options(\App\Models\AssetMaintenance::STATUSES)" value="scheduled" :placeholder="false" />
                            <x-field.textarea name="description" :label="__('common.description')" :required="true" rows="2" class="span-2" />
                            <x-field.input name="provider" :label="__('assets.provider')" maxlength="200" />
                            <x-field.select name="supplier_id" :label="__('assets.supplier')" :options="$suppliers" :placeholder="__('common.none')" />
                            <x-field.input name="scheduled_date" type="date" :label="__('assets.scheduled_date')" />
                            <x-field.input name="completed_date" type="date" :label="__('assets.completed_date')" />
                            <x-field.input name="cost" type="number" step="0.01" min="0" :label="__('assets.cost')" />
                            <div class="field" style="justify-content:flex-end"><button type="submit" class="btn btn--primary btn--sm">{{ __('assets.add_maintenance') }}</button></div>
                        </form>
                    </x-slot:footer>
                @endif
            </x-card>

            <x-attachments :model="$asset" :categories="['general', 'invoice', 'receipt', 'photo', 'contract', 'report']" />
        </div>

        <div>
            @if($canMove)
                <x-card :title="__('assets.record_movement')">
                    <form method="POST" action="{{ route('assets.movements.store', $asset) }}" class="stack">
                        @csrf
                        <x-field.select name="action" :label="__('assets.movement_action')" :required="true" :placeholder="false"
                            :options="$asset->assigned_staff_id ? ['transferred' => __('assets.transfer'), 'returned' => __('assets.return')] : ['assigned' => __('assets.assign'), 'transferred' => __('assets.transfer')]" />
                        <x-field.select name="to_staff_id" :label="__('assets.to_staff')" :options="$staffList" :placeholder="__('common.not_set')" />
                        <x-field.select name="to_location_id" :label="__('assets.to_location')" :options="$locations" :placeholder="__('common.not_set')" />
                        <x-field.select name="to_department_id" :label="__('assets.to_department')" :options="$departments" :placeholder="__('common.not_set')" />
                        <x-field.input name="movement_date" type="date" :label="__('assets.movement_date')" :value="today()->toDateString()" :required="true" />
                        <x-field.textarea name="notes" :label="__('common.notes')" rows="2" />
                        <div class="text-right"><button type="submit" class="btn btn--primary btn--sm">{{ __('assets.record_movement') }}</button></div>
                    </form>
                </x-card>
            @endif

            @can('retire', $asset)
                <x-card :title="__('assets.retire')">
                    <form method="POST" action="{{ route('assets.retire', $asset) }}" class="stack" data-confirm="{{ __('assets.confirm_retire') }}">
                        @csrf
                        <x-field.select name="status" :label="__('assets.retire_status')" :options="enum_options(\App\Models\Asset::RETIRED_STATUSES)" :required="true" :placeholder="false" />
                        <x-field.input name="retired_on" type="date" :label="__('assets.retired_on')" :value="today()->toDateString()" :required="true" />
                        <x-field.textarea name="retirement_reason" :label="__('assets.retirement_reason')" :required="true" rows="2" />
                        <div class="text-right"><button type="submit" class="btn btn--danger btn--sm">{{ __('assets.retire') }}</button></div>
                    </form>
                </x-card>
            @endcan

            <x-history :model="$asset" />
        </div>
    </div>
</x-layout>
