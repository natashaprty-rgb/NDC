<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetDisposal;
use App\Services\ApprovalService;
use App\Support\Audit;
use App\Support\Status;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Disposal and retirement requests. Each request needs approval by a holder
 * of assets.approve-disposal other than the requester; nothing happens to
 * the asset until that decision is made.
 */
class AssetDisposalController extends Controller
{
    public function store(Request $request, Asset $asset, ApprovalService $approvals): RedirectResponse
    {
        $this->authorize('requestDisposal', $asset);

        $data = $request->validate([
            'action' => ['required', Rule::in(Status::keys('disposal_action'))],
            'reason' => ['required', 'string', 'max:5000'],
            'method' => ['nullable', 'string', 'max:255'],
            'proposed_date' => ['nullable', 'date'],
        ]);

        if ($error = $this->blockingReason($asset, $data['action'])) {
            return back()->withInput()->with('error', $error);
        }

        DB::transaction(function () use ($asset, $data, $request, $approvals) {
            /** @var AssetDisposal $disposal */
            $disposal = $asset->disposals()->create([...$data, 'status' => 'pending', 'requested_by' => $request->user()->id]);
            $disposal->setRelation('asset', $asset);
            $approvals->request($disposal, 'assets.approve-disposal', $request->user(), $data['reason']);
        });

        Audit::log($data['action'] === 'retire' ? 'retirement_requested' : 'disposal_requested', $asset, $data['reason']);

        return back()->with('success', __('Request submitted for approval.'));
    }

    /**
     * Record that an approved disposal has been carried out.
     */
    public function complete(Request $request, Asset $asset, AssetDisposal $disposal): RedirectResponse
    {
        $this->authorize('complete', $disposal);

        $data = $request->validate([
            'completed_on' => ['required', 'date', 'before_or_equal:today'],
            'proceeds' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'method' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($asset, $disposal, $data) {
            $disposal->update([
                'status' => 'completed',
                'completed_on' => $data['completed_on'],
                'proceeds' => $data['proceeds'] ?? null,
                'method' => $data['method'] ?? $disposal->method,
            ]);

            $open = $asset->openAssignment()->first();
            $open?->forceFill(['returned_on' => $data['completed_on'], 'condition_on_return' => $asset->condition])->save();

            $asset->forceFill(['status' => 'disposed', 'assigned_to' => null])->save();
        });

        Audit::log('disposed', $asset, $disposal->method);

        return back()->with('success', __('Disposal recorded. The asset is now marked as disposed.'));
    }

    private function blockingReason(Asset $asset, string $action): ?string
    {
        return match (true) {
            $asset->openDisposal()->exists() => __('This asset already has an open disposal or retirement request.'),
            $asset->status === 'retired' && $action === 'retire' => __('This asset is already retired.'),
            $asset->openAssignment()->exists() => __('Return the asset before requesting its disposal or retirement.'),
            $asset->maintenances()->where('status', 'in_progress')->exists() => __('Complete or cancel the maintenance in progress first.'),
            default => null,
        };
    }
}
