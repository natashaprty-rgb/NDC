{{-- Attachment list + upload form for any model using HasAttachments.
     Upload/delete shown only when the user may `attach`; server re-checks. --}}
@props(['model', 'title' => null, 'categories' => null])
@php
    $type = $model->getMorphClass();
    $canAttach = auth()->user()->can('attach', $model);
    $items = $model->attachments()->with('uploader')->get();
    $categories ??= \App\Http\Controllers\AttachmentController::CATEGORIES;
@endphp
<x-card :title="$title ?? __('common.attachments')" :flush="true">
    @if($items->isEmpty())
        <x-empty :message="__('common.no_attachments')" icon="file" />
    @else
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>{{ __('common.file') }}</th><th>{{ __('common.category') }}</th><th>{{ __('common.size') }}</th><th>{{ __('common.uploaded_by') }}</th><th></th></tr></thead>
                <tbody>
                @foreach($items as $att)
                    <tr>
                        <td><a href="{{ route('attachments.download', $att) }}">{{ $att->original_name }}</a></td>
                        <td>{{ status_label($att->category) }}</td>
                        <td class="nowrap">{{ $att->humanSize() }}</td>
                        <td class="small">{{ $att->uploader?->name ?? '—' }}<br><span class="muted">{{ fmt_datetime($att->created_at) }}</span></td>
                        <td class="text-right nowrap">
                            @if(in_array($att->mime_type, config('ndc.inline_preview_mimes'), true))
                                <a class="btn btn--sm" href="{{ route('attachments.download', [$att, 'preview' => 1]) }}" target="_blank" rel="noopener">{{ __('common.preview') }}</a>
                            @endif
                            @if($canAttach)
                                <x-delete-button :action="route('attachments.destroy', $att)" :label="__('common.remove')" />
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
    @if($canAttach)
        <x-slot:footer>
            <form method="POST" action="{{ route('attachments.store', [$type, $model->getKey()]) }}" enctype="multipart/form-data" class="form-grid">
                @csrf
                <x-field.file name="file" :required="true" />
                <x-field.select name="category" :label="__('common.category')" :options="enum_options($categories)" :placeholder="false" value="general" />
                <div class="span-2 text-right"><button type="submit" class="btn btn--primary btn--sm"><x-icon name="upload" width="14" height="14" /> {{ __('common.upload') }}</button></div>
            </form>
        </x-slot:footer>
    @endif
</x-card>
