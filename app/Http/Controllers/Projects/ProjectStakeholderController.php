<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectStakeholder;
use App\Support\Status;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Contractors, consultants, stakeholders and contact persons of a project.
 */
class ProjectStakeholderController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $project->stakeholders()->create($this->validated($request));

        return $this->back($project, __('Stakeholder added.'));
    }

    public function edit(Project $project, ProjectStakeholder $stakeholder): View
    {
        $this->authorize('update', $project);

        return view('projects.stakeholders.edit', ['project' => $project, 'stakeholder' => $stakeholder]);
    }

    public function update(Request $request, Project $project, ProjectStakeholder $stakeholder): RedirectResponse
    {
        $this->authorize('update', $project);

        $stakeholder->update($this->validated($request));

        return $this->back($project, __('Stakeholder updated.'));
    }

    public function destroy(Project $project, ProjectStakeholder $stakeholder): RedirectResponse
    {
        $this->authorize('update', $project);

        $stakeholder->delete();

        return $this->back($project, __('Stakeholder removed.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(Status::keys('stakeholder_type'))],
            'name' => ['required', 'string', 'max:255'],
            'organization' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function back(Project $project, string $message): RedirectResponse
    {
        return redirect()->route('projects.show', [$project, 'tab' => 'stakeholders'])->with('success', $message);
    }
}
