<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use App\Support\Audit;
use App\Support\Status;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::query()->with(['roles', 'department'])
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('role'), fn ($q, $r) => $q->role($r))
            ->when($request->query('department'), fn ($q, $d) => $q->where('department_id', $d));

        $this->applySort($query, $request, ['name', 'email', 'status', 'last_login_at', 'created_at'], 'name', 'asc');

        return view('admin.users.index', [
            'users' => $query->paginate($this->perPage($request))->withQueryString(),
            'roles' => Role::query()->orderBy('name')->pluck('name', 'name')->map(fn ($n) => __($n)),
            'departments' => Department::options(),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.form', $this->formData(new User(['status' => 'active', 'locale' => 'en'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $user = DB::transaction(function () use ($data, $request) {
            $user = User::query()->create([
                ...collect($data)->except(['roles', 'password', 'send_reset'])->all(),
                'password' => $data['password'],
                'must_change_password' => true,
            ]);
            $user->forceFill(['password_changed_at' => now()])->save();
            $this->syncRoles($request, $user, $data['roles'] ?? []);

            return $user;
        });

        if ($request->boolean('send_reset')) {
            Password::sendResetLink(['email' => $user->email]);
        }

        return redirect()->route('admin.users.show', $user)->with('success', __('User account created.'));
    }

    public function show(User $user): View
    {
        $user->load(['roles', 'department', 'staffProfile']);

        return view('admin.users.show', [
            'user' => $user,
            'activity' => AuditLog::query()->where('user_id', $user->id)->latest('created_at')->limit(20)->get(),
        ]);
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', $this->formData($user));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        DB::transaction(function () use ($data, $request, $user) {
            $attributes = collect($data)->except(['roles', 'password', 'send_reset'])->all();
            if (! empty($data['password'])) {
                $attributes['password'] = $data['password'];
                $attributes['must_change_password'] = true;
            }
            $this->guardSelfLockout($request, $user, $attributes['status'] ?? $user->status, $data['roles'] ?? []);
            $user->update($attributes);
            $this->syncRoles($request, $user, $data['roles'] ?? []);
        });

        return redirect()->route('admin.users.show', $user)->with('success', __('User account updated.'));
    }

    /**
     * Activate, suspend or deactivate an account.
     */
    public function status(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(Status::keys('user'))]]);
        $this->guardSelfLockout($request, $user, $data['status'], $user->getRoleNames()->all());

        $user->update(['status' => $data['status']]);

        if ($data['status'] !== 'active') {
            // End any open sessions and revoke API tokens.
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            $user->tokens()->delete();
        }

        return back()->with('success', __('Account status changed to :status.', ['status' => Status::label('user', $data['status'])]));
    }

    public function sendReset(User $user): RedirectResponse
    {
        Password::sendResetLink(['email' => $user->email]);
        Audit::log('password_reset_sent', $user);

        return back()->with('success', __('A password reset link has been sent to :email.', ['email' => $user->email]));
    }

    public function resetTwoFactor(User $user): RedirectResponse
    {
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        Audit::log('two_factor_reset', $user);

        return back()->with('success', __('Two-factor authentication has been reset for this user.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'department_id' => ['nullable', Rule::exists('departments', 'id')],
            'job_title' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::in(Status::keys('user'))],
            'locale' => ['required', Rule::in(array_keys(config('ndc.locales')))],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', PasswordRule::defaults()],
            'roles' => ['array'],
            'roles.*' => [Rule::exists('roles', 'name')],
            'send_reset' => ['boolean'],
        ]);
    }

    /**
     * Only a Super Administrator may grant or remove the Super Administrator role.
     *
     * @param  list<string>  $roles
     */
    private function syncRoles(Request $request, User $user, array $roles): void
    {
        $super = config('ndc.super_admin_role');
        $actorIsSuper = $request->user()->isSuperAdmin();

        if (! $actorIsSuper) {
            $roles = array_values(array_diff($roles, [$super]));
            if ($user->hasRole($super)) {
                $roles[] = $super;
            }
        }

        $before = $user->getRoleNames()->sort()->values()->all();
        $user->syncRoles($roles);
        $after = $user->getRoleNames()->sort()->values()->all();

        if ($before !== $after) {
            Audit::log('roles_changed', $user, null, ['roles' => $before], ['roles' => $after]);
        }
    }

    /**
     * Prevent administrators from suspending themselves or removing their own
     * administrative access.
     *
     * @param  list<string>  $roles
     */
    private function guardSelfLockout(Request $request, User $user, string $status, array $roles): void
    {
        if (! $request->user()->is($user)) {
            return;
        }

        if ($status !== 'active') {
            abort(422, __('You cannot deactivate your own account.'));
        }

        $keepsAccess = collect($roles)->contains(fn ($name) => $name === config('ndc.super_admin_role')
            || Role::findByName($name)->hasPermissionTo('users.manage'));
        if (! $keepsAccess) {
            abort(422, __('You cannot remove your own user-management access.'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(User $user): array
    {
        $roles = Role::query()->orderBy('name')->pluck('name');
        if (! auth()->user()->isSuperAdmin()) {
            $roles = $roles->reject(fn ($r) => $r === config('ndc.super_admin_role'));
        }

        return [
            'user' => $user,
            'roles' => $roles,
            'departments' => Department::options(),
        ];
    }
}
