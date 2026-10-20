<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Printable inventory and allocation reports, computed from the register.
 */
class AssetReportController extends Controller
{
    public function inventory(Request $request): View
    {
        $this->authorize('viewAny', Asset::class);

        $assets = AssetController::filteredQuery($request)
            ->when(! $request->query('status'), fn ($q) => $q->where('status', '!=', 'disposed'))
            ->with(['category', 'department', 'assignee'])
            ->orderBy('asset_tag')
            ->get();

        $groups = $assets->groupBy(fn (Asset $a) => $a->category?->displayName() ?? __('Uncategorised'))
            ->sortKeys();

        return view('assets.reports.inventory', [
            'groups' => $groups,
            'total' => $assets->count(),
            'valueByCurrency' => $this->valueByCurrency($assets),
            ...AssetController::filterOptions(),
        ]);
    }

    public function allocation(Request $request): View
    {
        $this->authorize('viewAny', Asset::class);

        $assets = Asset::query()
            ->whereIn('status', ['assigned', 'under_maintenance', 'available', 'damaged'])
            ->when($request->query('department'), fn ($q, $v) => $q->where('department_id', $v))
            ->when($request->query('category'), fn ($q, $v) => $q->where('category_id', $v))
            ->with(['category', 'department', 'assignee', 'openAssignment'])
            ->orderBy('asset_tag')
            ->get();

        $assigned = $assets->filter(fn (Asset $a) => $a->openAssignment !== null);

        // Department => [holder name => assets]
        $byDepartment = $assigned
            ->groupBy(fn (Asset $a) => $a->department?->displayName() ?? __('No department'))
            ->sortKeys()
            ->map(fn (Collection $items) => $items
                ->groupBy(fn (Asset $a) => $a->assignee?->name ?? __('Department pool'))
                ->sortKeys());

        return view('assets.reports.allocation', [
            'byDepartment' => $byDepartment,
            'assignedCount' => $assigned->count(),
            'unassignedCount' => $assets->count() - $assigned->count(),
            ...AssetController::filterOptions(),
        ]);
    }

    /**
     * @param  Collection<int, Asset>  $assets
     * @return Collection<string, float>
     */
    private function valueByCurrency(Collection $assets): Collection
    {
        return $assets->filter(fn (Asset $a) => $a->purchase_cost !== null)
            ->groupBy('currency')
            ->map(fn (Collection $items) => $items->sum(fn (Asset $a) => (float) $a->purchase_cost))
            ->sortKeys();
    }
}
