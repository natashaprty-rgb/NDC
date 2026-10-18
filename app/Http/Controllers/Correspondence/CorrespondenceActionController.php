<?php

namespace App\Http\Controllers\Correspondence;

use App\Http\Controllers\Controller;
use App\Models\Correspondence;
use App\Services\Correspondence\CorrespondenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Forwarding, follow-up and status tracking. */
class CorrespondenceActionController extends Controller
{
    public function __construct(private readonly CorrespondenceService $service) {}

    public function forward(Request $request, Correspondence $correspondence): RedirectResponse
    {
        $this->authorize('act', $correspondence);

        $data = $request->validate([
            'to_user_id' => ['nullable', 'integer', 'required_without:to_department_id', Rule::exists('users', 'id')->whereNull('deleted_at')->where('status', 'active')],
            'to_department_id' => ['nullable', 'integer', 'required_without:to_user_id', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [], ['to_user_id' => __('correspondence.forward_to_user'), 'to_department_id' => __('correspondence.forward_to_department')]);

        $this->service->forward($correspondence, $request->user(), $data['to_user_id'] ?? null, $data['to_department_id'] ?? null, $data['note'] ?? null);

        return back()->with('success', __('correspondence.forwarded'));
    }

    public function followUp(Request $request, Correspondence $correspondence): RedirectResponse
    {
        $this->authorize('act', $correspondence);

        $data = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
            'follow_up_date' => ['nullable', 'date'],
        ], [], ['note' => __('common.notes'), 'follow_up_date' => __('correspondence.follow_up_date')]);

        $this->service->followUp($correspondence, $request->user(), $data['note'], $data['follow_up_date'] ?? null);

        return back()->with('success', __('correspondence.follow_up_recorded'));
    }

    public function changeStatus(Request $request, Correspondence $correspondence): RedirectResponse
    {
        $this->authorize('act', $correspondence);

        $data = $request->validate([
            'status' => ['required', Rule::in(Correspondence::STATUSES), Rule::notIn([$correspondence->status])],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [], ['status' => __('common.status')]);

        $this->service->changeStatus($correspondence, $request->user(), $data['status'], $data['note'] ?? null);

        return back()->with('success', __('correspondence.status_changed'));
    }
}
