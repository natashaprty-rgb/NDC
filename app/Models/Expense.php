<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasApprovals;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Recorded expenditure against a budget. Only approved expenses count
 * towards budget utilisation.
 */
#[Fillable(['category_id', 'purchase_order_id', 'reference', 'description', 'payee', 'amount', 'expense_date', 'status', 'recorded_by'])]
class Expense extends Model
{
    use Auditable, HasApprovals, HasAttachments, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'expense_date' => 'date'];
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function approvalDecided(Approval $approval): void
    {
        $this->forceFill(['status' => $approval->status === 'approved' ? 'approved' : 'rejected'])->save();
    }

    public function approvalTitle(): string
    {
        return __('Expense').': '.money($this->amount, $this->budget?->currency).' — '.$this->description;
    }

    public function approvalUrl(): string
    {
        return route('budgets.show', $this->budget_id).'#expense-'.$this->id;
    }
}
