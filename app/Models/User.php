<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, HasUserstamps, Notifiable, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_SUSPENDED];

    protected $fillable = [
        'name', 'email', 'password', 'status', 'locale', 'must_change_password',
        'password_changed_at', 'suspended_at', 'suspension_reason',
    ];

    protected $hidden = ['password', 'remember_token', 'mfa_secret', 'mfa_recovery_codes'];

    protected array $auditExclude = ['last_login_at', 'last_login_ip', 'remember_token'];

    /** @var array<string,bool>|null permission slugs cached for the request */
    private ?array $permissionCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'mfa_secret' => 'encrypted',
            'mfa_recovery_codes' => 'encrypted:array',
            'mfa_enabled_at' => 'datetime',
            'last_login_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withPivot(['assigned_by', 'assigned_at']);
    }

    public function staff(): HasOne
    {
        return $this->hasOne(Staff::class);
    }

    public function loginActivities(): HasMany
    {
        return $this->hasMany(LoginActivity::class)->latest('id');
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles->contains('slug', $slug);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    public function hasPermission(string $slug): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if ($this->permissionCache === null) {
            $this->loadMissing('roles.permissions');
            $this->permissionCache = $this->isSuperAdmin()
                ? array_fill_keys(Permission::allSlugs(), true)
                : $this->roles->flatMap(fn (Role $r) => $r->permissions->pluck('slug'))->unique()->flip()->map(fn () => true)->all();
        }

        return isset($this->permissionCache[$slug]);
    }

    /** @return list<string> */
    public function permissionSlugs(): array
    {
        $this->hasPermission('dashboard.view'); // warm cache

        return array_keys($this->permissionCache ?? []);
    }

    public function flushPermissionCache(): void
    {
        $this->permissionCache = null;
        $this->unsetRelation('roles');
    }

    /** Department of the linked staff record, if any. */
    public function departmentId(): ?int
    {
        return $this->staff?->department_id;
    }

    /** @var list<int>|null */
    private ?array $headedDepartmentsCache = null;

    /** IDs of departments this user is recorded as head of. */
    public function headedDepartmentIds(): array
    {
        return $this->headedDepartmentsCache ??= Department::query()->where('head_user_id', $this->id)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function hasMfaEnabled(): bool
    {
        return $this->mfa_enabled_at !== null && $this->mfa_secret !== null;
    }

    public function preferredLocale(): string
    {
        return $this->locale ?: config('app.locale');
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPassword($token));
    }
}
