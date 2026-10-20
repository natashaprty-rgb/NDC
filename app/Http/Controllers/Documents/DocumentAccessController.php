<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\User;
use App\Notifications\GenericNotification;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Individual access grants for restricted documents. Only the owner or a
 * holder of documents.view-confidential may grant or revoke access.
 */
class DocumentAccessController extends Controller
{
    public function store(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('manageAccess', $document);

        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('status', 'active')],
        ]);

        $grantee = User::query()->findOrFail($data['user_id']);
        if (! $grantee->can('documents.view')) {
            throw ValidationException::withMessages([
                'user_id' => __('This user does not have permission to view documents.'),
            ]);
        }

        if (! $document->grantedUsers()->whereKey($grantee->id)->exists()) {
            $document->grantedUsers()->attach($grantee->id, ['granted_by' => $request->user()->id]);
            Audit::log('access_granted', $document, $grantee->name);

            if (! $grantee->is($request->user())) {
                $grantee->notify(new GenericNotification(
                    'document_access_granted',
                    'Document access granted',
                    'You have been given access to the restricted document ":title".',
                    ['title' => $document->title],
                    route('documents.show', $document),
                ));
            }
        }

        return redirect()->to(route('documents.show', $document).'#access')
            ->with('success', __('Access granted to :name.', ['name' => $grantee->name]));
    }

    public function destroy(Document $document, User $user): RedirectResponse
    {
        $this->authorize('manageAccess', $document);

        if ($document->grantedUsers()->detach($user->id) > 0) {
            Audit::log('access_revoked', $document, $user->name);
        }

        return redirect()->to(route('documents.show', $document).'#access')
            ->with('success', __('Access revoked for :name.', ['name' => $user->name]));
    }
}
