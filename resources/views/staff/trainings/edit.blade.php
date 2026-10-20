<x-layouts.app :title="__('Edit training record')">
    <x-page-header :title="__('Edit training record')" :subtitle="$staff->full_name"
        :breadcrumbs="[__('Staff') => route('staff.index'), $staff->full_name => route('staff.show', $staff)]" />
    <form method="POST" action="{{ route('staff.trainings.update', [$staff, $training]) }}" class="card form">
        @csrf
        @method('PUT')
        <div class="card__body">
            @include('staff.trainings._fields', ['training' => $training])
        </div>
        <div class="card__foot form-actions">
            <button class="btn btn-primary">{{ __('Save') }}</button>
            <a href="{{ route('staff.show', $staff) }}#trainings" class="btn btn-ghost">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
