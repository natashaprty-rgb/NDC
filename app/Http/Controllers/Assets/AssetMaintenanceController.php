<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetMaintenance;
use App\Support\Audit;
use App\Support\Status;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Maintenance lifecycle: scheduled -> in_progress -> completed (or
 * cancelled). While work is in progress the asset is "under maintenance".
 */
class AssetMaintenanceController extends Controller
{
    public function store(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorize('update', $asset);

        if (! $asset->isInService()) {
            return back()->with('error', __('Maintenance cannot be scheduled for a retired or disposed asset.'));
        }

        $data = $request->validate([
            'type' => ['required', Rule::in(Status::keys('maintenance_type'))],
            'scheduled_date' => ['required', 'date'],
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'performed_by' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        $asset->maintenances()->create([...$data, 'status' => 'scheduled', 'created_by' => $request->user()->id]);

        return back()->with('success', __('Maintenance scheduled.'));
    }

    public function start(Asset $asset, AssetMaintenance $maintenance): RedirectResponse
    {
        $this->authorize('update', $asset);

        if ($maintenance->status !== 'scheduled' || ! $asset->isInService()) {
            return back()->with('error', __('Only scheduled maintenance can be started.'));
        }

        DB::transaction(function () use ($asset, $maintenance) {
            $maintenance->update(['status' => 'in_progress']);
            $asset->settleStatus();
        });

        Audit::log('maintenance_started', $asset, Status::label('maintenance_type', $maintenance->type));

        return back()->with('success', __('Maintenance started. The asset is now under maintenance.'));
    }

    public function complete(Request $request, Asset $asset, AssetMaintenance $maintenance): RedirectResponse
    {
        $this->authorize('update', $asset);

        if (! in_array($maintenance->status, ['scheduled', 'in_progress'], true)) {
            return back()->with('error', __('This maintenance record is already closed.'));
        }

        $data = $request->validate([
            'completed_date' => ['required', 'date', 'before_or_equal:today'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'performed_by' => ['nullable', 'string', 'max:255'],
            'condition' => ['nullable', Rule::in(Status::keys('asset_condition'))],
        ]);

        DB::transaction(function () use ($asset, $maintenance, $data) {
            $maintenance->update([
                'status' => 'completed',
                'completed_date' => $data['completed_date'],
                'cost' => $data['cost'] ?? $maintenance->cost,
                'performed_by' => $data['performed_by'] ?? $maintenance->performed_by,
            ]);
            if (! empty($data['condition'])) {
                $asset->forceFill(['condition' => $data['condition']])->save();
            }
            $asset->settleStatus();
        });

        Audit::log('maintenance_completed', $asset, Status::label('maintenance_type', $maintenance->type));

        return back()->with('success', __('Maintenance completed.'));
    }

    public function cancel(Asset $asset, AssetMaintenance $maintenance): RedirectResponse
    {
        $this->authorize('update', $asset);

        if (! in_array($maintenance->status, ['scheduled', 'in_progress'], true)) {
            return back()->with('error', __('This maintenance record is already closed.'));
        }

        DB::transaction(function () use ($asset, $maintenance) {
            $maintenance->update(['status' => 'cancelled']);
            $asset->settleStatus();
        });

        return back()->with('success', __('Maintenance cancelled.'));
    }
}
