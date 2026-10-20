<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Support\Audit;
use App\Support\FileStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Upload, download and removal of files attached to any record. Access is
 * delegated to the parent record's policy: "viewAttachments" (when the
 * policy defines it) or "view" to download, "update" to upload or remove.
 */
class AttachmentController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'attachable_type' => ['required', 'string'],
            'attachable_id' => ['required', 'integer'],
            'collection' => ['nullable', 'string', 'alpha_dash', 'max:40'],
            'description' => ['nullable', 'string', 'max:255'],
            'file' => FileStore::rules(required: true),
        ]);

        $class = Relation::getMorphedModel($data['attachable_type']);
        abort_unless($class && method_exists($class, 'attachments'), 404);

        /** @var Model $model */
        $model = $class::query()->findOrFail($data['attachable_id']);
        $this->authorize('update', $this->authorizationTarget($model));

        FileStore::attach($model, $request->file('file'), $data['collection'] ?? 'default', $data['description'] ?? null);

        return back()->with('success', __('File uploaded.'));
    }

    public function download(Attachment $attachment): StreamedResponse
    {
        $parent = $attachment->attachable;
        abort_if($parent === null, 404);
        $target = $this->authorizationTarget($parent);
        // Policies may define a stricter "viewAttachments" ability (e.g. staff CVs).
        $policy = Gate::getPolicyFor($target);
        $this->authorize($policy && method_exists($policy, 'viewAttachments') ? 'viewAttachments' : 'view', $target);

        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);
        Audit::log('file_downloaded', $parent, $attachment->original_name);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Attachment $attachment): RedirectResponse
    {
        $parent = $attachment->attachable;
        abort_if($parent === null, 404);
        $this->authorize('update', $this->authorizationTarget($parent));

        $attachment->delete();
        Audit::log('file_removed', $parent, $attachment->original_name);

        return back()->with('success', __('File removed.'));
    }

    /**
     * Child records (quotations, goods receipts, task comments) are
     * authorised through the record they belong to.
     */
    private function authorizationTarget(Model $model): Model
    {
        return method_exists($model, 'attachmentAuthorizationTarget') ? $model->attachmentAuthorizationTarget() : $model;
    }
}
