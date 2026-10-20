<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasApprovals;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'reference_number', 'title', 'description', 'category_id', 'department_id', 'project_id', 'owner_id',
    'confidentiality', 'status', 'document_date', 'review_date', 'expiry_date', 'created_by',
])]
class Document extends Model
{
    use Auditable, HasApprovals, HasFactory, SoftDeletes;

    /** @var list<string> */
    protected array $auditExclude = ['reminded_at'];

    /** Levels that need documents.view-confidential (or ownership / an explicit grant). */
    public const PROTECTED_LEVELS = ['confidential', 'restricted'];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'review_date' => 'date',
            'expiry_date' => 'date',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
            'reminded_at' => 'datetime',
            'current_version' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('version');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class)->latestOfMany('version');
    }

    /** Users explicitly granted access to a restricted document. */
    public function grantedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('granted_by')->withTimestamps();
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(DocumentAccessLog::class)->latest('created_at');
    }

    /**
     * Record-level visibility based on confidentiality:
     *  - public / internal: anyone with documents.view;
     *  - confidential: owner, creator, or documents.view-confidential;
     *  - restricted: owner, creator, explicitly granted users, or documents.view-confidential.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->can('documents.view-confidential')) {
            return;
        }

        $query->where(function (Builder $q) use ($user) {
            $q->whereNotIn('confidentiality', self::PROTECTED_LEVELS)
                ->orWhere('owner_id', $user->id)
                ->orWhere('created_by', $user->id)
                ->orWhereHas('grantedUsers', fn (Builder $g) => $g->where('users.id', $user->id));
        });
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    public function isVisibleTo(User $user): bool
    {
        if (! in_array($this->confidentiality, self::PROTECTED_LEVELS, true)) {
            return true;
        }

        return $user->can('documents.view-confidential')
            || $this->owner_id === $user->id
            || $this->created_by === $user->id
            || $this->grantedUsers()->whereKey($user->id)->exists();
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->lt(today());
    }

    public function approvalDecided(Approval $approval): void
    {
        $this->forceFill(['status' => $approval->status === 'approved' ? 'approved' : 'rejected'])->save();
    }

    public function approvalTitle(): string
    {
        return __('Document').': '.$this->reference_number.' — '.$this->title;
    }

    public function approvalUrl(): string
    {
        return route('documents.show', $this);
    }
}
