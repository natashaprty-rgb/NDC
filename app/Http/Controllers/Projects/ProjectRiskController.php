<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectRisk;
use App\Models\User;
use App\Support\Status;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Risks, challenges and delays of a project with their corrective actions.
 */
class ProjectRiskController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $project->risks()->create($this->validated($request));

        return $this->back($project, __('Entry added to the risk register.'));
    }

    public function edit(Project $project, ProjectRisk $risk): View
    {
        $this->authorize('update', $project);

        return view('projects.risks.edit', [
            'project' => $project,
            'risk' => $risk,
            'users' => User::query()->where(fn ($q) => $q->active()->when($risk->owner_id, fn ($q, $id) => $q->orWhere('id', $id)))
                ->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function update(Request $request, Project $project, ProjectRisk $risk): RedirectResponse
    {
        $this->authorize('update', $project);

        $risk->update($this->validated($request));

        return $this->back($project, __('Risk register entry updated.'));
    }

    public function destroy(Project $project, ProjectRisk $risk): RedirectResponse
    {
        $this->authorize('update', $project);

        $risk->delete();

        return $this->back($project, __('Risk register entry deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(Status::keys('risk_type'))],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'likelihood' => ['required', Rule::in(Status::keys('level'))],
            'impact' => ['required', Rule::in(Status::keys('level'))],
            'status' => ['required', Rule::in(Status::keys('risk'))],
            'corrective_action' => ['nullable', 'string', 'max:5000'],
            'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'identified_on' => ['nullable', 'date'],
            'resolved_on' => ['nullable', 'date', 'after_or_equal:identified_on'],
        ]);

        if ($data['status'] === 'closed' && empty($data['resolved_on'])) {
            $data['resolved_on'] = today()->toDateString();
        }

        return $data;
    }

    private function back(Project $project, string $message): RedirectResponse
    {
        return redirect()->route('projects.show', [$project, 'tab' => 'risks'])->with('success', $message);
    }
}
