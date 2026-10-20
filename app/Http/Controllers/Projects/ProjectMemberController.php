<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Notifications\GenericNotification;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Project team members (project_members pivot).
 */
class ProjectMemberController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('status', 'active'),
                Rule::unique('project_members', 'user_id')->where('project_id', $project->id)],
            'role' => ['nullable', 'string', 'max:100'],
        ], [
            'user_id.unique' => __('This user is already a member of the project team.'),
        ]);

        $user = User::query()->findOrFail($data['user_id']);
        $project->members()->attach($user->id, ['role' => $data['role'] ?? null]);
        Audit::log('member_added', $project, $user->name, null, ['user_id' => $user->id, 'role' => $data['role'] ?? null]);

        if (! $user->is($request->user())) {
            $user->notify(new GenericNotification(
                'project_member_added',
                'Added to a project team',
                'You have been added to the team of project ":title".',
                ['title' => $project->title],
                route('projects.show', $project),
            ));
        }

        return redirect()->route('projects.show', [$project, 'tab' => 'team'])->with('success', __('Team member added.'));
    }

    public function destroy(Project $project, User $user): RedirectResponse
    {
        $this->authorize('update', $project);

        if ($project->members()->detach($user->id) > 0) {
            Audit::log('member_removed', $project, $user->name, ['user_id' => $user->id]);
        }

        return redirect()->route('projects.show', [$project, 'tab' => 'team'])->with('success', __('Team member removed.'));
    }
}
