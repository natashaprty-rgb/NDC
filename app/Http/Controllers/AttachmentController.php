<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Concerns\HasAttachments;
use App\Services\AuditLogger;
use App\Services\FileStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One secure entry point for attachments of every module.
 *  - upload / delete require the `attach` ability on the parent record's policy;
 *  - download requires the `view` ability on the parent record's policy.
 */
class AttachmentController extends Controller
{
    public const CATEGORIES = ['general', 'evidence', 'receipt', 'invoice', 'quotation', 'contract', 'scan', 'agenda', 'minutes', 'delivery_note', 'report', 'photo'];

    public function store(Request $request, string $type, int $id): RedirectResponse
    {
        $parent = $this->resolveParent($type, $id);
        Gate::authorize('attach', $parent);

        $request->validate([
            'file' => FileStorage::rules(true),
            'category' => ['nullable', Rule::in(self::CATEGORIES)],
        ]);

        FileStorage::attach($parent, $request->file('file'), $request->input('category', 'general'));

        return back()->with('success', __('common.file_uploaded'));
    }

    public function download(Request $request, Attachment $attachment): StreamedResponse
    {
        $parent = $attachment->attachable;
        abort_if($parent === null, 404);
        Gate::authorize('view', $parent);

        AuditLogger::log('downloaded', $parent, 'Attachment downloaded: '.$attachment->original_name, [], ['attachment_id' => $attachment->id]);

        return FileStorage::download($attachment->disk, $attachment->path, $attachment->original_name, $attachment->mime_type, $request->boolean('preview'));
    }

    public function destroy(Attachment $attachment): RedirectResponse
    {
        $parent = $attachment->attachable;
        abort_if($parent === null, 404);
        Gate::authorize('attach', $parent);

        // Soft delete: the stored file is retained for audit/retention purposes.
        $attachment->delete();
        AuditLogger::log('attachment_removed', $parent, 'Attachment removed: '.$attachment->original_name, ['attachment_id' => $attachment->id]);

        return back()->with('success', __('common.deleted'));
    }

    private function resolveParent(string $type, int $id): Model
    {
        $class = Relation::getMorphedModel($type);
        abort_unless($class && in_array(HasAttachments::class, class_uses_recursive($class), true), 404);

        return $class::query()->findOrFail($id);
    }
}
