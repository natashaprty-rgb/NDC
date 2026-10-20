<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Support\Audit;
use App\Support\FileStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Document versions: uploading revisions and streaming files from the
 * private disk after an authorisation check. Every download is recorded in
 * the document access log.
 */
class DocumentVersionController extends Controller
{
    public function store(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('uploadVersion', $document);

        $data = $request->validate([
            'file' => FileStore::rules(required: true),
            'change_summary' => ['required', 'string', 'max:1000'],
        ]);

        $meta = FileStore::put($request->file('file'), 'documents');

        try {
            $version = DB::transaction(function () use ($document, $data, $meta, $request) {
                /** @var Document $locked */
                $locked = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
                $next = (int) $locked->current_version + 1;

                $version = $locked->versions()->create($meta + [
                    'version' => $next,
                    'change_summary' => $data['change_summary'],
                    'uploaded_by' => $request->user()->id,
                ]);
                $locked->forceFill(['current_version' => $next])->save();

                return $version;
            });
        } catch (Throwable $e) {
            FileStore::delete($meta['disk'], $meta['path']);

            throw $e;
        }

        Audit::log('version_uploaded', $document, __('Version :number: :name', ['number' => $version->version, 'name' => $version->original_name]));

        return redirect()->to(route('documents.show', $document).'#versions')
            ->with('success', __('Version :number uploaded.', ['number' => $version->version]));
    }

    public function download(Request $request, Document $document, DocumentVersion $version): StreamedResponse
    {
        $this->authorize('download', $document);

        $disk = Storage::disk($version->disk);
        abort_unless($disk->exists($version->path), 404);

        $document->logAccess('download', $request->user(), $version, $request->ip());

        return $disk->download($version->path, $version->original_name, [
            'Content-Type' => $version->mime_type ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
