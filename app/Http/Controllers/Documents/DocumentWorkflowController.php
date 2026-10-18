<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Review, approval, archive and restore of documents. */
class DocumentWorkflowController extends Controller
{
    public function submit(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('submitForReview', $document);

        $document->update(['status' => 'pending_review', 'review_note' => null]);
        AuditLogger::log('submitted_for_approval', $document, 'Submitted for review');

        $approvers = User::query()->withPermission('documents.approve')->get()
            ->filter(fn (User $u) => $u->can('approve', $document->fresh()));
        Notifier::send($approvers, 'approval_pending', 'documents.notify.review_requested',
            ['reference' => $document->reference, 'title' => $document->title], route('documents.show', $document));

        return back()->with('success', __('documents.submitted'));
    }

    public function approve(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('approve', $document);
        $data = $request->validate(['review_note' => ['nullable', 'string', 'max:2000']]);

        DB::transaction(function () use ($document, $data, $request) {
            $document->update([
                'status' => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'review_note' => $data['review_note'] ?? null,
            ]);
            AuditLogger::log('approved', $document, 'Document approved');
        });

        Notifier::send($document->owner, 'document_decision', 'documents.notify.approved',
            ['reference' => $document->reference, 'title' => $document->title], route('documents.show', $document));

        return back()->with('success', __('documents.approved'));
    }

    public function reject(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('approve', $document);
        $data = $request->validate(['review_note' => ['required', 'string', 'max:2000']], [], ['review_note' => __('documents.review_note')]);

        DB::transaction(function () use ($document, $data) {
            $document->update(['status' => 'rejected', 'review_note' => $data['review_note'], 'approved_by' => null, 'approved_at' => null]);
            AuditLogger::log('rejected', $document, 'Document rejected');
        });

        Notifier::send($document->owner, 'document_decision', 'documents.notify.rejected',
            ['reference' => $document->reference, 'title' => $document->title], route('documents.show', $document));

        return back()->with('success', __('documents.rejected'));
    }

    public function archive(Document $document): RedirectResponse
    {
        $this->authorize('archive', $document);

        $document->update(['status' => 'archived', 'archived_at' => now()]);

        return redirect()->route('documents.show', $document)->with('success', __('common.archived'));
    }

    public function restore(Document $document): RedirectResponse
    {
        $this->authorize('restore', $document);

        $document->update(['status' => $document->approved_at ? 'approved' : 'draft', 'archived_at' => null]);
        AuditLogger::log('restored', $document, 'Document restored from archive');

        return redirect()->route('documents.show', $document)->with('success', __('common.restored'));
    }
}
