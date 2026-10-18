<x-layout :title="__('audit.record_review')" :breadcrumbs="[__('audit.reviews_title') => route('admin.permission-reviews.index'), __('audit.record_review')]">
    <x-card>
        <p class="muted small">{{ __('audit.record_help', ['n' => $accounts]) }}</p>
        <form method="POST" action="{{ route('admin.permission-reviews.store') }}">
            @csrf
            <div class="form-grid">
                <x-field.input name="review_date" type="date" :label="__('audit.review_date')" :value="today()" :required="true" />
                <x-field.input name="next_review_due" type="date" :label="__('audit.next_review_due')" :value="today()->addDays((int) \App\Support\Settings::get('security.permission_review_days', 90))" :required="true" />
                <x-field.textarea name="findings" :label="__('audit.findings')" :required="true" class="span-2" />
                <x-field.textarea name="actions_taken" :label="__('audit.actions_taken')" class="span-2" />
            </div>
            <div class="form-actions">
                <a href="{{ route('admin.permission-reviews.index') }}" class="btn">{{ __('common.cancel') }}</a>
                <button type="submit" class="btn btn--primary">{{ __('common.save') }}</button>
            </div>
        </form>
    </x-card>
</x-layout>
