@php
    $user = auth()->user();
    $canWork = $user->can('update', $task);
@endphp
<x-layouts.app :title="$task->title">
    <x-page-header :title="$task->title" :breadcrumbs="[__('Tasks') => route('tasks.index')]">
        <x-slot:meta>
            <div style="display:flex;flex-wrap:wrap;gap:.4rem;align-items:center;margin-top:.4rem">
                <x-status domain="task" :value="$task->status" />
                <x-status domain="priority" :value="$task->priority" />
                @if ($task->isOverdue())<span class="badge badge--danger">{{ __('Overdue') }}</span>@endif
            </div>
        </x-slot:meta>
        <x-slot:actions>
            @can('edit', $task)
                <a href="{{ route('tasks.edit', $task) }}" class="btn"><x-icon name="edit" /> {{ __('Edit') }}</a>
            @endcan
            @can('delete', $task)
                <x-delete-button :action="route('tasks.destroy', $task)" class="btn btn-danger" :confirm="__('Delete this task?')" />
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if ($task->status === 'rejected' && $task->review_comment)
        <div class="alert alert--warning" role="status">
            <strong>{{ __('Returned for rework by :name', ['name' => $task->reviewer?->name ?? '—']) }}:</strong>
            <span class="prose">{{ $task->review_comment }}</span>
        </div>
    @elseif ($task->status === 'submitted')
        <div class="alert alert--info" role="status">{{ __('Submitted for review on :date. Waiting for a reviewer to approve or return the work.', ['date' => fmt_date($task->submitted_at, true)]) }}</div>
    @endif

    <div class="grid grid-main">
        <div class="stack">
            <div class="card">
                <div class="card__head"><h2>{{ __('Task details') }}</h2></div>
                <div class="card__body">
                    <dl class="dl">
                        <dt>{{ __('Status') }}</dt><dd><x-status domain="task" :value="$task->status" /></dd>
                        <dt>{{ __('Progress') }}</dt><dd><x-progress :value="$task->progress" :tone="$task->isOverdue() ? 'danger' : null" /></dd>
                        <dt>{{ __('Assigned to') }}</dt><dd>{{ $task->assignee?->name ?? '—' }}</dd>
                        <dt>{{ __('Department') }}</dt><dd>{{ $task->department?->displayName() ?? '—' }}</dd>
                        <dt>{{ __('Project') }}</dt>
                        <dd>@if ($task->project)
                                @can('view', $task->project)<a href="{{ route('projects.show', $task->project) }}">{{ $task->project->code }} — {{ $task->project->title }}</a>
                                @else{{ $task->project->code }} — {{ $task->project->title }}@endcan
                            @else — @endif</dd>
                        <dt>{{ __('Milestone') }}</dt><dd>{{ $task->milestone?->title ?? '—' }}</dd>
                        <dt>{{ __('Priority') }}</dt><dd><x-status domain="priority" :value="$task->priority" /></dd>
                        <dt>{{ __('Start date') }}</dt><dd>{{ fmt_date($task->start_date) }}</dd>
                        <dt>{{ __('Due date') }}</dt><dd>{{ fmt_date($task->due_date) }} @if ($task->isOverdue())<span class="badge badge--danger">{{ __('Overdue') }}</span>@endif</dd>
                        <dt>{{ __('Review') }}</dt><dd>{{ $task->requires_review ? __('Completed work must be reviewed and approved') : __('Not required') }}</dd>
                        <dt>{{ __('Created by') }}</dt><dd>{{ $task->creator?->name ?? '—' }} · {{ fmt_date($task->created_at, true) }}</dd>
                        @if ($task->submitted_at)
                            <dt>{{ __('Submitted') }}</dt><dd>{{ fmt_date($task->submitted_at, true) }}</dd>
                        @endif
                        @if ($task->reviewed_at)
                            <dt>{{ __('Reviewed') }}</dt><dd>{{ $task->reviewer?->name ?? '—' }} · {{ fmt_date($task->reviewed_at, true) }}
                                @if ($task->review_comment)<div class="small prose muted">“{{ $task->review_comment }}”</div>@endif</dd>
                        @endif
                        @if ($task->completed_at)
                            <dt>{{ __('Completed') }}</dt><dd>{{ fmt_date($task->completed_at, true) }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card__head"><h2>{{ __('Description') }}</h2></div>
                <div class="card__body">
                    <p class="prose">{{ $task->description ?: __('No description recorded.') }}</p>
                    <h3>{{ __('Expected deliverable') }}</h3>
                    <p class="prose mb-0">{{ $task->expected_deliverable ?: '—' }}</p>
                </div>
            </div>

            <div class="card" id="comments">
                <div class="card__head"><h2>{{ __('Comments and completion evidence') }}</h2>
                    <span class="muted small">{{ trans_choice(':count comment|:count comments', $task->comments->count()) }}</span></div>
                <div class="card__body">
                    @forelse ($task->comments as $comment)
                        <div class="comment">
                            <div class="comment__head">
                                <strong>{{ $comment->user?->name ?? __('System') }}</strong> · {{ fmt_date($comment->created_at, true) }}
                                @if ($comment->is_evidence)<span class="badge badge--success">{{ __('Completion evidence') }}</span>@endif
                            </div>
                            <div class="prose">{{ $comment->body }}</div>
                            @foreach ($comment->attachments as $file)
                                <div class="small"><x-icon name="paperclip" /> <a href="{{ route('attachments.download', $file) }}">{{ $file->original_name }}</a>
                                    <span class="muted">({{ $file->humanSize() }})</span></div>
                            @endforeach
                        </div>
                    @empty
                        <p class="muted">{{ __('No comments yet.') }}</p>
                    @endforelse
                </div>
                @can('comment', $task)
                    <div class="card__foot">
                        <form method="POST" action="{{ route('tasks.comments.store', $task) }}" enctype="multipart/form-data">
                            @csrf
                            <x-form.textarea name="body" :label="__('Add a comment')" rows="3" required maxlength="5000" />
                            @if ($canWork)
                                <div class="form-grid">
                                    <x-form.input type="file" name="file" :label="__('Attach a file (optional)')"
                                        :help="__('Allowed: :types (max :size MB)', ['types' => strtoupper(str_replace(',', ', ', setting('upload_allowed_extensions'))), 'size' => round(setting('upload_max_kb') / 1024)])" />
                                    <x-form.checkbox name="is_evidence" :label="__('This is evidence of completed work')" />
                                </div>
                            @endif
                            <div class="form-actions"><button class="btn btn-primary">{{ __('Post comment') }}</button></div>
                        </form>
                    </div>
                @endcan
            </div>

            <x-attachments :model="$task" :can-upload="$canWork" />
            <x-audit-trail :model="$task" :limit="30" />
        </div>

        <div class="stack">
            @include('tasks.partials.workflow')
        </div>
    </div>
</x-layouts.app>
