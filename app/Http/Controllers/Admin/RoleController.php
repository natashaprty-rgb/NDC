<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Role::class);

        return view('admin.roles.index', [
            'roles' => Role::query()->withCount(['users', 'permissions'])->orderByDesc('is_system')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Role::class);

        return view('admin.roles.form', ['role' => new Role, 'granted' => [], 'groups' => $this->groups()]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $slug = Str::slug($data['name']);
        if ($slug === '' || Role::query()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['name' => __('roles.slug_taken')]);
        }

        $role = DB::transaction(function () use ($data, $slug) {
            $role = Role::query()->create(['slug' => $slug, 'name' => $data['name'], 'description' => $data['description'] ?? null, 'is_system' => false]);
            $this->syncPermissions($role, $data['permissions'] ?? []);

            return $role;
        });

        return redirect()->route('admin.roles.show', $role)->with('success', __('common.created'));
    }

    public function show(Role $role): View
    {
        $this->authorize('view', $role);
        $role->load('permissions');

        return view('admin.roles.show', [
            'role' => $role,
            'granted' => $role->permissions->pluck('slug')->all(),
            'groups' => $this->groups(),
            'users' => $role->users()->orderBy('name')->get(),
        ]);
    }

    public function edit(Role $role): View
    {
        $this->authorize('update', $role);

        return view('admin.roles.form', ['role' => $role, 'granted' => $role->permissions()->pluck('slug')->all(), 'groups' => $this->groups()]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($role, $data) {
            $role->update(['name' => $data['name'], 'description' => $data['description'] ?? null]);
            $this->syncPermissions($role, $data['permissions'] ?? []);
        });

        return redirect()->route('admin.roles.show', $role)->with('success', __('common.updated'));
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);
        $role->delete();

        return redirect()->route('admin.roles.index')->with('success', __('common.deleted'));
    }

    private function syncPermissions(Role $role, array $slugs): void
    {
        $old = $role->permissions()->pluck('slug')->sort()->values()->all();
        $role->permissions()->sync(Permission::query()->whereIn('slug', $slugs)->pluck('id'));
        $new = $role->permissions()->pluck('slug')->sort()->values()->all();

        if ($old !== $new) {
            AuditLogger::log('updated', $role, 'Permissions changed', ['permissions' => $old], ['permissions' => $new]);
        }
    }

    /** @return array<string, array<string,string>> */
    private function groups(): array
    {
        return config('ndc_permissions.permissions');
    }
}
