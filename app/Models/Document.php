<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use Auditable, HasFactory, HasUserstamps, SoftDeletes;

    public const CONFIDENTIALITY = ['public', 'internal', 'confidential', 'restricted'];

    public const STATUSES = ['draft', 'pending_review', 'approved', 'rejected', 'archived'];

    protected $fillable = [
        'reference', 'title', 'category_id', 'description', 'department_id', 'project_id', 'owner_id',
        'registration_date', 'confidentiality', 'status', 'approved_by', 'approved_at', 'review_note',
        'review_date', 'expiry_date', 'retain_until', 'archived_at', 'current_version',
    ];

    protected function casts(): array
    {
        return [
            'registration_date' => 'date',
            'approved_at' => 'datetime',
            'review_date' => 'date',
            'expiry_date' => 'date',
            'retain_until' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'category_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withTrashed();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id')->withTrashed();
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('version_number');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class)->ofMany('version_number', 'max');
    }

    public function accessGrants(): HasMany
    {
        return $this->hasMany(DocumentAccess::class);
    }

    /**
     * Documents the user may see. Mirrors canBeViewedBy() so lists and
     * direct URLs apply exactly the same rules.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $canConfidential = $user->hasPermission('documents.view_confidential');
        $canRestricted = $user->hasPermission('documents.view_restricted');
        $deptId = $user->departmentId();

        return $query->where(function (Builder $q) use ($user, $canConfidential, $canRestricted, $deptId) {
            $q->whereIn('confidentiality', ['public', 'internal'])
                ->orWhere('owner_id', $user->id)
                ->orWhere('created_by', $user->id)
                ->orWhereHas('accessGrants', function (Builder $g) use ($user, $deptId) {
                    $g->where('user_id', $user->id);
                    if ($deptId) {
                        $g->orWhere('department_id', $deptId);
                    }
                });

            if ($canConfidential) {
                $q->orWhere('confidentiality', 'confidential');
            } elseif ($deptId) {
                $q->orWhere(fn (Builder $c) => $c->where('confidentiality', 'confidential')->where('department_id', $deptId));
            }

            if ($canRestricted) {
                $q->orWhere('confidentiality', 'restricted');
            }
        });
    }

    public function canBeViewedBy(User $user): bool
    {
        if (in_array($this->confidentiality, ['public', 'internal'], true)) {
            return true;
        }
        if ($this->owner_id === $user->id || $this->created_by === $user->id || $this->hasGrantFor($user)) {
            return true;
        }
        if ($this->confidentiality === 'confidential') {
            return $user->hasPermission('documents.view_confidential')
                || ($this->department_id !== null && $this->department_id === $user->departmentId());
        }

        return $this->confidentiality === 'restricted' && $user->hasPermission('documents.view_restricted');
    }

    public function hasGrantFor(User $user, ?string $level = null): bool
    {
        $deptId = $user->departmentId();

        return $this->accessGrants
            ->filter(fn (DocumentAccess $g) => $g->user_id === $user->id || ($deptId && $g->department_id === $deptId))
            ->when($level === 'edit', fn ($c) => $c->where('level', 'edit'))
            ->isNotEmpty();
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->lt(today());
    }
}
