<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Department;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Asset register (inventory) and asset records.
 */
class AssetController extends Controller
{
    /**
     * Warranty filter choices: "expiring within N days", still valid, expired.
     */
    public const WARRANTY_FILTERS = ['30', '60', '90', 'active', 'expired', 'none'];

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Asset::class);

        $query = self::filteredQuery($request)->with(['category', 'department', 'assignee']);
        $this->applySort($query, $request, ['asset_tag', 'name', 'status', 'condition', 'purchase_date', 'warranty_expiry', 'created_at'], 'asset_tag', 'asc');

        return view('assets.index', [
            'assets' => $query->paginate($this->perPage($request))->withQueryString(),
            'summary' => Asset::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            ...self::filterOptions(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Asset::class);

        return view('assets.form', $this->formData(new Asset([
            'condition' => 'new',
            'currency' => setting('currency', 'USD'),
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Asset::class);
        $data = $this->validated($request);

        $asset = DB::transaction(function () use ($data, $request) {
            $asset = new Asset([
                ...$data,
                'asset_tag' => $data['asset_tag'] ?: Asset::nextTag(),
                'created_by' => $request->user()->id,
            ]);
            $asset->forceFill(['status' => $data['condition'] === 'damaged' ? 'damaged' : 'available'])->save();

            return $asset;
        });

        return redirect()->route('assets.show', $asset)->with('success', __('Asset registered with tag :tag.', ['tag' => $asset->asset_tag]));
    }

    public function show(Asset $asset): View
    {
        $this->authorize('view', $asset);

        $asset->load([
            'category', 'supplier', 'department', 'assignee', 'creator', 'attachments.uploader',
            'assignments.user', 'assignments.department', 'assignments.assigner',
            'maintenances.supplier', 'disposals.requester',
        ]);

        return view('assets.show', [
            'asset' => $asset,
            'openAssignment' => $asset->openAssignment()->with(['user', 'department'])->first(),
            'openDisposal' => $asset->openDisposal()->first(),
            'latestDisposal' => $asset->disposals->first(),
            'users' => User::query()->active()->orderBy('name')->pluck('name', 'id'),
            'departments' => Department::options(),
            'suppliers' => Supplier::options(),
            'warrantyDays' => (int) setting('warranty_reminder_days', 30),
        ]);
    }

    public function edit(Asset $asset): View
    {
        $this->authorize('update', $asset);

        return view('assets.form', $this->formData($asset));
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorize('update', $asset);
        $data = $this->validated($request, $asset);

        DB::transaction(function () use ($asset, $data) {
            $asset->update([...$data, 'asset_tag' => $data['asset_tag'] ?: $asset->asset_tag]);
            $asset->settleStatus();
        });

        return redirect()->route('assets.show', $asset)->with('success', __('Asset updated.'));
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        $this->authorize('delete', $asset);

        if ($asset->openAssignment()->exists()) {
            return back()->with('error', __('Return the asset before deleting it.'));
        }

        $asset->delete();

        return redirect()->route('assets.index')->with('success', __('Asset deleted.'));
    }

    /**
     * The register query with the list filters applied; shared with the
     * printable inventory report.
     */
    public static function filteredQuery(Request $request): Builder
    {
        return Asset::query()
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('asset_tag', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%")
                ->orWhere('serial_number', 'like', "%{$term}%")
                ->orWhere('manufacturer', 'like', "%{$term}%")
                ->orWhere('model', 'like', "%{$term}%")
                ->orWhere('location', 'like', "%{$term}%")))
            ->when($request->query('category'), fn ($q, $v) => $q->where('category_id', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('department'), fn ($q, $v) => $q->where('department_id', $v))
            ->when($request->query('assigned'), fn ($q, $v) => $q->where('assigned_to', $v))
            ->when($request->query('condition'), fn ($q, $v) => $q->where('condition', $v))
            ->when($request->query('supplier'), fn ($q, $v) => $q->where('supplier_id', $v))
            ->when($request->query('warranty'), function ($q, $v) {
                match ((string) $v) {
                    '30', '60', '90' => $q->warrantyExpiringWithin((int) $v),
                    'active' => $q->whereDate('warranty_expiry', '>=', today()),
                    'expired' => $q->whereDate('warranty_expiry', '<', today()),
                    'none' => $q->whereNull('warranty_expiry'),
                    default => null,
                };
            });
    }

    /**
     * @return array<string, mixed>
     */
    public static function filterOptions(): array
    {
        return [
            'categories' => Category::options('asset'),
            'departments' => Department::options(),
            'users' => User::query()->whereIn('id', Asset::query()->whereNotNull('assigned_to')->select('assigned_to'))
                ->orderBy('name')->pluck('name', 'id'),
            'warrantyOptions' => [
                '30' => __('Expiring within :days days', ['days' => 30]),
                '60' => __('Expiring within :days days', ['days' => 60]),
                '90' => __('Expiring within :days days', ['days' => 90]),
                'active' => __('Under warranty'),
                'expired' => __('Warranty expired'),
                'none' => __('No warranty recorded'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Asset $asset = null): array
    {
        $data = $request->validate([
            'asset_tag' => ['nullable', 'string', 'max:40', Rule::unique('assets', 'asset_tag')->ignore($asset?->id)],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('type', 'asset')],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'purchase_date' => ['nullable', 'date', 'before_or_equal:today'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'warranty_expiry' => array_filter(['nullable', 'date', $request->filled('purchase_date') ? 'after_or_equal:purchase_date' : null]),
            'location' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', Rule::exists('departments', 'id')],
            'condition' => ['required', Rule::in(Status::keys('asset_condition'))],
            'notes' => ['nullable', 'string', 'max:5000'],
            'specifications' => ['nullable', 'array', 'max:50'],
            'specifications.*.key' => ['nullable', 'string', 'max:100'],
            'specifications.*.value' => ['nullable', 'string', 'max:500'],
        ]);

        $data['currency'] = strtoupper($data['currency']);
        $data['asset_tag'] = isset($data['asset_tag']) ? trim($data['asset_tag']) : null;
        $data['specifications'] = self::specificationsFromInput($data['specifications'] ?? []);

        return $data;
    }

    /**
     * Turn repeater rows into a "label => value" array, ignoring blank labels.
     *
     * @param  array<int|string, array{key?: ?string, value?: ?string}>  $rows
     * @return array<string, string>|null
     */
    public static function specificationsFromInput(array $rows): ?array
    {
        $specs = [];
        foreach ($rows as $row) {
            $key = trim((string) ($row['key'] ?? ''));
            if ($key !== '') {
                $specs[$key] = trim((string) ($row['value'] ?? ''));
            }
        }

        return $specs ?: null;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Asset $asset): array
    {
        $suppliers = Supplier::options();
        if ($asset->supplier_id && ! isset($suppliers[$asset->supplier_id]) && $asset->supplier) {
            $suppliers[$asset->supplier_id] = $asset->supplier->name;
        }

        return [
            'asset' => $asset,
            'categories' => Category::options('asset', $asset->category_id),
            'departments' => Department::options(),
            'suppliers' => $suppliers,
        ];
    }
}
