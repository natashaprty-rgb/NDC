@props(['model', 'canUpload' => false, 'collection' => 'default', 'title' => null])
{{-- Lists a record's attachments with download links and, when allowed, an upload form. --}}
@php
    $files = $model->attachments->when($collection !== '*', fn ($c) => $c->where('collection', $collection));
@endphp
<div class="card" id="attachments">
    <div class="card__head">
        <h2>{{ $title ?? __('Attachments') }}</h2>
        <span class="muted small">{{ trans_choice(':count file|:count files', $files->count()) }}</span>
    </div>
    @if ($files->isEmpty())
        <x-empty icon="paperclip" :message="__('No files attached yet.')" />
    @else
        <ul class="list">
            @foreach ($files as $file)
                <li>
                    <div class="list__row">
                        <div>
                            <a href="{{ route('attachments.download', $file) }}">{{ $file->original_name }}</a>
                            <div class="list__meta">
                                {{ $file->humanSize() }} · {{ $file->uploader?->name ?? __('System') }} · {{ fmt_date($file->created_at, true) }}
                                @if ($file->description) · {{ $file->description }} @endif
                            </div>
                        </div>
                        @if ($canUpload)
                            <x-delete-button :action="route('attachments.destroy', $file)" :label="__('Remove')" class="btn-link danger" />
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
    @if ($canUpload)
        <div class="card__foot">
            <form method="POST" action="{{ route('attachments.store') }}" enctype="multipart/form-data" class="grid" style="grid-template-columns:minmax(0,2fr) minmax(0,2fr) auto;align-items:end">
                @csrf
                <input type="hidden" name="attachable_type" value="{{ $model->getMorphClass() }}">
                <input type="hidden" name="attachable_id" value="{{ $model->getKey() }}">
                <input type="hidden" name="collection" value="{{ $collection === '*' ? 'default' : $collection }}">
                <x-form.input type="file" name="file" :label="__('File')" required :help="__('Allowed: :types (max :size MB)', ['types' => strtoupper(str_replace(',', ', ', setting('upload_allowed_extensions'))), 'size' => round(setting('upload_max_kb') / 1024)])" />
                <x-form.input name="description" :label="__('Description')" maxlength="255" />
                <div class="field"><button class="btn btn-primary" type="submit"><x-icon name="upload" /> {{ __('Upload') }}</button></div>
            </form>
        </div>
    @endif
</div>
