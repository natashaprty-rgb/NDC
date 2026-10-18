<?php

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\DepartmentRequest;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Department::class);

        $departments = Department::query()
            ->with(['parent', 'head'])
            ->withCount('staff')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->trim().'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('name_so', 'like', $term)->orWhere('code', 'like', $term));
            })
            ->when($request->filled('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('departments.index', compact('departments'));
    }

    public function create(): View
    {
        $this->authorize('create', Department::class);

        return view('departments.form', ['department' => new Department(['is_active' => true])] + $this->formOptions());
    }

    public function store(DepartmentRequest $request): RedirectResponse
    {
        $department = Department::query()->create($request->validated());

        return redirect()->route('departments.show', $department)->with('success', __('common.created'));
    }

    public function show(Department $department): View
    {
        $this->authorize('view', $department);

        $department->load(['parent', 'head', 'children']);
        $staff = $department->staff()->orderBy('full_name')->paginate(25);

        return view('departments.show', compact('department', 'staff'));
    }

    public function edit(Department $department): View
    {
        $this->authorize('update', $department);

        return view('departments.form', ['department' => $department] + $this->formOptions($department));
    }

    public function update(DepartmentRequest $request, Department $department): RedirectResponse
    {
        $department->update($request->validated());

        return redirect()->route('departments.show', $department)->with('success', __('common.updated'));
    }

    public function destroy(Department $department): RedirectResponse
    {
        $this->authorize('update', $department);
        if (! request()->user()->can('delete', $department)) {
            return back()->with('error', __('departments.cannot_delete'));
        }

        $department->delete();

        return redirect()->route('departments.index')->with('success', __('common.archived'));
    }

    private function formOptions(?Department $current = null): array
    {
        return [
            'parents' => Department::query()->when($current, fn ($q) => $q->whereKeyNot($current->id))->orderBy('name')->pluck('name', 'id')->all(),
            'users' => User::query()->active()->orderBy('name')->pluck('name', 'id')->all(),
        ];
    }
}
