<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with('roles')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->trim().'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('role'), fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('roles.id', $request->integer('role'))))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => Role::query()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.form', ['target' => new User(['locale' => 'en']), 'roles' => $this->assignableRoles($request->user()), 'selected' => []]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $roleIds = $this->checkedRoleIds($request->user(), $data['roles'] ?? [], []);
        $generated = $data['password_mode'] === 'generate' ? Str::password(14) : null;

        $user = DB::transaction(function () use ($data, $generated, $roleIds, $request) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'locale' => $data['locale'],
                'password' => $generated ?? $data['password'],
                'status' => User::STATUS_ACTIVE,
                'must_change_password' => true,
            ]);
            $this->syncRoles($user, $roleIds, $request->user());

            return $user;
        });

        $redirect = redirect()->route('admin.users.show', $user)->with('success', __('common.created'));

        return $generated ? $redirect->with('temporary_password', $generated) : $redirect;
    }

    public function show(User $user): View
    {
        $this->authorize('view', $user);
        $user->load(['roles', 'staff.department']);

        return view('admin.users.show', [
            'target' => $user,
            'logins' => $user->loginActivities()->limit(15)->get(),
        ]);
    }

    public function edit(Request $request, User $user): View
    {
        $this->authorize('update', $user);

        return view('admin.users.form', [
            'target' => $user,
            'roles' => $this->assignableRoles($request->user()),
            'selected' => $user->roles->pluck('id')->all(),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $current = $user->roles()->pluck('roles.id')->all();
        $requested = array_map('intval', $data['roles'] ?? []);

        $changed = array_diff($requested, $current) || array_diff($current, $requested);
        if ($changed && ! $request->user()->can('assignRoles', $user)) {
            throw ValidationException::withMessages(['roles' => __('users.cannot_change_own_roles')]);
        }
        $roleIds = $this->checkedRoleIds($request->user(), $requested, $current);

        DB::transaction(function () use ($user, $data, $roleIds, $request) {
            $user->update(['name' => $data['name'], 'email' => $data['email'], 'locale' => $data['locale']]);
            $this->syncRoles($user, $roleIds, $request->user());
        });

        return redirect()->route('admin.users.show', $user)->with('success', __('common.updated'));
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        $this->authorize('suspend', $user);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], [], ['reason' => __('common.reason')]);

        $user->update(['status' => User::STATUS_SUSPENDED, 'suspended_at' => now(), 'suspension_reason' => $data['reason']]);
        AuditLogger::log('account_suspended', $user, 'Account suspended: '.$data['reason']);

        return back()->with('success', __('users.suspended'));
    }

    public function activate(User $user): RedirectResponse
    {
        $this->authorize('suspend', $user);

        $user->update(['status' => User::STATUS_ACTIVE, 'suspended_at' => null, 'suspension_reason' => null]);
        AuditLogger::log('account_activated', $user, 'Account activated');

        return back()->with('success', __('users.activated'));
    }

    public function resetPassword(User $user): RedirectResponse
    {
        $this->authorize('resetPassword', $user);

        $temporary = Str::password(14);
        $user->forceFill([
            'password' => $temporary,
            'must_change_password' => true,
            'remember_token' => Str::random(60),
        ])->save();
        AuditLogger::log('password_reset', $user, 'Temporary password issued by administrator');

        return back()->with('temporary_password', $temporary)->with('success', __('users.password_reset_done'));
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);
        $user->update(['status' => User::STATUS_SUSPENDED, 'suspended_at' => now()]);
        $user->delete();

        return redirect()->route('admin.users.index')->with('success', __('common.archived'));
    }

    /** Roles the acting user may grant (super-admin only by a super admin). */
    private function assignableRoles(User $actor)
    {
        return Role::query()->orderBy('name')->get()
            ->reject(fn (Role $r) => $r->slug === 'super-admin' && ! $actor->can('grantSuperAdmin', User::class));
    }

    /**
     * Enforce server side that the super-admin role is neither granted nor
     * removed by someone who is not a super admin.
     */
    private function checkedRoleIds(User $actor, array $requested, array $current): array
    {
        $requested = array_values(array_unique(array_map('intval', $requested)));
        $superId = Role::query()->where('slug', 'super-admin')->value('id');

        if ($superId && ! $actor->can('grantSuperAdmin', User::class)) {
            $had = in_array($superId, $current, true);
            $wants = in_array($superId, $requested, true);
            if ($had !== $wants) {
                throw ValidationException::withMessages(['roles' => __('users.super_admin_only')]);
            }
        }

        return $requested;
    }

    private function syncRoles(User $user, array $roleIds, User $actor): void
    {
        $old = $user->roles()->pluck('slug')->sort()->values()->all();
        $current = $user->roles()->pluck('roles.id')->all();

        $attach = array_diff($roleIds, $current);
        $detach = array_diff($current, $roleIds);
        if ($detach) {
            $user->roles()->detach($detach);
        }
        foreach ($attach as $id) {
            $user->roles()->attach($id, ['assigned_by' => $actor->id, 'assigned_at' => now()]);
        }

        if ($attach || $detach) {
            $new = $user->roles()->pluck('slug')->sort()->values()->all();
            AuditLogger::log('role_changed', $user, 'Roles changed', ['roles' => $old], ['roles' => $new]);
            $user->flushPermissionCache();
        }
    }
}
