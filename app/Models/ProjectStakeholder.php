<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['type', 'name', 'organization', 'role', 'email', 'phone', 'notes'])]
class ProjectStakeholder extends Model
{
    use Auditable;

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
