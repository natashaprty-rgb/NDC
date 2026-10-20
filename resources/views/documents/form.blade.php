@php
    $title = $document->exists ? __('Edit document details') : __('Upload document');
    $allowed = strtoupper(str_replace(',', ', ', setting('upload_allowed_extensions')));
@endphp
<x-layouts.app :title="$title">
    <x-page-header :title="$title" :subtitle="$document->exists ? $document->reference_number.' — '.$document->title : null"
        :breadcrumbs="$document->exists ? [__('Documents') => route('documents.index'), $document->reference_number => route('documents.show', $document)] : [__('Documents') => route('documents.index')]" />
    <form method="POST" action="{{ $document->exists ? route('documents.update', $document) : route('documents.store') }}" class="card form" enctype="multipart/form-data">
        @csrf
        @if ($document->exists) @method('PUT') @endif
        <div class="card__body">
            <fieldset>
                <legend>{{ __('Registration') }}</legend>
                <div class="form-grid">
                    <x-form.input name="title" :label="__('Title')" :value="$document->title" required maxlength="255" class="span-2" />
                    @if ($document->exists)
                        <div class="field"><span class="label">{{ __('Reference number') }}</span><div class="mono">{{ $document->reference_number }}</div></div>
                    @else
                        <x-form.input name="reference_number" :label="__('Reference number')" maxlength="60"
                            :help="__('Leave blank to assign the next official reference automatically.')" />
                    @endif
                    <x-form.input name="document_date" type="date" :label="__('Document date')" :value="$document->document_date" />
                    <x-form.select name="category_id" :label="__('Document type')" :options="$categories" :value="$document->category_id" />
                    <x-form.select name="confidentiality" :label="__('Confidentiality')" :options="\App\Support\Status::options('confidentiality')" :value="$document->confidentiality" required :placeholder="false"
                        :help="__('Confidential and restricted documents are visible only to their owner, creator and authorised users.')" />
                    <x-form.select name="department_id" :label="__('Department')" :options="$departments" :value="$document->department_id" />
                    <x-form.select name="project_id" :label="__('Project')" :options="$projects" :value="$document->project_id" />
                    <x-form.select name="owner_id" :label="__('Owner')" :options="$owners" :value="$document->owner_id" />
                    <x-form.textarea name="description" :label="__('Description')" :value="$document->description" rows="3" maxlength="5000" class="span-2" />
                </div>
            </fieldset>
            <fieldset>
                <legend>{{ __('Review and expiry') }}</legend>
                <div class="form-grid">
                    <x-form.input name="review_date" type="date" :label="__('Next review date')" :value="$document->review_date" />
                    <x-form.input name="expiry_date" type="date" :label="__('Expiry / renewal date')" :value="$document->expiry_date"
                        :help="__('Used to track expiry and renewal deadlines.')" />
                </div>
            </fieldset>
            @unless ($document->exists)
                <fieldset>
                    <legend>{{ __('File') }}</legend>
                    <div class="form-grid">
                        <x-form.input type="file" name="file" :label="__('File')" required class="span-2"
                            :help="__('Allowed: :types (max :size MB)', ['types' => $allowed, 'size' => round(setting('upload_max_kb') / 1024)])" />
                        <x-form.input name="change_summary" :label="__('Version note')" maxlength="1000" class="span-2"
                            :help="__('Optional. Defaults to “Initial version”.')" />
                    </div>
                </fieldset>
            @endunless
        </div>
        <div class="card__foot form-actions">
            <button class="btn btn-primary">{{ $document->exists ? __('Save') : __('Upload and register') }}</button>
            <a href="{{ $document->exists ? route('documents.show', $document) : route('documents.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
