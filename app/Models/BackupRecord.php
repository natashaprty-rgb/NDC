<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupRecord extends Model
{
    use Auditable;

    public const TYPES = ['database', 'files', 'full'];

    protected $fillable = [
        'type', 'method', 'location', 'file_name', 'size', 'checksum', 'status', 'started_at', 'completed_at',
        'restore_tested_at', 'restore_test_result', 'notes', 'recorded_by',
    ];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime', 'restore_tested_at' => 'datetime'];
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by')->withTrashed();
    }
}
