<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assets\MaintenanceRequest;
use App\Http\Requests\Assets\MovementRequest;
use App\Http\Requests\Assets\RetireRequest;
use App\Models\Asset;
use App\Models\AssetMaintenance;
use App\Services\Assets\AssetService;
use Illuminate\Http\RedirectResponse;

/** Assignment / transfer / return, maintenance and retirement of assets. */
class AssetActionController extends Controller
{
    public function __construct(private readonly AssetService $service) {}

    public function move(MovementRequest $request, Asset $asset): RedirectResponse
    {
        $this->service->move($asset, $request->validated(), $request->user());

        return redirect()->route('assets.show', $asset)->with('success', __('assets.moved'));
    }

    public function storeMaintenance(MaintenanceRequest $request, Asset $asset): RedirectResponse
    {
        $this->service->saveMaintenance($asset, $request->validated());

        return redirect()->to(route('assets.show', $asset).'#maintenance')->with('success', __('assets.maintenance_saved'));
    }

    public function updateMaintenance(MaintenanceRequest $request, Asset $asset, AssetMaintenance $maintenance): RedirectResponse
    {
        abort_unless($maintenance->asset_id === $asset->id, 404);
        $this->service->saveMaintenance($asset, $request->validated(), $maintenance);

        return redirect()->to(route('assets.show', $asset).'#maintenance')->with('success', __('assets.maintenance_saved'));
    }

    public function retire(RetireRequest $request, Asset $asset): RedirectResponse
    {
        $this->service->retire($asset, $request->validated(), $request->user());

        return redirect()->route('assets.show', $asset)->with('success', __('assets.retired_msg'));
    }
}
