<?php

namespace App\Policies;

use App\Models\GoodsReceipt;
use App\Models\User;

class GoodsReceiptPolicy
{
    public function view(User $user, GoodsReceipt $receipt): bool
    {
        return $receipt->purchaseOrder !== null && (new PurchaseOrderPolicy)->view($user, $receipt->purchaseOrder);
    }

    public function attach(User $user, GoodsReceipt $receipt): bool
    {
        return $receipt->received_by === $user->id && $user->hasPermission('procurement.receive');
    }
}
