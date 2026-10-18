<?php

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\StaffRequest;
use App\Models\Asset;
use App\Models\Department;
use App\Models\Staff;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Staff::class);
        $sensitive = $request->user()->hasPermission('staff.view_sensitive') || $request->user()->hasPermission('staff.manage');

        $staff = Staff::query()
            ->with('department')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->trim().'%';
                $q->where(fn ($w) => $w->where('full_name', 'like', $term)->orWhere('staff_number', 'like', $term)
                    ->orWhere('job_title', 'like', $term)->orWhere('official_email', 'like', $term));
            })
            ->when($request->filled('department'), fn ($q) => $q->where('department_id', $request->integer('department')))
            ->when($sensitive && $request->filled('status'), fn ($q) => $q->where('employment_status', $request->string('status')))
            ->orderBy('full_name')
            ->paginate(25)
            ->withQueryString();

        return view('staff.index', [
            'staff' => $staff,
            'sensitive' => $sensitive,
            'departments' => Department::query()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Staff::class);

        return view('staff.form', ['member' => new Staff(['employment_status' => 'active', 'employment_type' => 'permanent'])] + $this->formOptions());
    }

    public function store(StaffRequest $request): RedirectResponse
    {
        $member = Staff::query()->create($request->validated());

        return redirect()->route('staff.show', $member)->with('success', __('common.created'));
    }

    public function show(Request $request, Staff $staff): View
    {
        $this->authorize('view', $staff);
        $staff->load(['department', 'user']);

        $tasks = $staff->user_id
            ? Task::query()->where('assigned_to', $staff->user_id)->open()->with('project')->orderBy('due_date')->limit(20)->get()
            : collect();
        $assets = Asset::query()->where('assigned_staff_id', $staff->id)->with('category')->orderBy('asset_tag')->get();

        return view('staff.show', [
            'member' => $staff,
            'tasks' => $tasks,
            'assets' => $assets,
            'canSensitive' => $request->user()->can('viewSensitive', $staff),
        ]);
    }

    public function edit(Staff $staff): View
    {
        $this->authorize('update', $staff);

        return view('staff.form', ['member' => $staff] + $this->formOptions($staff));
    }

    public function update(StaffRequest $request, Staff $staff): RedirectResponse
    {
        $staff->update($request->validated());

        return redirect()->route('staff.show', $staff)->with('success', __('common.updated'));
    }

    public function destroy(Staff $staff): RedirectResponse
    {
        $this->authorize('delete', $staff);
        $staff->delete();

        return redirect()->route('staff.index')->with('success', __('common.archived'));
    }

    private function formOptions(?Staff $current = null): array
    {
        // Only user accounts that are not yet linked to another staff record.
        $linked = Staff::query()->whereNotNull('user_id')->when($current, fn ($q) => $q->whereKeyNot($current->id))->pluck('user_id');

        return [
            'departments' => Department::query()->orderBy('name')->pluck('name', 'id')->all(),
            'users' => User::query()->whereNotIn('id', $linked)->orderBy('name')->get()->mapWithKeys(fn ($u) => [$u->id => $u->name.' ('.$u->email.')'])->all(),
        ];
    }
}
