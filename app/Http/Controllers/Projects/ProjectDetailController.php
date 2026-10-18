<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\MilestoneRequest;
use App\Http\Requests\Projects\RiskRequest;
use App\Http\Requests\Projects\StakeholderRequest;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectRisk;
use App\Models\ProjectStakeholder;
use Illuminate\Http\RedirectResponse;

/** Milestones, stakeholders and risks/issues of a project (ability: manageDetails). */
class ProjectDetailController extends Controller
{
    public function storeMilestone(MilestoneRequest $request, Project $project): RedirectResponse
    {
        $project->milestones()->create($this->milestoneData($request->validated()));

        return $this->back($project, 'milestones');
    }

    public function updateMilestone(MilestoneRequest $request, Project $project, ProjectMilestone $milestone): RedirectResponse
    {
        $milestone->update($this->milestoneData($request->validated(), $milestone));

        return $this->back($project, 'milestones');
    }

    public function destroyMilestone(Project $project, ProjectMilestone $milestone): RedirectResponse
    {
        $this->authorize('manageDetails', $project);
        $milestone->delete();

        return $this->back($project, 'milestones', 'common.deleted');
    }

    public function storeStakeholder(StakeholderRequest $request, Project $project): RedirectResponse
    {
        $project->stakeholders()->create($request->validated());

        return $this->back($project, 'stakeholders');
    }

    public function updateStakeholder(StakeholderRequest $request, Project $project, ProjectStakeholder $stakeholder): RedirectResponse
    {
        $stakeholder->update($request->validated());

        return $this->back($project, 'stakeholders');
    }

    public function destroyStakeholder(Project $project, ProjectStakeholder $stakeholder): RedirectResponse
    {
        $this->authorize('manageDetails', $project);
        $stakeholder->delete();

        return $this->back($project, 'stakeholders', 'common.deleted');
    }

    public function storeRisk(RiskRequest $request, Project $project): RedirectResponse
    {
        $project->risks()->create($this->riskData($request->validated()));

        return $this->back($project, 'risks');
    }

    public function updateRisk(RiskRequest $request, Project $project, ProjectRisk $risk): RedirectResponse
    {
        $risk->update($this->riskData($request->validated(), $risk));

        return $this->back($project, 'risks');
    }

    public function destroyRisk(Project $project, ProjectRisk $risk): RedirectResponse
    {
        $this->authorize('manageDetails', $project);
        $risk->delete();

        return $this->back($project, 'risks', 'common.deleted');
    }

    private function milestoneData(array $data, ?ProjectMilestone $existing = null): array
    {
        if ($data['status'] === 'completed') {
            $data['completed_at'] = $existing?->completed_at ?? now();
        } else {
            $data['completed_at'] = null;
        }
        $data['sort_order'] ??= 0;

        return $data;
    }

    private function riskData(array $data, ?ProjectRisk $existing = null): array
    {
        $data['closed_at'] = $data['status'] === 'closed' ? ($existing?->closed_at ?? now()) : null;

        return $data;
    }

    private function back(Project $project, string $section, string $message = 'common.saved'): RedirectResponse
    {
        return redirect()->to(route('projects.show', $project).'#'.$section)->with('success', __($message));
    }
}
