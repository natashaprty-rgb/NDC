@php $editing = $document->exists; @endphp
<x-layout :title="$editing ? __('documents.edit') : __('documents.new')"
          :breadcrumbs="[__('documents.title') => route('documents.index'), ($editing ? $document->reference : __('documents.new'))]">
    <x-card>
        <form method="POST" action="{{ $editing ? route('documents.update', $document) : route('documents.store') }}" @unless($editing) enctype="multipart/form-data" @endunless>
            @csrf
            @if($editing) @method('PUT') @endif
            <div class="form-grid">
                <x-field.input name="title" :label="__('documents.doc_title')" :value="$document->title" :required="true" maxlength="250" class="span-2" />
                <x-field.select name="category_id" :label="__('common.category')" :options="$categories" :value="$document->category_id" />
                <x-field.select name="confidentiality" :label="__('documents.confidentiality')" :options="enum_options(\App\Models\Document::CONFIDENTIALITY)" :value="$document->confidentiality" :required="true" :placeholder="false" :help="__('documents.confidentiality_help')" />
                <x-field.select name="department_id" :label="__('common.department')" :options="$departments" :value="$document->department_id" :placeholder="__('common.none')" />
                <x-field.select name="project_id" :label="__('common.project')" :options="$projects" :value="$document->project_id" :placeholder="__('common.none')" />
                <x-field.select name="owner_id" :label="__('common.owner')" :options="$users" :value="$document->owner_id" :placeholder="false" />
                <x-field.input name="registration_date" type="date" :label="__('documents.registration_date')" :value="$document->registration_date" :required="true" />
                <x-field.input name="review_date" type="date" :label="__('documents.review_date')" :value="$document->review_date" />
                <x-field.input name="expiry_date" type="date" :label="__('documents.expiry_date')" :value="$document->expiry_date" />
                <x-field.textarea name="description" :label="__('common.description')" :value="$document->description" class="span-2" />
                @unless($editing)
                    <x-field.file name="file" :label="__('documents.initial_file')" :required="true" />
                    <x-field.input name="change_note" :label="__('documents.change_note')" maxlength="500" />
                @endunless
            </div>
            <div class="form-actions">
                <a href="{{ $editing ? route('documents.show', $document) : route('documents.index') }}" class="btn">{{ __('common.cancel') }}</a>
                <button type="submit" class="btn btn--primary">{{ $editing ? __('common.save_changes') : __('documents.register') }}</button>
            </div>
        </form>
    </x-card>
</x-layout>
