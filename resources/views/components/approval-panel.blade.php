@props(['model', 'title' => null])
{{-- Approval history for a record plus the decision form for eligible approvers. --}}
@php
    $service = app(\App\Services\ApprovalService::class);
    $approvals = $model->approvals()->with(['requester', 'decider'])->get();
    $pending = $approvals->firstWhere('status', 'pending');
@endphp
<div class="card" id="approvals">
    <div class="card__head"><h2>{{ $title ?? __('Approval') }}</h2>
        @if ($pending)<x-status domain="approval" value="pending" />@endif
    </div>
    @if ($approvals->isEmpty())
        <x-empty icon="stamp" :message="__('No approval has been requested yet.')" />
    @else
        <ul class="list">
            @foreach ($approvals as $approval)
                <li>
                    <div class="list__row">
                        <div>
                            <x-status domain="approval" :value="$approval->status" />
                            <span class="small">{{ __('Requested by :name on :date', ['name' => $approval->requester?->name ?? '—', 'date' => fmt_date($approval->created_at, true)]) }}</span>
                            @if ($approval->request_note)<div class="small prose">{{ $approval->request_note }}</div>@endif
                            @if ($approval->decided_at)
                                <div class="list__meta">{{ __('Decided by :name on :date', ['name' => $approval->decider?->name ?? '—', 'date' => fmt_date($approval->decided_at, true)]) }}</div>
                                @if ($approval->decision_comment)<div class="small prose">“{{ $approval->decision_comment }}”</div>@endif
                            @endif
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
    @if ($pending && $service->canDecide($pending, auth()->user()))
        <div class="card__foot">
            <form method="POST" action="{{ route('approvals.decide', $pending) }}">
                @csrf
                <x-form.textarea name="decision_comment" :label="__('Comment')" rows="2" :help="__('Required when rejecting.')" />
                <div class="form-actions">
                    <button type="submit" name="decision" value="approve" class="btn btn-success">{{ __('Approve') }}</button>
                    <button type="submit" name="decision" value="reject" class="btn btn-danger">{{ __('Reject') }}</button>
                </div>
            </form>
        </div>
    @elseif ($pending && $pending->requested_by === auth()->id())
        <div class="card__foot small muted">{{ __('You requested this approval; another authorised user must decide it.') }}</div>
    @endif
</div>
