<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['version', 'original_name', 'disk', 'path', 'mime_type', 'size', 'checksum', 'change_summary', 'uploaded_by'])]
class DocumentVersion extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['version' => 'integer', 'size' => 'integer'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function humanSize(): string
    {
        return format_bytes($this->size);
    }
}
