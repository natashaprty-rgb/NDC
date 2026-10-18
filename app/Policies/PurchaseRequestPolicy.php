<?php

namespace App\Policies;

use App\Models\PurchaseRequest;
use App\Models\User;

/**
 * Segregation of duties:
 *   requester  -> creates and submits (cannot approve own request)
 *   approver   -> procurement.approve, must not be the requester
 *   procurement officer -> quotations / purchase orders (procurement.order), must not be the requester
 *   receiver   -> goods received (PurchaseOrderPolicy::receive), must not be requester or approver
 */
class PurchaseRequestPolicy
{
    /** Permissions that grant visibility of every purchase request. */
    public const OVERSIGHT = ['procurement.approve', 'procurement.order', 'procurement.receive', 'budgets.view', 'reports.financial'];

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('procurement.view');
    }

    public function view(User $user, PurchaseRequest $pr): bool
    {
        if (! $user->hasPermission('procurement.view')) {
            return false;
        }

        return $pr->requested_by === $user->id
            || self::hasOversight($user)
            || ($pr->department_id !== null && in_array($pr->department_id, $user->headedDepartmentIds(), true));
    }

    public static function hasOversight(User $user): bool
    {
        foreach (self::OVERSIGHT as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('procurement.request');
    }

    public function update(User $user, PurchaseRequest $pr): bool
    {
        return $pr->status === 'draft' && $pr->requested_by === $user->id;
    }

    public function submit(User $user, PurchaseRequest $pr): bool
    {
        return $this->update($user, $pr) && $pr->items()->exists();
    }

    public function approve(User $user, PurchaseRequest $pr): bool
    {
        return $pr->status === 'submitted'
            && $user->hasPermission('procurement.approve')
            && $pr->requested_by !== $user->id
            && $pr->created_by !== $user->id;
    }

    public function cancel(User $user, PurchaseRequest $pr): bool
    {
        return in_array($pr->status, ['draft', 'submitted', 'approved'], true)
            && ($pr->requested_by === $user->id || $user->hasPermission('procurement.approve'));
    }

    /** Record supplier quotations and raise purchase orders. */
    public function order(User $user, PurchaseRequest $pr): bool
    {
        return in_array($pr->status, ['approved', 'ordered', 'partially_received'], true)
            && $user->hasPermission('procurement.order')
            && $pr->requested_by !== $user->id;
    }

    public function attach(User $user, PurchaseRequest $pr): bool
    {
        return ($this->update($user, $pr) || $this->order($user, $pr)) && $this->view($user, $pr);
    }

    public function delete(User $user, PurchaseRequest $pr): bool
    {
        return $this->update($user, $pr);
    }
}
