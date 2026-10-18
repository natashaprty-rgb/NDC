<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('projects.view');
    }

    public function view(User $user, Project $project): bool
    {
        return $user->hasPermission('projects.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('projects.create');
    }

    /** Project managers may edit their own projects; projects.manage may edit any. */
    public function update(User $user, Project $project): bool
    {
        if ($project->isArchived()) {
            return false;
        }

        return $user->hasPermission('projects.manage')
            || ($user->hasPermission('projects.update') && $project->manager_id === $user->id);
    }

    /** Milestones, stakeholders, risks. */
    public function manageDetails(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    public function attach(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    public function archive(User $user, Project $project): bool
    {
        return $user->hasPermission('projects.manage') && ! $project->isArchived();
    }

    public function restore(User $user, Project $project): bool
    {
        return $user->hasPermission('projects.manage') && $project->isArchived();
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->archive($user, $project);
    }
}
