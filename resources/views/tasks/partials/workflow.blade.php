{{-- Side panel of the task page: the workflow actions available to the current user. --}}
@php
    $canStart = $user->can('start', $task);
    $canHandIn = $user->can('handIn', $task);
    $canResume = $user->can('resume', $task);
    $canProgress = $user->can('progress', $task);
@endphp

<div class="card">
    <div class="card__head"><h2>{{ __('Workflow') }}</h2></div>
    <div class="card__body">
        @error('status')<div class="alert alert--error" role="alert">{{ $message }}</div>@enderror

        @if ($canStart || $canResume)
            <form method="POST" action="{{ $canStart ? route('tasks.start', $task) : route('tasks.resume', $task) }}" style="margin-bottom:1rem">
                @csrf
                <button class="btn btn-primary" style="width:100%">{{ $canStart ? __('Start work') : __('Resume work') }}</button>
            </form>
        @endif

        @if ($canProgress)
            <form method="POST" action="{{ route('tasks.progress', $task) }}" style="margin-bottom:1rem">
                @csrf @method('PATCH')
                <x-form.input type="number" name="progress" :label="__('Progress (%)')" :value="$task->progress" min="0" max="100" step="5" required id="wf_progress" />
                <button class="btn" style="width:100%">{{ __('Update progress') }}</button>
            </form>
        @endif

        @if ($canHandIn)
            <form method="POST" action="{{ route('tasks.hand-in', $task) }}">
                @csrf
                <x-form.textarea name="note" :label="__('Note (optional)')" rows="2" maxlength="2000" id="wf_handin_note" />
                @if ($task->requires_review)
                    <button class="btn btn-accent" style="width:100%">{{ __('Submit for review') }}</button>
                    <p class="help mb-0">{{ __('A reviewer will approve the work or return it for rework.') }}</p>
                @else
                    <button class="btn btn-success" style="width:100%">{{ __('Mark as completed') }}</button>
                @endif
            </form>
        @endif

        @unless ($canStart || $canResume || $canProgress || $canHandIn)
            <p class="muted mb-0">
                @switch($task->status)
                    @case('submitted') {{ __('The work has been submitted and is awaiting review.') }} @break
                    @case('completed') {{ __('This task has been completed.') }} @break
                    @case('cancelled') {{ __('This task has been cancelled.') }} @break
                    @default {{ __('No workflow actions are available to you on this task.') }}
                @endswitch
            </p>
        @endunless
    </div>
</div>

@if ($task->isAwaitingReview())
    @if (! $task->isAssignedTo($user) && $user->can('review', $task))
        <form method="POST" action="{{ route('tasks.approve', $task) }}" class="card">
            @csrf
            <div class="card__head"><h2>{{ __('Review') }}</h2></div>
            <div class="card__body">
                <x-form.textarea name="review_comment" :label="__('Review comment')" rows="3" maxlength="5000" id="wf_review_comment"
                    :help="__('Required when returning the task for rework.')" />
                <div class="form-actions">
                    <button type="submit" class="btn btn-success">{{ __('Approve') }}</button>
                    <button type="submit" class="btn btn-danger" formaction="{{ route('tasks.reject', $task) }}">{{ __('Return for rework') }}</button>
                </div>
            </div>
        </form>
    @elseif ($user->can('tasks.review') && $task->isAssignedTo($user))
        <div class="card"><div class="card__body small muted">{{ __('This task is assigned to you; another reviewer must approve or return it.') }}</div></div>
    @endif
@endif

@can('reassign', $task)
    <form method="POST" action="{{ route('tasks.reassign', $task) }}" class="card">
        @csrf
        <div class="card__head"><h2>{{ __('Reassign') }}</h2></div>
        <div class="card__body">
            <x-form.select name="assigned_to" :label="__('Staff member')" :options="$users" :value="$task->assigned_to" id="wf_assigned_to" />
            <x-form.select name="department_id" :label="__('Department')" :options="$departments" :value="$task->department_id" id="wf_department_id" />
            <x-form.textarea name="note" :label="__('Reason (optional)')" rows="2" maxlength="2000" id="wf_reassign_note" />
            <button class="btn" style="width:100%">{{ __('Reassign task') }}</button>
        </div>
    </form>
@endcan

@if ($user->can('cancel', $task) || $user->can('reopen', $task))
    <div class="card">
        <div class="card__body">
            @can('cancel', $task)
                <x-delete-button :action="route('tasks.cancel', $task)" method="POST" :label="__('Cancel task')" class="btn btn-danger"
                    :confirm="__('Cancel this task? The assignee will be notified.')" />
            @endcan
            @can('reopen', $task)
                <x-delete-button :action="route('tasks.reopen', $task)" method="POST" :label="__('Reopen task')" class="btn"
                    :confirm="__('Reopen this task and return it to the assignee?')" />
            @endcan
        </div>
    </div>
@endif
