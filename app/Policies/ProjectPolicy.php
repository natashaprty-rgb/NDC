<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * Projects are visible to everyone holding projects.view. Archived projects
 * are read-only until they are restored.
 *
 * "update" also covers milestones, risks, stakeholders, team members and
 * attachments, which are managed from the project page.
 */
class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('projects.view');
    }

    public function view(User $user, Project $project): bool
    {
        return $user->can('projects.view');
    }

    public function create(User $user): bool
    {
        return $user->can('projects.create');
    }

    public function update(User $user, Project $project): bool
    {
        return $user->can('projects.update') && ! $project->isArchived();
    }

    public function archive(User $user, Project $project): bool
    {
        return $user->can('projects.archive') && ! $project->isArchived();
    }

    public function unarchive(User $user, Project $project): bool
    {
        return $user->can('projects.archive') && $project->isArchived();
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->can('projects.archive');
    }
}
