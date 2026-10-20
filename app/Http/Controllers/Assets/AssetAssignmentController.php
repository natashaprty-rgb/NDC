<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\User;
use App\Notifications\GenericNotification;
use App\Support\Audit;
use App\Support\Status;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Issue assets to employees or departments, take them back and transfer
 * them. Every movement is kept as an AssetAssignment row.
 */
class AssetAssignmentController extends Controller
{
    public function assign(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorize('update', $asset);

        if ($asset->status !== 'available' || $asset->openAssignment()->exists()) {
            return back()->with('error', __('Only available assets can be assigned.'));
        }
        if ($asset->openDisposal()->exists()) {
            return back()->with('error', __('This asset has an open disposal or retirement request.'));
        }

        $data = $this->validatedAssignment($request, 'assigned_on');

        $assignment = DB::transaction(fn () => $this->openNewAssignment($request, $asset, $data, $data['assigned_on']));

        Audit::log('assigned', $asset, $this->describe($assignment));
        $this->notifyAssignee($request, $asset, $assignment);

        return back()->with('success', __('Asset assigned.'));
    }

    public function return(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorize('update', $asset);

        $open = $asset->openAssignment()->first();
        if ($open === null) {
            return back()->with('error', __('This asset is not currently assigned.'));
        }

        $data = $request->validate([
            'returned_on' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$open->assigned_on->toDateString()],
            'condition_on_return' => ['required', Rule::in(Status::keys('asset_condition'))],
            'return_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($asset, $open, $data) {
            $this->closeAssignment($open, $data['returned_on'], $data['condition_on_return'], $data['return_notes'] ?? null);
            $asset->forceFill(['assigned_to' => null, 'condition' => $data['condition_on_return']])->save();
            $asset->settleStatus();
        });

        Audit::log('returned', $asset, $this->describe($open));

        return back()->with('success', __('Asset returned.'));
    }

    /**
     * Return from the current holder and assign to a new one in one step.
     */
    public function transfer(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorize('update', $asset);

        $open = $asset->openAssignment()->first();
        if ($open === null || $asset->status !== 'assigned') {
            return back()->with('error', __('Only assets that are currently assigned can be transferred.'));
        }

        $data = $this->validatedAssignment($request, 'transferred_on', [
            'transferred_on' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$open->assigned_on->toDateString()],
            'condition_on_transfer' => ['required', Rule::in(Status::keys('asset_condition'))],
        ]);

        $assignment = DB::transaction(function () use ($request, $asset, $open, $data) {
            $this->closeAssignment($open, $data['transferred_on'], $data['condition_on_transfer'], null);
            $asset->forceFill(['condition' => $data['condition_on_transfer']]);

            return $this->openNewAssignment($request, $asset, $data, $data['transferred_on']);
        });

        Audit::log('transferred', $asset, $this->describe($open).' → '.$this->describe($assignment));
        $this->notifyAssignee($request, $asset, $assignment);

        return back()->with('success', __('Asset transferred.'));
    }

    /**
     * @param  array<string, mixed>  $extraRules
     * @return array<string, mixed>
     */
    private function validatedAssignment(Request $request, string $dateField, array $extraRules = []): array
    {
        return $request->validate([
            'user_id' => ['nullable', 'required_without:department_id', Rule::exists('users', 'id')->where('status', 'active')],
            'department_id' => ['nullable', 'required_without:user_id', Rule::exists('departments', 'id')],
            'location' => ['nullable', 'string', 'max:255'],
            $dateField => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
            ...$extraRules,
        ], [
            'user_id.required_without' => __('Choose an employee or a department.'),
            'department_id.required_without' => __('Choose an employee or a department.'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function openNewAssignment(Request $request, Asset $asset, array $data, string $date): AssetAssignment
    {
        $user = isset($data['user_id']) ? User::query()->find($data['user_id']) : null;
        $departmentId = $data['department_id'] ?? $user?->department_id ?? $asset->department_id;
        $location = filled($data['location'] ?? null) ? $data['location'] : $asset->location;

        /** @var AssetAssignment $assignment */
        $assignment = $asset->assignments()->create([
            'user_id' => $user?->id,
            'department_id' => $departmentId,
            'location' => $location,
            'assigned_on' => $date,
            'assigned_by' => $request->user()->id,
            'notes' => $data['notes'] ?? null,
        ]);

        $asset->forceFill([
            'assigned_to' => $user?->id,
            'department_id' => $departmentId,
            'location' => $location,
            'status' => 'assigned',
        ])->save();

        return $assignment;
    }

    private function closeAssignment(AssetAssignment $assignment, string $date, string $condition, ?string $notes): void
    {
        $assignment->forceFill([
            'returned_on' => $date,
            'condition_on_return' => $condition,
            'notes' => filled($notes) ? trim(($assignment->notes ? $assignment->notes."\n" : '').$notes) : $assignment->notes,
        ])->save();
    }

    private function describe(AssetAssignment $assignment): string
    {
        $assignment->loadMissing(['user', 'department']);

        return collect([$assignment->user?->name, $assignment->department?->displayName(), $assignment->location])
            ->filter()->join(' · ') ?: '—';
    }

    private function notifyAssignee(Request $request, Asset $asset, AssetAssignment $assignment): void
    {
        $assignee = $assignment->user;
        if ($assignee && ! $assignee->is($request->user())) {
            $assignee->notify(new GenericNotification(
                'asset_assigned',
                'Asset assigned to you',
                'The asset ":tag — :name" has been assigned to you.',
                ['tag' => $asset->asset_tag, 'name' => $asset->name],
                route('assets.show', $asset),
            ));
        }
    }
}
