<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\FileStorage;
use App\Support\ReferenceNumber;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DocumentService
{
    /** Register a document with its first version in one transaction. */
    public function register(array $data, UploadedFile $file, User $user): Document
    {
        $meta = FileStorage::store($file, 'documents');

        try {
            return DB::transaction(function () use ($data, $meta, $user) {
                $changeNote = $data['change_note'] ?? null;
                unset($data['file'], $data['change_note']);

                $data['reference'] = ReferenceNumber::next('document');
                $data['owner_id'] = $data['owner_id'] ?? $user->id;
                $data['status'] = 'draft';
                $data['current_version'] = 1;
                $data['retain_until'] = $this->retainUntil($data['category_id'] ?? null, $data['registration_date']);

                $document = Document::query()->create($data);
                $document->versions()->create($meta + [
                    'version_number' => 1,
                    'change_note' => $changeNote,
                    'uploaded_by' => $user->id,
                ]);

                return $document;
            });
        } catch (\Throwable $e) {
            Storage::disk($meta['disk'])->delete($meta['path']);
            throw $e;
        }
    }

    /**
     * Add a new version. The document row is locked so concurrent uploads
     * cannot obtain the same version number. A new version of an approved
     * (or in-review) document returns it to draft for re-approval.
     */
    public function addVersion(Document $document, UploadedFile $file, ?string $changeNote, User $user): DocumentVersion
    {
        $meta = FileStorage::store($file, 'documents');

        try {
            return DB::transaction(function () use ($document, $meta, $changeNote, $user) {
                $locked = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
                $next = max((int) $locked->current_version, (int) $locked->versions()->max('version_number')) + 1;

                $version = $locked->versions()->create($meta + [
                    'version_number' => $next,
                    'change_note' => $changeNote,
                    'uploaded_by' => $user->id,
                ]);

                $updates = ['current_version' => $next];
                if (in_array($locked->status, ['approved', 'pending_review'], true)) {
                    $updates += ['status' => 'draft', 'approved_by' => null, 'approved_at' => null];
                    AuditLogger::log('approval_reset', $locked, "Version {$next} uploaded; document returned to draft for re-approval");
                }
                $locked->update($updates);
                AuditLogger::log('version_uploaded', $locked, "Version {$next} uploaded", [], ['version' => $next]);

                return $version;
            });
        } catch (\Throwable $e) {
            Storage::disk($meta['disk'])->delete($meta['path']);
            throw $e;
        }
    }

    public function retainUntil(?int $categoryId, string|\DateTimeInterface $registrationDate): ?string
    {
        $years = $categoryId ? DocumentCategory::query()->whereKey($categoryId)->value('retention_years') : null;

        return $years ? Carbon::parse($registrationDate)->addYears((int) $years)->toDateString() : null;
    }
}
