<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssetCategory;
use App\Models\DocumentCategory;
use App\Models\ExpenseCategory;
use App\Models\FundingSource;
use App\Models\Location;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Reference lists (categories, locations, funding sources). Entries are
 * deactivated rather than deleted so historical records stay intact.
 */
class LookupController extends Controller
{
    public static function types(): array
    {
        return [
            'document-categories' => ['model' => DocumentCategory::class, 'table' => 'document_categories', 'fields' => ['code', 'name', 'name_so', 'retention_years', 'description']],
            'expense-categories' => ['model' => ExpenseCategory::class, 'table' => 'expense_categories', 'fields' => ['code', 'name', 'name_so', 'description']],
            'asset-categories' => ['model' => AssetCategory::class, 'table' => 'asset_categories', 'fields' => ['code', 'name', 'name_so', 'asset_class', 'description']],
            'locations' => ['model' => Location::class, 'table' => 'locations', 'fields' => ['code', 'name', 'building', 'floor', 'room', 'description']],
            'funding-sources' => ['model' => FundingSource::class, 'table' => 'funding_sources', 'fields' => ['code', 'name', 'type', 'description']],
        ];
    }

    public function index(string $type): View
    {
        $config = $this->config($type);

        return view('admin.lookups.index', [
            'type' => $type,
            'types' => array_keys(self::types()),
            'fields' => $config['fields'],
            'items' => $config['model']::query()->orderByDesc('is_active')->orderBy('name')->paginate(50),
        ]);
    }

    public function create(string $type): View
    {
        $config = $this->config($type);

        return view('admin.lookups.form', ['type' => $type, 'fields' => $config['fields'], 'item' => new $config['model'](['is_active' => true])]);
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $config = $this->config($type);
        $config['model']::query()->create($this->validated($request, $type));

        return redirect()->route('admin.lookups.index', $type)->with('success', __('common.created'));
    }

    public function edit(string $type, int $id): View
    {
        $config = $this->config($type);

        return view('admin.lookups.form', ['type' => $type, 'fields' => $config['fields'], 'item' => $config['model']::query()->findOrFail($id)]);
    }

    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        $config = $this->config($type);
        /** @var Model $item */
        $item = $config['model']::query()->findOrFail($id);
        $item->update($this->validated($request, $type, $item));

        return redirect()->route('admin.lookups.index', $type)->with('success', __('common.updated'));
    }

    private function validated(Request $request, string $type, ?Model $item = null): array
    {
        $config = $this->config($type);
        $rules = [
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique($config['table'], 'code')->ignore($item?->getKey())],
            'name' => ['required', 'string', 'max:150'],
            'name_so' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'retention_years' => ['nullable', 'integer', 'min:1', 'max:100'],
            'asset_class' => ['required', Rule::in(AssetCategory::CLASSES)],
            'type' => ['required', Rule::in(FundingSource::TYPES)],
            'building' => ['nullable', 'string', 'max:150'],
            'floor' => ['nullable', 'string', 'max:30'],
            'room' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ];
        $data = $request->validate(array_intersect_key($rules, array_flip([...$config['fields'], 'is_active'])));
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function config(string $type): array
    {
        return self::types()[$type] ?? abort(404);
    }
}
