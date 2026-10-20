<x-layouts.app :title="__('Edit milestone')">
    <x-page-header :title="__('Edit milestone')" :subtitle="$project->code.' — '.$project->title"
        :breadcrumbs="[__('Projects') => route('projects.index'), $project->code => route('projects.show', [$project, 'tab' => 'milestones'])]" />
    <form method="POST" action="{{ route('projects.milestones.update', [$project, $milestone]) }}" class="card form">
        @csrf @method('PUT')
        <div class="card__body">
            @include('projects.milestones._fields')
        </div>
        <div class="card__foot form-actions">
            <button class="btn btn-primary">{{ __('Save') }}</button>
            <a href="{{ route('projects.show', [$project, 'tab' => 'milestones']) }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
