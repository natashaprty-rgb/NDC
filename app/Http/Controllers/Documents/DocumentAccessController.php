<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentAccess;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Explicit access grants for confidential / restricted documents. */
class DocumentAccessController extends Controller
{
    public function store(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('manageAccess', $document);

        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'required_without:department_id', 'prohibits:department_id', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'department_id' => ['nullable', 'integer', 'required_without:user_id', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'level' => ['required', Rule::in(DocumentAccess::LEVELS)],
        ], [], ['user_id' => __('documents.grant_user'), 'department_id' => __('common.department'), 'level' => __('documents.access_level')]);

        $grant = DocumentAccess::query()->updateOrCreate(
            ['document_id' => $document->id, 'user_id' => $data['user_id'] ?? null, 'department_id' => $data['department_id'] ?? null],
            ['level' => $data['level'], 'granted_by' => $request->user()->id]
        );
        AuditLogger::log('access_granted', $document, 'Access granted', [], [
            'user_id' => $grant->user_id, 'department_id' => $grant->department_id, 'level' => $grant->level,
        ]);

        return back()->with('success', __('documents.access_granted'));
    }

    public function destroy(Document $document, DocumentAccess $accessGrant): RedirectResponse
    {
        $this->authorize('manageAccess', $document);

        $old = ['user_id' => $accessGrant->user_id, 'department_id' => $accessGrant->department_id, 'level' => $accessGrant->level];
        $accessGrant->delete();
        AuditLogger::log('access_revoked', $document, 'Access revoked', $old);

        return back()->with('success', __('documents.access_revoked'));
    }
}
