<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Configurable roles and their permission matrix.
 */
class RoleController extends Controller
{
    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => Role::query()->withCount(['users', 'permissions'])->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.roles.form', ['role' => new Role, 'granted' => []]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);
        Audit::log('role_created', $role, $role->name, null, ['permissions' => $data['permissions'] ?? []]);

        return redirect()->route('admin.roles.index')->with('success', __('Role created.'));
    }

    public function edit(Role $role): View
    {
        abort_if($this->isSuperRole($role), 403, __('The Super Administrator role always has every permission.'));

        return view('admin.roles.form', [
            'role' => $role,
            'granted' => $role->permissions->pluck('name')->all(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_if($this->isSuperRole($role), 403);
        $data = $this->validated($request, $role);

        $before = $role->permissions->pluck('name')->sort()->values()->all();
        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $after = collect($data['permissions'] ?? [])->sort()->values()->all();
        Audit::log('role_updated', $role, $role->name, ['permissions' => array_values(array_diff($before, $after))], ['permissions' => array_values(array_diff($after, $before))]);

        return redirect()->route('admin.roles.index')->with('success', __('Role updated.'));
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_if($this->isSuperRole($role), 403);

        if ($role->users()->exists()) {
            return back()->with('error', __('This role is assigned to users and cannot be deleted.'));
        }

        Audit::log('role_deleted', $role, $role->name);
        $role->delete();

        return redirect()->route('admin.roles.index')->with('success', __('Role deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role?->id)],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(Permission::query()->pluck('name')->all())],
        ]);
    }

    private function isSuperRole(Role $role): bool
    {
        return $role->name === config('ndc.super_admin_role');
    }
}
