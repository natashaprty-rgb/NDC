<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasApprovals;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Request to dispose of or retire an asset. Requires approval by a user with
 * assets.approve-disposal who is not the requester.
 */
#[Fillable(['action', 'reason', 'method', 'proposed_date', 'completed_on', 'proceeds', 'status', 'requested_by'])]
class AssetDisposal extends Model
{
    use Auditable, HasApprovals;

    protected function casts(): array
    {
        return ['proposed_date' => 'date', 'completed_on' => 'date', 'proceeds' => 'decimal:2'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * An approved retirement takes effect immediately; an approved disposal
     * stays "approved" until the disposal itself is recorded as completed.
     */
    public function approvalDecided(Approval $approval): void
    {
        if ($approval->status !== 'approved') {
            $this->forceFill(['status' => 'rejected'])->save();

            return;
        }

        if ($this->action === 'retire') {
            $this->forceFill(['status' => 'completed', 'completed_on' => $this->completed_on ?? today()])->save();
            $this->asset?->forceFill(['status' => 'retired'])->save();

            return;
        }

        $this->forceFill(['status' => 'approved'])->save();
    }

    public function isAwaitingCompletion(): bool
    {
        return $this->action === 'dispose' && $this->status === 'approved';
    }

    public function approvalTitle(): string
    {
        $action = $this->action === 'retire' ? __('Asset retirement') : __('Asset disposal');

        return $action.': '.$this->asset?->asset_tag.' — '.$this->asset?->name;
    }

    public function approvalUrl(): string
    {
        return route('assets.show', $this->asset_id);
    }
}
