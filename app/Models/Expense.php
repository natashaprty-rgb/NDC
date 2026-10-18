<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use Auditable, HasAttachments, HasFactory, HasUserstamps, SoftDeletes;

    public const STATUSES = ['recorded', 'approved', 'rejected', 'paid', 'reconciled'];

    /** Statuses that consume the budget balance. */
    public const COMMITTED_STATUSES = ['approved', 'paid', 'reconciled'];

    protected $fillable = [
        'reference', 'budget_id', 'expense_category_id', 'purchase_order_id', 'supplier_id', 'description', 'payee',
        'invoice_number', 'amount', 'currency', 'expense_date', 'status', 'recorded_by', 'approved_by', 'approved_at',
        'rejected_by', 'rejected_at', 'paid_on', 'payment_reference', 'settled_by', 'reconciled_at', 'review_note', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'paid_on' => 'date',
            'reconciled_at' => 'datetime',
        ];
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class)->withTrashed();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class)->withTrashed();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by')->withTrashed();
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by')->withTrashed();
    }

    public function settledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by')->withTrashed();
    }
}
