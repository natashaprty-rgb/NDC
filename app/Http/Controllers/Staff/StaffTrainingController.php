<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\StaffTraining;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Authorised training and professional development recorded on a staff
 * profile.
 */
class StaffTrainingController extends Controller
{
    public function store(Request $request, Staff $staff): RedirectResponse
    {
        $this->authorize('manageTrainings', $staff);

        $training = $staff->trainings()->create($this->validated($request));
        Audit::log('training_recorded', $staff, $training->title);

        return redirect()->to(route('staff.show', $staff).'#trainings')->with('success', __('Training record added.'));
    }

    public function edit(Staff $staff, StaffTraining $training): View
    {
        $this->authorize('manageTrainings', $staff);

        return view('staff.trainings.edit', ['staff' => $staff, 'training' => $training]);
    }

    public function update(Request $request, Staff $staff, StaffTraining $training): RedirectResponse
    {
        $this->authorize('manageTrainings', $staff);

        $training->update($this->validated($request));

        return redirect()->to(route('staff.show', $staff).'#trainings')->with('success', __('Training record updated.'));
    }

    public function destroy(Staff $staff, StaffTraining $training): RedirectResponse
    {
        $this->authorize('manageTrainings', $staff);

        $training->delete();
        Audit::log('training_removed', $staff, $training->title);

        return redirect()->to(route('staff.show', $staff).'#trainings')->with('success', __('Training record removed.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(array_keys(StaffTraining::TYPES))],
            'start_date' => ['nullable', 'date'],
            'end_date' => array_filter(['nullable', 'date', $request->filled('start_date') ? 'after_or_equal:start_date' : null]),
            'certificate_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
