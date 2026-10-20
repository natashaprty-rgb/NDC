<?php

namespace App\Http\Controllers\Budgets\Concerns;

use App\Models\Budget;
use App\Models\Expense;

/**
 * Authorises an action through the policy and, separately, checks that the
 * record's status allows it. The second check also binds Super
 * Administrators, who pass every policy through Gate::before.
 */
trait AuthorizesWorkflow
{
    protected function authorizeWorkflow(string $ability, Budget|Expense $model): void
    {
        $this->authorize($ability, $model);
        abort_unless($model->allows($ability), 403, __('This action is not available in the record\'s current status.'));
    }
}
