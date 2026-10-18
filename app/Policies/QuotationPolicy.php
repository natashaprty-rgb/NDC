<?php

namespace App\Policies;

use App\Models\Quotation;
use App\Models\User;

class QuotationPolicy
{
    public function view(User $user, Quotation $quotation): bool
    {
        return $quotation->purchaseRequest !== null && (new PurchaseRequestPolicy)->view($user, $quotation->purchaseRequest);
    }

    public function attach(User $user, Quotation $quotation): bool
    {
        return $quotation->purchaseRequest !== null && (new PurchaseRequestPolicy)->order($user, $quotation->purchaseRequest);
    }
}
