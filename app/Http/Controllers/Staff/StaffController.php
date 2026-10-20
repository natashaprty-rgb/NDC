<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Staff;
use App\Models\Task;
use App\Models\User;
use App\Support\ReferenceNumber;
use App\Support\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Staff profiles and the staff directory (SRS 3.3).
 *
 * Sensitive personal fields (Staff::SENSITIVE) are only validated, saved and
 * displayed for users holding staff.view-sensitive; for everybody else they
 * are left out of forms and ignored on update, so they are never wiped.
 */
class StaffController extends Controller
{
    private const SORTABLE = ['staff_number', 'full_name', 'job_title', 'employment_status', 'employment_type', 'hire_date', 'created_at'];

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Staff::class);

        $query = $this->filtered(Staff::query()->with(['department', 'user']), $request);
        $this->applySort($query, $request, self::SORTABLE, 'full_name', 'asc');

        $department = $request->filled('department')
            ? Department::query()->with('head')->withCount('staff')->find($request->integer('department'))
            : null;

        return view('staff.index', [
            'staff' => $query->paginate($this->perPage($request))->withQueryString(),
            'departments' => Department::options(),
            'department' => $department,
        ]);
    }

    /**
     * Printable staff directory grouped by department.
     */
    public function directory(Request $request): View
    {
        $this->authorize('viewAny', Staff::class);

        $staff = $this->filtered(Staff::query()->with(['department.head']), $request)
            ->orderBy('full_name')
            ->get();

        $groups = $staff->groupBy(fn (Staff $s) => $s->department_id ?? 0)
            ->sortBy(fn ($members, $id) => $id === 0 ? "\u{FFFF}" : mb_strtolower($members->first()->department?->displayName() ?? ''));

        return view('staff.directory', [
            'groups' => $groups,
            'total' => $staff->count(),
            'departments' => Department::options(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Staff::class);

        return view('staff.form', $this->formData(new Staff([
            'employment_status' => 'active',
            'employment_type' => 'permanent',
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Staff::class);

        $data = $this->validated($request);
        $data['staff_number'] = filled($data['staff_number'] ?? null) ? $data['staff_number'] : ReferenceNumber::next('STF');
        $data['created_by'] = $request->user()->id;

        $staff = Staff::query()->create($data);

        return redirect()->route('staff.show', $staff)->with('success', __('Staff profile created.'));
    }

    public function show(Request $request, Staff $staff): View
    {
        $this->authorize('view', $staff);
        $user = $request->user();

        $staff->load(['department.head', 'supervisor', 'user', 'trainings']);

        $canSeeFiles = $user->can('viewAttachments', $staff);
        if ($canSeeFiles) {
            $staff->load('attachments.uploader');
        }

        $tasks = collect();
        if ($staff->user_id && $user->can('tasks.view')) {
            $tasks = Task::query()->with('project')
                ->visibleTo($user)
                ->where('assigned_to', $staff->user_id)
                ->orderByRaw('case when due_date is null then 1 else 0 end')
                ->orderByDesc('due_date')
                ->limit(25)
                ->get();
        }

        return view('staff.show', [
            'staff' => $staff,
            'canSeeSensitive' => $user->can('viewSensitive', $staff),
            'canSeeFiles' => $canSeeFiles,
            'tasks' => $tasks,
            'directReports' => Staff::query()->where('supervisor_id', $staff->id)->orderBy('full_name')->get(['id', 'full_name', 'job_title', 'staff_number']),
        ]);
    }

    public function edit(Staff $staff): View
    {
        $this->authorize('update', $staff);

        return view('staff.form', $this->formData($staff));
    }

    public function update(Request $request, Staff $staff): RedirectResponse
    {
        $this->authorize('update', $staff);

        $staff->update($this->validated($request, $staff));

        return redirect()->route('staff.show', $staff)->with('success', __('Staff profile updated.'));
    }

    public function destroy(Staff $staff): RedirectResponse
    {
        $this->authorize('delete', $staff);

        DB::transaction(function () use ($staff) {
            // Release the user account so it can be linked to another profile.
            if ($staff->user_id !== null) {
                $staff->update(['user_id' => null]);
            }
            $staff->delete();
        });

        return redirect()->route('staff.index')->with('success', __('Staff record deleted.'));
    }

    /**
     * Directory filters shared by the list and the printable directory.
     */
    private function filtered(Builder $query, Request $request): Builder
    {
        return $query
            ->search($request->query('q'))
            ->when($request->query('department'), fn (Builder $q, $d) => $q->where('department_id', $d))
            ->when($request->query('status'), fn (Builder $q, $s) => $q->where('employment_status', $s))
            ->when($request->query('type'), fn (Builder $q, $t) => $q->where('employment_type', $t));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Staff $staff = null): array
    {
        $rules = [
            'staff_number' => [$staff ? 'required' : 'nullable', 'string', 'max:40', Rule::unique('staff', 'staff_number')->ignore($staff?->id)],
            'full_name' => ['required', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'supervisor_id' => array_filter(['nullable', 'integer', Rule::exists('staff', 'id')->whereNull('deleted_at'), $staff ? Rule::notIn([$staff->id]) : null]),
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id'), Rule::unique('staff', 'user_id')->ignore($staff?->id)],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'employment_status' => ['required', Rule::in(Status::keys('employment'))],
            'employment_type' => ['required', Rule::in(Status::keys('employment_type'))],
            'hire_date' => ['nullable', 'date'],
            'end_date' => array_filter(['nullable', 'date', $request->filled('hire_date') ? 'after_or_equal:hire_date' : null]),
            'gender' => ['nullable', Rule::in(array_keys(Staff::GENDERS))],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        if ($request->user()->can('viewSensitive', Staff::class)) {
            $rules += [
                'date_of_birth' => ['nullable', 'date', 'before:today'],
                'national_id' => ['nullable', 'string', 'max:50'],
                'personal_phone' => ['nullable', 'string', 'max:50'],
                'address' => ['nullable', 'string', 'max:1000'],
                'emergency_contact' => ['nullable', 'string', 'max:500'],
            ];
        }

        $data = $request->validate($rules, [
            'supervisor_id.not_in' => __('A staff member cannot be their own supervisor.'),
            'user_id.unique' => __('This user account is already linked to another staff profile.'),
        ]);

        if (! empty($data['date_of_birth'])) {
            $data['date_of_birth'] = date('Y-m-d', strtotime($data['date_of_birth']));
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Staff $staff): array
    {
        $linkedUserIds = Staff::withTrashed()->whereNotNull('user_id')
            ->when($staff->exists, fn (Builder $q) => $q->whereKeyNot($staff->id))
            ->pluck('user_id');

        return [
            'staff' => $staff,
            'departments' => Department::options(),
            'supervisors' => Staff::options($staff->id),
            'users' => User::query()->whereNotIn('id', $linkedUserIds)
                ->where(fn (Builder $q) => $q->active()->when($staff->user_id, fn (Builder $q) => $q->orWhere('id', $staff->user_id)))
                ->orderBy('name')->get(['id', 'name', 'email'])
                ->mapWithKeys(fn (User $u) => [$u->id => $u->name.' <'.$u->email.'>']),
            'canEditSensitive' => auth()->user()->can('viewSensitive', Staff::class),
        ];
    }
}
