<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('procurement.view');
    }

    public function view(User $user, PurchaseOrder $order): bool
    {
        return $user->can('procurement.view');
    }

    public function update(User $user, PurchaseOrder $order): bool
    {
        return $user->can('procurement.update') && $order->status !== 'cancelled';
    }

    public function receive(User $user, PurchaseOrder $order): bool
    {
        return $user->can('procurement.update') && $order->canReceiveGoods();
    }

    /**
     * An order can be cancelled only before anything has been delivered.
     */
    public function cancel(User $user, PurchaseOrder $order): bool
    {
        return $user->can('procurement.update') && $order->status === 'issued' && ! $order->receipts()->exists();
    }
}
