<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('documents.view');
    }

    /** Confidentiality rules are defined once in Document::canBeViewedBy(). */
    public function view(User $user, Document $document): bool
    {
        return $user->hasPermission('documents.view') && $document->canBeViewedBy($user);
    }

    public function download(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('documents.create');
    }

    public function update(User $user, Document $document): bool
    {
        if ($document->status === 'archived' || ! $this->view($user, $document)) {
            return false;
        }

        return $user->hasPermission('documents.manage')
            || $document->owner_id === $user->id
            || $document->created_by === $user->id
            || $document->hasGrantFor($user, 'edit');
    }

    public function uploadVersion(User $user, Document $document): bool
    {
        return $this->update($user, $document) && $user->hasPermission('documents.create');
    }

    public function submitForReview(User $user, Document $document): bool
    {
        return $this->update($user, $document) && in_array($document->status, ['draft', 'rejected'], true) && $document->current_version > 0;
    }

    /** Reviewers cannot approve documents they own or registered. */
    public function approve(User $user, Document $document): bool
    {
        return $document->status === 'pending_review'
            && $user->hasPermission('documents.approve')
            && $document->owner_id !== $user->id
            && $document->created_by !== $user->id
            && $document->canBeViewedBy($user);
    }

    public function manageAccess(User $user, Document $document): bool
    {
        return $document->status !== 'archived'
            && ($user->hasPermission('documents.manage') || $document->owner_id === $user->id);
    }

    public function archive(User $user, Document $document): bool
    {
        return $document->status !== 'archived'
            && ($user->hasPermission('documents.manage') || $document->owner_id === $user->id);
    }

    public function restore(User $user, Document $document): bool
    {
        return $document->status === 'archived' && $user->hasPermission('documents.manage');
    }
}
