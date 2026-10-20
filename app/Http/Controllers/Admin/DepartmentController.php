<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(Request $request): View
    {
        $query = Department::query()->with(['head', 'parent'])->withCount(['users', 'staff'])
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($q) => $q->where('name', 'like', "%{$t}%")->orWhere('code', 'like', "%{$t}%")))
            ->when($request->query('status') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($request->query('status') === 'active', fn ($q) => $q->where('is_active', true));
        $this->applySort($query, $request, ['name', 'code', 'created_at'], 'name', 'asc');

        return view('admin.departments.index', ['departments' => $query->paginate($this->perPage($request))->withQueryString()]);
    }

    public function create(): View
    {
        return view('admin.departments.form', $this->formData(new Department(['is_active' => true])));
    }

    public function store(Request $request): RedirectResponse
    {
        Department::query()->create($this->validated($request));

        return redirect()->route('admin.departments.index')->with('success', __('Department created.'));
    }

    public function edit(Department $department): View
    {
        return view('admin.departments.form', $this->formData($department));
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $data = $this->validated($request, $department);

        if (($data['parent_id'] ?? null) == $department->id) {
            return back()->withInput()->withErrors(['parent_id' => __('A department cannot be its own parent.')]);
        }

        $department->update($data);

        return redirect()->route('admin.departments.index')->with('success', __('Department updated.'));
    }

    public function destroy(Department $department): RedirectResponse
    {
        if ($department->users()->exists() || $department->staff()->exists() || $department->children()->exists()) {
            return back()->with('error', __('This department still has users, staff or sub-departments. Deactivate it instead.'));
        }

        $department->delete();

        return redirect()->route('admin.departments.index')->with('success', __('Department deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Department $department = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_so' => ['nullable', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('departments', 'code')->ignore($department?->id)],
            'parent_id' => ['nullable', Rule::exists('departments', 'id')],
            'head_user_id' => ['nullable', Rule::exists('users', 'id')],
            'description' => ['nullable', 'string', 'max:5000'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Department $department): array
    {
        return [
            'department' => $department,
            'parents' => collect(Department::options())->except($department->id)->all(),
            'users' => User::query()->active()->orderBy('name')->pluck('name', 'id'),
        ];
    }
}
