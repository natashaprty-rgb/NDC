<x-layouts.app :title="__('Edit risk register entry')">
    <x-page-header :title="__('Edit risk register entry')" :subtitle="$project->code.' — '.$project->title"
        :breadcrumbs="[__('Projects') => route('projects.index'), $project->code => route('projects.show', [$project, 'tab' => 'risks'])]" />
    <form method="POST" action="{{ route('projects.risks.update', [$project, $risk]) }}" class="card form">
        @csrf @method('PUT')
        <div class="card__body">
            @include('projects.risks._fields')
        </div>
        <div class="card__foot form-actions">
            <button class="btn btn-primary">{{ __('Save') }}</button>
            <a href="{{ route('projects.show', [$project, 'tab' => 'risks']) }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
