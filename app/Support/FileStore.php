<?php

namespace App\Support;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores uploaded files on the private disk. Files are never placed under the
 * public web root; they are streamed back through controllers that check
 * authorisation first.
 */
class FileStore
{
    public const DISK = 'local';

    /**
     * Validation rules for a single uploaded file, driven by the upload
     * settings (permitted extensions and size limit).
     *
     * @return list<string>
     */
    public static function rules(bool $required = false): array
    {
        $settings = app(Settings::class);

        return [
            $required ? 'required' : 'nullable',
            'file',
            'max:'.(int) $settings->get('upload_max_kb', 20480),
            'extensions:'.implode(',', $settings->allowedExtensions()),
            'mimes:'.implode(',', $settings->allowedExtensions()),
        ];
    }

    /**
     * Store a file under a dated directory and return its metadata.
     *
     * @return array{disk: string, path: string, original_name: string, mime_type: ?string, size: int, checksum: string}
     */
    public static function put(UploadedFile $file, string $directory): array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $name = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs(trim($directory, '/').'/'.now()->format('Y/m'), $name, self::DISK);

        return [
            'disk' => self::DISK,
            'path' => $path,
            'original_name' => self::cleanName($file->getClientOriginalName()),
            'mime_type' => $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
        ];
    }

    public static function attach(Model $model, UploadedFile $file, string $collection = 'default', ?string $description = null): Attachment
    {
        $meta = self::put($file, 'attachments/'.$model->getMorphClass());

        /** @var Attachment $attachment */
        $attachment = $model->attachments()->create($meta + [
            'collection' => $collection,
            'description' => $description,
            'uploaded_by' => Auth::id(),
        ]);

        Audit::log('file_uploaded', $model, $attachment->original_name);

        return $attachment;
    }

    public static function delete(string $disk, string $path): void
    {
        Storage::disk($disk)->delete($path);
    }

    public static function cleanName(string $name): string
    {
        $name = preg_replace('/[^\pL\pN\s._()-]+/u', '_', $name) ?? 'file';

        return mb_substr(trim($name), 0, 200) ?: 'file';
    }
}
