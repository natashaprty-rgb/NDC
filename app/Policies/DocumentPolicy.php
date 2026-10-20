<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

/**
 * Document register. Every record action requires the document to be visible
 * to the user under its confidentiality level (Document::isVisibleTo()).
 */
class DocumentPolicy
{
    /** Statuses in which metadata may be edited and new versions uploaded. */
    public const EDITABLE_STATUSES = ['draft', 'rejected', 'approved', 'published'];

    public function viewAny(User $user): bool
    {
        return $user->can('documents.view');
    }

    public function view(User $user, Document $document): bool
    {
        return $user->can('documents.view') && $document->isVisibleTo($user);
    }

    /**
     * Download any version of the file.
     */
    public function download(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    public function create(User $user): bool
    {
        return $user->can('documents.create');
    }

    public function update(User $user, Document $document): bool
    {
        return $user->can('documents.update')
            && ! $document->isArchived()
            && in_array($document->status, self::EDITABLE_STATUSES, true)
            && $document->isVisibleTo($user);
    }

    /**
     * Upload a new version of the file.
     */
    public function uploadVersion(User $user, Document $document): bool
    {
        return $this->update($user, $document);
    }

    /**
     * Send a draft or rejected document for approval: editors, or the
     * document's owner or creator.
     */
    public function submit(User $user, Document $document): bool
    {
        return in_array($document->status, ['draft', 'rejected'], true)
            && ! $document->isArchived()
            && $document->current_version > 0
            && $this->view($user, $document)
            && ($user->can('documents.update') || $this->isOwner($user, $document));
    }

    /**
     * Publish an approved document: approvers or the document's owner.
     */
    public function publish(User $user, Document $document): bool
    {
        return $document->status === 'approved'
            && ! $document->isArchived()
            && $this->view($user, $document)
            && ($user->can('documents.approve') || $document->owner_id === $user->id);
    }

    public function archive(User $user, Document $document): bool
    {
        return $user->can('documents.delete')
            && ! $document->isArchived()
            && $document->isVisibleTo($user);
    }

    public function unarchive(User $user, Document $document): bool
    {
        return $user->can('documents.delete')
            && $document->isArchived()
            && $document->isVisibleTo($user);
    }

    /**
     * Grant or revoke individual access to a restricted document: its owner
     * or holders of documents.view-confidential.
     */
    public function manageAccess(User $user, Document $document): bool
    {
        return $document->confidentiality === 'restricted'
            && $user->can('documents.view')
            && ($document->owner_id === $user->id || $user->can('documents.view-confidential'));
    }

    /**
     * See who viewed and downloaded the document.
     */
    public function viewAccessLog(User $user, Document $document): bool
    {
        return $this->view($user, $document)
            && ($document->owner_id === $user->id || $user->can('documents.approve') || $user->can('audit.view'));
    }

    private function isOwner(User $user, Document $document): bool
    {
        return $document->owner_id === $user->id || $document->created_by === $user->id;
    }
}
