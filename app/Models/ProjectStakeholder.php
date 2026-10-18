<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectStakeholder extends Model
{
    use Auditable, HasUserstamps;

    public const TYPES = ['stakeholder', 'contractor', 'consultant', 'sponsor', 'partner'];

    protected $fillable = ['project_id', 'type', 'name', 'organization', 'supplier_id', 'role_description', 'email', 'phone'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }
}
