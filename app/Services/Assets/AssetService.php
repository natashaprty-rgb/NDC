<?php

namespace App\Services\Assets;

use App\Models\Asset;
use App\Models\AssetMaintenance;
use App\Models\AssetMovement;
use App\Models\Staff;
use App\Models\User;
use App\Support\Money;
use App\Support\ReferenceNumber;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssetService
{
    private const FIELDS = [
        'name', 'category_id', 'description', 'manufacturer', 'model', 'serial_number', 'purchase_date', 'purchase_cost',
        'currency', 'supplier_id', 'purchase_order_id', 'warranty_expiry', 'condition', 'location_id', 'department_id', 'notes',
    ];

    public function register(array $data, User $actor): Asset
    {
        return DB::transaction(function () use ($data, $actor) {
            $staffId = $data['assigned_staff_id'] ?? null;
            $asset = Asset::query()->create($this->attributes($data) + [
                'asset_tag' => filled($data['asset_tag'] ?? null) ? $data['asset_tag'] : ReferenceNumber::next('asset'),
                'status' => $staffId ? 'in_use' : 'in_stock',
                'assigned_staff_id' => $staffId,
            ]);

            if ($staffId) {
                $staff = Staff::query()->find($staffId);
                if (! $asset->department_id && $staff?->department_id) {
                    $asset->update(['department_id' => $staff->department_id]);
                }
                AssetMovement::query()->create([
                    'asset_id' => $asset->id, 'action' => 'assigned', 'to_staff_id' => $staffId,
                    'to_location_id' => $asset->location_id, 'to_department_id' => $asset->department_id,
                    'movement_date' => today(), 'performed_by' => $actor->id,
                ]);
            }

            return $asset;
        });
    }

    public function update(Asset $asset, array $data): Asset
    {
        $attributes = $this->attributes($data);
        if (filled($data['asset_tag'] ?? null)) {
            $attributes['asset_tag'] = $data['asset_tag'];
        }
        $asset->update($attributes);

        return $asset;
    }

    /** Assign, transfer or return an asset, writing the movement history. */
    public function move(Asset $asset, array $data, User $actor): AssetMovement
    {
        return DB::transaction(function () use ($asset, $data, $actor) {
            $asset = Asset::query()->lockForUpdate()->findOrFail($asset->id);
            $action = $data['action'];

            if ($asset->status === 'under_maintenance') {
                throw ValidationException::withMessages(['action' => __('assets.under_maintenance_block')]);
            }
            if ($action === 'assigned' && $asset->assigned_staff_id) {
                throw ValidationException::withMessages(['action' => __('assets.already_assigned')]);
            }
            if ($action === 'returned' && ! $asset->assigned_staff_id) {
                throw ValidationException::withMessages(['action' => __('assets.not_assigned')]);
            }
            if ($action === 'transferred' && empty($data['to_staff_id']) && empty($data['to_location_id']) && empty($data['to_department_id'])) {
                throw ValidationException::withMessages(['to_staff_id' => __('assets.transfer_needs_target')]);
            }

            $toStaff = $action === 'returned' ? null : ($data['to_staff_id'] ?? $asset->assigned_staff_id);
            $toDepartment = $data['to_department_id'] ?? null;
            if (! $toDepartment && $toStaff && $toStaff !== $asset->assigned_staff_id) {
                $toDepartment = Staff::query()->whereKey($toStaff)->value('department_id');
            }
            $toDepartment ??= $asset->department_id;
            $toLocation = $data['to_location_id'] ?? $asset->location_id;

            $movement = AssetMovement::query()->create([
                'asset_id' => $asset->id,
                'action' => $action,
                'from_staff_id' => $asset->assigned_staff_id,
                'to_staff_id' => $toStaff,
                'from_location_id' => $asset->location_id,
                'to_location_id' => $toLocation,
                'from_department_id' => $asset->department_id,
                'to_department_id' => $toDepartment,
                'movement_date' => $data['movement_date'],
                'notes' => $data['notes'] ?? null,
                'performed_by' => $actor->id,
            ]);

            $asset->update([
                'assigned_staff_id' => $toStaff,
                'location_id' => $toLocation,
                'department_id' => $toDepartment,
                'status' => $toStaff ? 'in_use' : 'in_stock',
            ]);

            return $movement;
        });
    }

    public function saveMaintenance(Asset $asset, array $data, ?AssetMaintenance $record = null): AssetMaintenance
    {
        return DB::transaction(function () use ($asset, $data, $record) {
            if (isset($data['cost']) && $data['cost'] !== '') {
                $data['cost'] = Money::fromCents(Money::toCents((string) $data['cost']));
            }
            if ($data['status'] === 'completed' && empty($data['completed_date'])) {
                $data['completed_date'] = today()->toDateString();
            }

            $record = $record
                ? tap($record)->update($data)
                : $asset->maintenances()->create($data);

            $this->syncMaintenanceStatus($asset->fresh());

            return $record;
        });
    }

    public function retire(Asset $asset, array $data, User $actor): Asset
    {
        return DB::transaction(function () use ($asset, $data, $actor) {
            AssetMovement::query()->create([
                'asset_id' => $asset->id,
                'action' => 'retired',
                'from_staff_id' => $asset->assigned_staff_id,
                'from_location_id' => $asset->location_id,
                'from_department_id' => $asset->department_id,
                'to_location_id' => $asset->location_id,
                'to_department_id' => $asset->department_id,
                'movement_date' => $data['retired_on'],
                'notes' => $data['retirement_reason'],
                'performed_by' => $actor->id,
            ]);
            $asset->update([
                'status' => $data['status'],
                'retired_on' => $data['retired_on'],
                'retirement_reason' => $data['retirement_reason'],
                'assigned_staff_id' => null,
            ]);

            return $asset;
        });
    }

    /** under_maintenance while any record is in progress; otherwise back to in_use / in_stock. */
    private function syncMaintenanceStatus(Asset $asset): void
    {
        if ($asset->isRetired()) {
            return;
        }
        $active = $asset->maintenances()->where('status', 'in_progress')->exists();
        if ($active && $asset->status !== 'under_maintenance') {
            $asset->update(['status' => 'under_maintenance']);
        } elseif (! $active && $asset->status === 'under_maintenance') {
            $asset->update(['status' => $asset->assigned_staff_id ? 'in_use' : 'in_stock']);
        }
    }

    private function attributes(array $data): array
    {
        $attributes = Arr::only($data, self::FIELDS);
        if (array_key_exists('purchase_cost', $attributes) && $attributes['purchase_cost'] !== null && $attributes['purchase_cost'] !== '') {
            $attributes['purchase_cost'] = Money::fromCents(Money::toCents((string) $attributes['purchase_cost']));
        }
        $attributes['specifications'] = collect($data['specifications'] ?? [])
            ->map(fn ($row) => ['key' => trim((string) $row['key']), 'value' => trim((string) ($row['value'] ?? ''))])
            ->values()->all() ?: null;

        return $attributes;
    }
}
