<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Support\Status;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Milestones, deliverables and deadlines of a project.
 */
class ProjectMilestoneController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $this->validated($request);
        $data['sort_order'] ??= (int) $project->milestones()->max('sort_order') + 1;

        $project->milestones()->create($this->withCompletion($data, new ProjectMilestone));

        return $this->back($project, __('Milestone added.'));
    }

    public function edit(Project $project, ProjectMilestone $milestone): View
    {
        $this->authorize('update', $project);

        return view('projects.milestones.edit', ['project' => $project, 'milestone' => $milestone]);
    }

    public function update(Request $request, Project $project, ProjectMilestone $milestone): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $this->validated($request);
        $data['sort_order'] ??= $milestone->sort_order;
        $milestone->update($this->withCompletion($data, $milestone));

        return $this->back($project, __('Milestone updated.'));
    }

    public function complete(Project $project, ProjectMilestone $milestone): RedirectResponse
    {
        $this->authorize('update', $project);

        $milestone->update($this->withCompletion(['status' => 'completed'], $milestone));

        return $this->back($project, __('Milestone marked as completed.'));
    }

    public function destroy(Project $project, ProjectMilestone $milestone): RedirectResponse
    {
        $this->authorize('update', $project);

        $milestone->delete();

        return $this->back($project, __('Milestone deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'deliverables' => ['nullable', 'string', 'max:5000'],
            'due_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(Status::keys('milestone'))],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
    }

    /**
     * Keep completed_at in step with the status.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withCompletion(array $data, ProjectMilestone $milestone): array
    {
        $data['completed_at'] = $data['status'] === 'completed' ? ($milestone->completed_at ?? now()) : null;

        return $data;
    }

    private function back(Project $project, string $message): RedirectResponse
    {
        return redirect()->route('projects.show', [$project, 'tab' => 'milestones'])->with('success', $message);
    }
}
