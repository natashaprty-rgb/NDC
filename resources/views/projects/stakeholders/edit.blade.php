<x-layouts.app :title="__('Edit stakeholder')">
    <x-page-header :title="__('Edit stakeholder')" :subtitle="$project->code.' — '.$project->title"
        :breadcrumbs="[__('Projects') => route('projects.index'), $project->code => route('projects.show', [$project, 'tab' => 'stakeholders'])]" />
    <form method="POST" action="{{ route('projects.stakeholders.update', [$project, $stakeholder]) }}" class="card form">
        @csrf @method('PUT')
        <div class="card__body">
            @include('projects.stakeholders._fields')
        </div>
        <div class="card__foot form-actions">
            <button class="btn btn-primary">{{ __('Save') }}</button>
            <a href="{{ route('projects.show', [$project, 'tab' => 'stakeholders']) }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
