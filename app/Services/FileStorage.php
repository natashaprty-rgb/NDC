<?php

namespace App\Services;

use App\Models\Attachment;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Secure file handling for every module.
 *
 *  - files are written to the private "local" disk (storage/app/private), never public/;
 *  - stored names are random (UUID) and contain no user input;
 *  - the extension must be allowed by settings AND the content-detected MIME
 *    type must match that extension (config ndc.upload_mime_map);
 *  - downloads go through an authorised controller which calls download().
 */
class FileStorage
{
    public const DISK = 'local';

    /** Validation rules for an upload field. */
    public static function rules(bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'file',
            'max:'.self::maxKb(),
            'extensions:'.implode(',', self::allowedExtensions()),
        ];
    }

    public static function maxKb(): int
    {
        return max(1, (int) Settings::get('uploads.max_kb', 20480));
    }

    public static function allowedExtensions(): array
    {
        $configured = array_filter(array_map('trim', explode(',', strtolower((string) Settings::get('uploads.allowed_extensions')))));

        // Only extensions with a known MIME mapping can ever be accepted.
        return array_values(array_intersect($configured, array_keys(config('ndc.upload_mime_map'))));
    }

    /**
     * Verify the real content type and store the file. Returns metadata.
     *
     * @return array{original_name:string,disk:string,path:string,mime_type:string,size:int,checksum:string}
     */
    public static function store(UploadedFile $file, string $folder, string $field = 'file'): array
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([$field => __('validation.uploaded', ['attribute' => $field])]);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $detected = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath()) ?: 'application/octet-stream';
        $allowedMimes = config('ndc.upload_mime_map.'.$extension, []);

        if (! in_array($extension, self::allowedExtensions(), true) || ! in_array($detected, $allowedMimes, true)) {
            throw ValidationException::withMessages([$field => __('common.upload_type_mismatch')]);
        }

        $folder = trim(preg_replace('/[^a-z0-9_\/-]/', '', strtolower($folder)), '/');
        $storedName = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs($folder.'/'.now()->format('Y/m'), $storedName, ['disk' => self::DISK]);

        if ($path === false) {
            throw ValidationException::withMessages([$field => __('validation.uploaded', ['attribute' => $field])]);
        }

        return [
            'original_name' => self::safeOriginalName($file->getClientOriginalName()),
            'disk' => self::DISK,
            'path' => $path,
            'mime_type' => $detected,
            'size' => (int) $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
        ];
    }

    /** Store an upload as an Attachment of the given model. */
    public static function attach(Model $owner, UploadedFile $file, string $category = 'general', string $field = 'file'): Attachment
    {
        $folder = 'attachments/'.Str::snake(class_basename($owner));
        $meta = self::store($file, $folder, $field);

        try {
            $attachment = $owner->attachments()->create($meta + [
                'category' => $category,
                'uploaded_by' => Auth::id(),
            ]);
        } catch (\Throwable $e) {
            Storage::disk($meta['disk'])->delete($meta['path']);
            throw $e;
        }

        AuditLogger::log('attachment_added', $owner, 'Attachment added: '.$attachment->original_name, [], ['attachment_id' => $attachment->id, 'category' => $category]);

        return $attachment;
    }

    /**
     * Stream a stored file. Inline only for safe preview types; everything else
     * is forced to download. Caller MUST have authorised the request.
     */
    public static function download(string $disk, string $path, string $originalName, string $mime, bool $inline = false): StreamedResponse
    {
        abort_unless(Storage::disk($disk)->exists($path), 404);

        $inline = $inline && in_array($mime, config('ndc.inline_preview_mimes'), true);

        return Storage::disk($disk)->response($path, $originalName, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
        ], $inline ? 'inline' : 'attachment');
    }

    public static function safeOriginalName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '_', $name) ?? 'file';

        return Str::limit($name, 200, '');
    }
}
