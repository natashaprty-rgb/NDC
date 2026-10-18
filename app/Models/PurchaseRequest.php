<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseRequest extends Model
{
    use Auditable, HasAttachments, HasFactory, HasUserstamps, SoftDeletes;

    public const STATUSES = ['draft', 'submitted', 'approved', 'rejected', 'ordered', 'partially_received', 'received', 'cancelled'];

    protected $fillable = [
        'reference', 'title', 'justification', 'department_id', 'project_id', 'budget_id', 'requested_by', 'needed_by',
        'currency', 'estimated_total', 'status', 'submitted_at', 'decided_by', 'decided_at', 'decision_note',
    ];

    protected function casts(): array
    {
        return ['needed_by' => 'date', 'submitted_at' => 'datetime', 'decided_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by')->withTrashed();
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withTrashed();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class)->withTrashed();
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }
}
