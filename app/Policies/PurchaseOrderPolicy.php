<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('procurement.view') && PurchaseRequestPolicy::hasOversight($user);
    }

    public function view(User $user, PurchaseOrder $po): bool
    {
        return $user->hasPermission('procurement.view')
            && (PurchaseRequestPolicy::hasOversight($user) || $po->purchaseRequest?->requested_by === $user->id);
    }

    /** Recording goods received: not the requester, not the approver. */
    public function receive(User $user, PurchaseOrder $po): bool
    {
        $pr = $po->purchaseRequest;

        return in_array($po->status, ['issued', 'partially_delivered'], true)
            && $user->hasPermission('procurement.receive')
            && $pr !== null
            && $pr->requested_by !== $user->id
            && $pr->decided_by !== $user->id;
    }

    public function cancel(User $user, PurchaseOrder $po): bool
    {
        return $po->status === 'issued' && $user->hasPermission('procurement.order') && ! $po->receipts()->exists();
    }

    public function attach(User $user, PurchaseOrder $po): bool
    {
        return $po->status !== 'cancelled' && ($user->hasPermission('procurement.order') || $user->hasPermission('procurement.receive'));
    }
}
