<?php

namespace App\Services\Procurement;

use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifier;
use App\Support\Money;
use App\Support\ReferenceNumber;
use Illuminate\Support\Facades\DB;

class PurchaseRequestService
{
    /**
     * Create or update a draft request; line items are replaced atomically and
     * every total is computed server-side in integer cents.
     *
     * @param  array<int, array{description:string, quantity:string, unit?:string|null, estimated_unit_cost:string}>  $items
     */
    public function saveDraft(?PurchaseRequest $pr, array $data, array $items, User $user): PurchaseRequest
    {
        return DB::transaction(function () use ($pr, $data, $items, $user) {
            $lines = [];
            foreach (array_values($items) as $item) {
                $lines[] = [
                    'description' => $item['description'],
                    'quantity' => Money::fromCents(Money::toCents((string) $item['quantity'])),
                    'unit' => ($item['unit'] ?? null) ?: 'unit',
                    'estimated_unit_cost' => Money::fromCents(Money::toCents((string) $item['estimated_unit_cost'])),
                    'line_total' => Money::multiply((string) $item['quantity'], (string) $item['estimated_unit_cost']),
                ];
            }

            $attributes = [
                'title' => $data['title'],
                'justification' => $data['justification'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'project_id' => $data['project_id'] ?? null,
                'budget_id' => $data['budget_id'] ?? null,
                'needed_by' => $data['needed_by'] ?? null,
                'currency' => strtoupper($data['currency']),
                'estimated_total' => Money::sum(array_column($lines, 'line_total')),
            ];

            if ($pr === null) {
                $pr = PurchaseRequest::query()->create($attributes + [
                    'reference' => ReferenceNumber::next('purchase_request'),
                    'requested_by' => $user->id,
                    'status' => 'draft',
                ]);
            } else {
                $pr = PurchaseRequest::query()->lockForUpdate()->findOrFail($pr->id);
                abort_unless($pr->status === 'draft', 409);
                $pr->update($attributes);
                $pr->items()->delete();
            }

            $pr->items()->createMany($lines);

            return $pr;
        });
    }

    public function submit(PurchaseRequest $pr): void
    {
        DB::transaction(function () use ($pr) {
            $locked = PurchaseRequest::query()->lockForUpdate()->findOrFail($pr->id);
            abort_unless($locked->status === 'draft', 409);
            $locked->update(['status' => 'submitted', 'submitted_at' => now()]);
        });
        $pr->refresh();

        $approvers = User::query()->withPermission('procurement.approve')->where('id', '!=', $pr->requested_by)->get();
        Notifier::send($approvers, 'approval_pending', 'procurement.notify.pr_submitted',
            ['reference' => $pr->reference, 'title' => $pr->title], route('purchase-requests.show', $pr));
    }

    public function decide(PurchaseRequest $pr, User $user, bool $approve, ?string $note): void
    {
        DB::transaction(function () use ($pr, $user, $approve, $note) {
            $locked = PurchaseRequest::query()->lockForUpdate()->findOrFail($pr->id);
            abort_unless($locked->status === 'submitted', 409);
            $locked->update([
                'status' => $approve ? 'approved' : 'rejected',
                'decided_by' => $user->id,
                'decided_at' => now(),
                'decision_note' => $note,
            ]);
            AuditLogger::log($approve ? 'approved' : 'rejected', $locked, $note);
        });
        $pr->refresh();

        Notifier::send($pr->requester, 'pr_decided', $approve ? 'procurement.notify.pr_approved' : 'procurement.notify.pr_rejected',
            ['reference' => $pr->reference, 'title' => $pr->title], route('purchase-requests.show', $pr));
    }

    public function cancel(PurchaseRequest $pr, string $reason): void
    {
        DB::transaction(function () use ($pr, $reason) {
            $locked = PurchaseRequest::query()->lockForUpdate()->findOrFail($pr->id);
            abort_unless(in_array($locked->status, ['draft', 'submitted', 'approved'], true), 409);
            $locked->update(['status' => 'cancelled', 'decision_note' => $reason]);
            AuditLogger::log('cancelled', $locked, $reason);
        });
    }
}
