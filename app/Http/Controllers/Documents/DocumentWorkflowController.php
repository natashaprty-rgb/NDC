<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\ApprovalService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Approval and publication workflow:
 * draft/rejected → pending_review → approved/rejected (shared approval panel)
 * → published → archived (and back).
 */
class DocumentWorkflowController extends Controller
{
    public function submit(Request $request, Document $document, ApprovalService $approvals): RedirectResponse
    {
        $this->authorize('submit', $document);

        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        DB::transaction(function () use ($document, $approvals, $request, $data) {
            $approvals->request($document, 'documents.approve', $request->user(), $data['note'] ?? null);
            $this->transition($document, 'pending_review', 'submitted', $data['note'] ?? null);
        });

        return back()->with('success', __('Document submitted for approval.'));
    }

    public function publish(Document $document): RedirectResponse
    {
        $this->authorize('publish', $document);

        $this->transition($document, 'published', 'published', null, ['published_at' => now()]);

        return back()->with('success', __('Document published.'));
    }

    public function archive(Request $request, Document $document, ApprovalService $approvals): RedirectResponse
    {
        $this->authorize('archive', $document);

        DB::transaction(function () use ($document, $approvals, $request) {
            if ($pending = $document->pendingApproval) {
                $approvals->cancel($pending, $request->user());
            }
            $this->transition($document, 'archived', 'archived', null, ['archived_at' => now()]);
        });

        return back()->with('success', __('Document archived.'));
    }

    public function unarchive(Document $document): RedirectResponse
    {
        $this->authorize('unarchive', $document);

        // Return the document to the stage it had reached before archiving.
        $lastDecision = $document->approvals()->whereIn('status', ['approved', 'rejected'])->first();
        $status = match (true) {
            $document->published_at !== null => 'published',
            $lastDecision !== null => $lastDecision->status,
            default => 'draft',
        };

        $this->transition($document, $status, 'unarchived', null, ['archived_at' => null]);

        return back()->with('success', __('Document restored from the archive.'));
    }

    /**
     * Change status (and related timestamps) and write one workflow entry to
     * the audit trail.
     *
     * @param  array<string, mixed>  $extra
     */
    private function transition(Document $document, string $status, string $event, ?string $note = null, array $extra = []): void
    {
        $old = ['status' => $document->status];
        $document->forceFill(['status' => $status] + $extra)->saveQuietly();

        Audit::log($event, $document, $note ?? $document->reference_number, $old, ['status' => $status]);
    }
}
