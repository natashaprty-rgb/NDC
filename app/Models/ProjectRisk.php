<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectRisk extends Model
{
    use Auditable, HasFactory, HasUserstamps;

    public const TYPES = ['risk', 'issue'];

    public const LEVELS = ['low', 'medium', 'high'];

    public const STATUSES = ['open', 'mitigating', 'closed'];

    protected $fillable = [
        'project_id', 'type', 'title', 'description', 'likelihood', 'impact', 'status', 'owner_id',
        'corrective_action', 'due_date', 'closed_at',
    ];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'closed_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id')->withTrashed();
    }

    /** Simple 1-9 severity score (likelihood x impact). */
    public function severity(): int
    {
        $score = ['low' => 1, 'medium' => 2, 'high' => 3];

        return ($score[$this->likelihood] ?? 2) * ($score[$this->impact] ?? 2);
    }
}
