<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Services\AuditLogger;
use App\Services\Documents\DocumentService;
use App\Services\FileStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentVersionController extends Controller
{
    public function store(Request $request, Document $document, DocumentService $service): RedirectResponse
    {
        $this->authorize('uploadVersion', $document);

        $request->validate([
            'file' => FileStorage::rules(true),
            'change_note' => ['nullable', 'string', 'max:500'],
        ], [], ['file' => __('common.file'), 'change_note' => __('documents.change_note')]);

        $version = $service->addVersion($document, $request->file('file'), $request->input('change_note'), $request->user());

        return redirect()->route('documents.show', $document)->with('success', __('documents.version_uploaded', ['n' => $version->version_number]));
    }

    /** Files are streamed from the private disk only after authorisation. */
    public function download(Request $request, Document $document, DocumentVersion $version): StreamedResponse
    {
        $this->authorize('download', $document);

        $preview = $request->boolean('preview');
        AuditLogger::log('downloaded', $document, ($preview ? 'Previewed' : 'Downloaded').' version '.$version->version_number, [], ['version' => $version->version_number]);

        return FileStorage::download($version->disk, $version->path, $version->original_name, $version->mime_type, $preview);
    }
}
