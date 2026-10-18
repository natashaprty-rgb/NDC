<x-layout :title="$task->title" :subtitle="$task->reference"
          :breadcrumbs="[__('tasks.title') => route('tasks.index'), $task->reference]">
    <x-slot:actions>
        @can('update', $task)
            <a href="{{ route('tasks.edit', $task) }}" class="btn"><x-icon name="edit" width="16" height="16" /> {{ __('common.edit') }}</a>
        @endcan
        @can('delete', $task)
            <x-delete-button :action="route('tasks.destroy', $task)" :message="__('tasks.confirm_delete')" class="btn btn--danger" />
        @endcan
    </x-slot:actions>

    <div class="layout-main-side">
        <div>
            <x-card :title="__('common.details')">
                <div class="mb-1"><x-progress :value="$task->progress" /></div>
                @if($task->description)<p class="prose">{{ $task->description }}</p>@endif
                @if($task->deliverables)<h3>{{ __('tasks.deliverables') }}</h3><p class="prose">{{ $task->deliverables }}</p>@endif
                @if($task->completion_evidence)
                    <h3>{{ __('tasks.completion_evidence') }}</h3><p class="prose">{{ $task->completion_evidence }}</p>
                @endif
                @if($task->review_note)
                    <div class="alert alert--info mb-0"><strong>{{ __('tasks.last_review_note') }}:</strong> {{ $task->review_note }}</div>
                @endif
            </x-card>

            @can('updateProgress', $task)
                <x-card :title="__('tasks.my_work')">
                    <form method="POST" action="{{ route('tasks.progress', $task) }}" class="form-grid">
                        @csrf
                        <x-field.input name="progress" type="number" :label="__('common.progress').' (%)'" :value="$task->progress" min="0" max="100" :required="true" />
                        <x-field.select name="status" :label="__('common.status')" :options="enum_options(['todo', 'in_progress'])" :value="$task->status" :placeholder="false" />
                        <div class="span-2 text-right"><button class="btn btn--sm" type="submit">{{ __('tasks.update_progress') }}</button></div>
                    </form>
                    <hr style="border:0;border-top:1px solid var(--c-border);margin:1rem 0">
                    <form method="POST" action="{{ route('tasks.submit', $task) }}" enctype="multipart/form-data" class="stack">
                        @csrf
                        <x-field.textarea name="completion_evidence" :label="__('tasks.completion_evidence')" :required="true" :help="__('tasks.completion_help')" rows="3" />
                        <x-field.file name="evidence_file" :label="__('tasks.evidence_file')" />
                        <div class="text-right"><button class="btn btn--primary btn--sm" type="submit">{{ __('tasks.submit_completion') }}</button></div>
                    </form>
                </x-card>
            @endcan

            @can('approve', $task)
                <x-card :title="__('tasks.approval')">
                    <p class="small">{{ __('tasks.awaiting_review', ['date' => fmt_datetime($task->submitted_at)]) }}</p>
                    <div class="grid grid-2">
                        <form method="POST" action="{{ route('tasks.approve', $task) }}" class="stack">
                            @csrf
                            <x-field.textarea name="review_note" :label="__('tasks.review_note')" rows="2" />
                            <button class="btn btn--success btn--sm" type="submit">{{ __('tasks.approve') }}</button>
                        </form>
                        <form method="POST" action="{{ route('tasks.reject', $task) }}" class="stack">
                            @csrf
                            <x-field.textarea name="review_note" :label="__('common.reason')" :required="true" rows="2" />
                            <button class="btn btn--danger btn--sm" type="submit">{{ __('tasks.reject') }}</button>
                        </form>
                    </div>
                </x-card>
            @endcan

            <x-card :title="__('common.comments')" id="comments">
                @if($task->comments->isEmpty())
                    <p class="muted">{{ __('tasks.no_comments') }}</p>
                @else
                    <ul class="timeline">
                        @foreach($task->comments as $comment)
                            <li><strong>{{ $comment->user?->name ?? __('common.system') }}</strong>
                                <div class="when">{{ fmt_datetime($comment->created_at) }}</div>
                                <div class="prose">{{ $comment->body }}</div></li>
                        @endforeach
                    </ul>
                @endif
                @can('comment', $task)
                    <form method="POST" action="{{ route('tasks.comments.store', $task) }}" class="stack mt-1">
                        @csrf
                        <x-field.textarea name="body" :label="__('common.add_comment')" :required="true" rows="2" />
                        <div class="text-right"><button class="btn btn--sm" type="submit">{{ __('common.add_comment') }}</button></div>
                    </form>
                @endcan
            </x-card>

            <x-attachments :model="$task" :categories="['general', 'evidence']" />
        </div>

        <div>
            <x-card :title="__('common.status')">
                <dl class="dl">
                    <dt>{{ __('common.status') }}</dt><dd><x-badge :value="$task->status" /> @if($task->isOverdue())<x-badge value="overdue" />@endif</dd>
                    <dt>{{ __('tasks.priority') }}</dt><dd><x-badge :value="$task->priority" /></dd>
                    <dt>{{ __('common.project') }}</dt><dd>@if($task->project)<a href="{{ route('projects.show', $task->project) }}">{{ $task->project->name }}</a>@else — @endif</dd>
                    <dt>{{ __('tasks.milestone') }}</dt><dd>{{ $task->milestone?->title ?? '—' }}</dd>
                    <dt>{{ __('common.department') }}</dt><dd>{{ $task->department?->displayName() ?? '—' }}</dd>
                    <dt>{{ __('common.assigned_to') }}</dt><dd>{{ $task->assignee?->name ?? __('common.unassigned') }}</dd>
                    <dt>{{ __('tasks.assigned_by') }}</dt><dd>{{ $task->assigner?->name ?? '—' }}</dd>
                    <dt>{{ __('common.created_by') }}</dt><dd>{{ $task->creator?->name ?? '—' }}</dd>
                    <dt>{{ __('tasks.start_date') }}</dt><dd>{{ fmt_date($task->start_date) }}</dd>
                    <dt>{{ __('tasks.due_date') }}</dt><dd>{{ fmt_date($task->due_date) }}</dd>
                    <dt>{{ __('tasks.requires_approval') }}</dt><dd>{{ $task->requires_approval ? __('common.yes') : __('common.no') }}</dd>
                    @if($task->requires_approval)
                        <dt>{{ __('tasks.approver') }}</dt><dd>{{ $task->approver?->name ?? '—' }}</dd>
                    @endif
                    <dt>{{ __('tasks.submitted_at') }}</dt><dd>{{ fmt_datetime($task->submitted_at) }}</dd>
                    <dt>{{ __('tasks.approved_by') }}</dt><dd>{{ $task->approvedBy?->name ?? '—' }}</dd>
                    <dt>{{ __('tasks.completed_at') }}</dt><dd>{{ fmt_datetime($task->completed_at) }}</dd>
                </dl>
            </x-card>

            @can('cancel', $task)
                <x-card :title="__('tasks.cancel_task')">
                    <form method="POST" action="{{ route('tasks.cancel', $task) }}" class="stack" data-confirm="{{ __('tasks.confirm_cancel') }}">
                        @csrf
                        <x-field.textarea name="reason" :label="__('common.reason')" :required="true" rows="2" />
                        <button class="btn btn--danger btn--sm" type="submit">{{ __('tasks.cancel_task') }}</button>
                    </form>
                </x-card>
            @endcan

            <x-history :model="$task" />
        </div>
    </div>
</x-layout>
