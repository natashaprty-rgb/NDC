<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['type', 'title', 'description', 'likelihood', 'impact', 'status', 'corrective_action', 'owner_id', 'identified_on', 'resolved_on'])]
class ProjectRisk extends Model
{
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return ['identified_on' => 'date', 'resolved_on' => 'date'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
