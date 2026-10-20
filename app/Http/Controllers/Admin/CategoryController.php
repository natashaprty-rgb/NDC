<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Reference data: document types, asset/expense/supplier categories and
 * funding sources.
 */
class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $types = config('ndc.category_types');
        $type = array_key_exists($request->query('type'), $types) ? $request->query('type') : array_key_first($types);

        return view('admin.categories.index', [
            'types' => $types,
            'type' => $type,
            'categories' => Category::query()->ofType($type)->orderBy('sort_order')->orderBy('name')->get(),
            'editing' => $request->query('edit') ? Category::query()->ofType($type)->find($request->query('edit')) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        Category::query()->create($data);

        return redirect()->route('admin.categories.index', ['type' => $data['type']])->with('success', __('Item added.'));
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $this->validated($request, $category);
        $category->update($data);

        return redirect()->route('admin.categories.index', ['type' => $category->type])->with('success', __('Item updated.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Category $category = null): array
    {
        $type = $category?->type ?? $request->input('type');

        $data = $request->validate([
            'type' => [$category ? 'nullable' : 'required', Rule::in(array_keys(config('ndc.category_types')))],
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->where('type', $type)->ignore($category?->id)],
            'name_so' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ]);
        $data['type'] = $type;
        $data['sort_order'] ??= 0;

        return $data;
    }
}
