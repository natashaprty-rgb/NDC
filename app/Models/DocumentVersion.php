<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVersion extends Model
{
    protected $fillable = ['document_id', 'version_number', 'original_name', 'disk', 'path', 'mime_type', 'size', 'checksum', 'change_note', 'uploaded_by'];

    protected $hidden = ['path', 'disk'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class)->withTrashed();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }

    public function humanSize(): string
    {
        $kb = $this->size / 1024;

        return $kb >= 1024 ? round($kb / 1024, 1).' MB' : round($kb, 1).' KB';
    }
}
