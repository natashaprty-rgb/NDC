<?php

namespace App\Policies;

use App\Models\ProcurementRequest;
use App\Models\User;

class ProcurementRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('procurement.view');
    }

    public function view(User $user, ProcurementRequest $request): bool
    {
        return $user->can('procurement.view');
    }

    public function create(User $user): bool
    {
        return $user->can('procurement.create');
    }

    /**
     * Change the request itself (details and line items): only while it is a
     * draft or has been returned, by its requester or a procurement officer.
     */
    public function edit(User $user, ProcurementRequest $request): bool
    {
        return $request->isEditable() && $this->ownsOrManages($user, $request);
    }

    /**
     * Add or remove supporting files on the request, its quotations, orders
     * and delivery records.
     */
    public function update(User $user, ProcurementRequest $request): bool
    {
        return $request->status !== 'cancelled' && $this->ownsOrManages($user, $request);
    }

    public function submit(User $user, ProcurementRequest $request): bool
    {
        return $this->edit($user, $request);
    }

    public function cancel(User $user, ProcurementRequest $request): bool
    {
        if (! $request->isCancellable()) {
            return false;
        }

        // Requesters may withdraw their own request until it is approved.
        return $user->can('procurement.update')
            || ((int) $request->requested_by === $user->id && in_array($request->status, ['draft', 'submitted', 'rejected'], true));
    }

    /**
     * Record quotations and purchase orders.
     */
    public function manage(User $user, ProcurementRequest $request): bool
    {
        return $user->can('procurement.update') && $request->canReceiveQuotations();
    }

    public function delete(User $user, ProcurementRequest $request): bool
    {
        return $user->can('procurement.delete')
            && in_array($request->status, ['draft', 'rejected', 'cancelled'], true)
            && ! $request->purchaseOrders()->exists();
    }

    private function ownsOrManages(User $user, ProcurementRequest $request): bool
    {
        return $user->can('procurement.update')
            || ((int) $request->requested_by === $user->id && $user->can('procurement.create'));
    }
}
