<x-layout :title="$document->title" :subtitle="$document->reference"
          :breadcrumbs="[__('documents.title') => route('documents.index'), $document->reference]">
    <x-slot:actions>
        @can('update', $document)
            <a href="{{ route('documents.edit', $document) }}" class="btn"><x-icon name="edit" width="16" height="16" /> {{ __('common.edit') }}</a>
        @endcan
        @can('submitForReview', $document)
            <form method="POST" action="{{ route('documents.submit', $document) }}" class="inline-form">@csrf
                <button type="submit" class="btn btn--primary">{{ __('documents.submit_review') }}</button>
            </form>
        @endcan
        @can('archive', $document)
            <x-delete-button :action="route('documents.archive', $document)" method="POST" :label="__('common.archive')" :message="__('common.confirm_archive')" class="btn btn--danger" />
        @endcan
        @can('restore', $document)
            <form method="POST" action="{{ route('documents.restore', $document) }}" class="inline-form">@csrf
                <button type="submit" class="btn">{{ __('common.restore') }}</button>
            </form>
        @endcan
    </x-slot:actions>

    @if($document->isExpired() && $document->status !== 'archived')
        <div class="alert alert--warning" role="status">{{ __('documents.expired_notice', ['date' => fmt_date($document->expiry_date)]) }}</div>
    @endif
    @if($document->status === 'rejected' && $document->review_note)
        <div class="alert alert--danger" role="status"><strong>{{ __('documents.rejected_reason') }}</strong> {{ $document->review_note }}</div>
    @endif

    <div class="layout-main-side">
        <div>
            @can('approve', $document)
                <x-card :title="__('documents.review_decision')">
                    <form method="POST" action="{{ route('documents.approve', $document) }}">
                        @csrf
                        <x-field.textarea name="review_note" :label="__('documents.review_note')" :rows="3" :help="__('documents.review_note_help')" />
                        <div class="form-actions">
                            <button type="submit" formaction="{{ route('documents.reject', $document) }}" class="btn btn--danger">{{ __('common.reject') }}</button>
                            <button type="submit" class="btn btn--success">{{ __('common.approve') }}</button>
                        </div>
                    </form>
                </x-card>
            @endcan

            <x-card :title="__('documents.versions')" :flush="true">
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>{{ __('documents.version') }}</th><th>{{ __('common.file') }}</th><th>{{ __('documents.change_note') }}</th><th>{{ __('common.uploaded_by') }}</th><th>{{ __('common.size') }}</th><th></th></tr></thead>
                    <tbody>
                    @foreach($document->versions as $version)
                        <tr>
                            <td class="nowrap">v{{ $version->version_number }} @if($version->version_number === $document->current_version)<x-badge value="active" :label="__('documents.current')" />@endif</td>
                            <td>{{ $version->original_name }}</td>
                            <td class="small">{{ $version->change_note ?? '—' }}</td>
                            <td class="small">{{ $version->uploader?->name ?? '—' }}<br><span class="muted">{{ fmt_datetime($version->created_at) }}</span></td>
                            <td class="nowrap">{{ $version->humanSize() }}</td>
                            <td class="text-right nowrap">
                                @if(in_array($version->mime_type, config('ndc.inline_preview_mimes'), true))
                                    <a class="btn btn--sm" href="{{ route('documents.versions.download', [$document, $version, 'preview' => 1]) }}" target="_blank" rel="noopener">{{ __('common.preview') }}</a>
                                @endif
                                <a class="btn btn--sm" href="{{ route('documents.versions.download', [$document, $version]) }}"><x-icon name="download" width="14" height="14" /> {{ __('common.download') }}</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
                @can('uploadVersion', $document)
                    <x-slot:footer>
                        <form method="POST" action="{{ route('documents.versions.store', $document) }}" enctype="multipart/form-data" class="form-grid">
                            @csrf
                            <x-field.file name="file" :label="__('documents.new_version')" :required="true" />
                            <x-field.input name="change_note" :label="__('documents.change_note')" maxlength="500" />
                            @if($document->status === 'approved')
                                <p class="span-2 small muted mb-0">{{ __('documents.new_version_resets') }}</p>
                            @endif
                            <div class="span-2 text-right"><button type="submit" class="btn btn--primary btn--sm"><x-icon name="upload" width="14" height="14" /> {{ __('documents.upload_version') }}</button></div>
                        </form>
                    </x-slot:footer>
                @endcan
            </x-card>

            @if(in_array($document->confidentiality, ['confidential', 'restricted'], true) || $document->accessGrants->isNotEmpty())
                <x-card :title="__('documents.access_grants')" :flush="true">
                    @if($document->accessGrants->isEmpty())
                        <x-empty :message="__('documents.no_grants')" icon="key" />
                    @else
                        <div class="table-wrap"><table class="table">
                            <thead><tr><th>{{ __('documents.grantee') }}</th><th>{{ __('documents.access_level') }}</th><th></th></tr></thead>
                            <tbody>
                            @foreach($document->accessGrants as $grant)
                                <tr>
                                    <td>{{ $grant->user?->name ?? ($grant->department ? __('common.department').': '.$grant->department->displayName() : '—') }}</td>
                                    <td><x-badge :value="$grant->level" /></td>
                                    <td class="text-right">
                                        @can('manageAccess', $document)
                                            <x-delete-button :action="route('documents.access.destroy', [$document, $grant])" :label="__('documents.revoke')" :message="__('documents.confirm_revoke')" />
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table></div>
                    @endif
                    @can('manageAccess', $document)
                        <x-slot:footer>
                            <form method="POST" action="{{ route('documents.access.store', $document) }}" class="form-grid">
                                @csrf
                                <x-field.select name="user_id" :label="__('documents.grant_user')" :options="$users" :placeholder="__('common.none')" />
                                <x-field.select name="department_id" :label="__('documents.grant_department')" :options="$departments" :placeholder="__('common.none')" />
                                <x-field.select name="level" :label="__('documents.access_level')" :options="enum_options(\App\Models\DocumentAccess::LEVELS)" value="view" :placeholder="false" />
                                <div class="text-right" style="align-self:end"><button type="submit" class="btn btn--primary btn--sm">{{ __('documents.grant') }}</button></div>
                            </form>
                        </x-slot:footer>
                    @endcan
                </x-card>
            @endif
        </div>
        <div>
            <x-card :title="__('common.details')">
                <dl class="dl">
                    <dt>{{ __('common.status') }}</dt><dd><x-badge :value="$document->status" /></dd>
                    <dt>{{ __('documents.confidentiality') }}</dt><dd><x-badge :value="$document->confidentiality" /></dd>
                    <dt>{{ __('common.category') }}</dt><dd>{{ $document->category?->displayName() ?? '—' }}</dd>
                    <dt>{{ __('common.department') }}</dt><dd>{{ $document->department?->displayName() ?? '—' }}</dd>
                    <dt>{{ __('common.project') }}</dt><dd>@if($document->project)<a href="{{ route_if('projects.show', $document->project) ?? '#' }}">{{ $document->project->name }}</a>@else — @endif</dd>
                    <dt>{{ __('common.owner') }}</dt><dd>{{ $document->owner?->name ?? '—' }}</dd>
                    <dt>{{ __('documents.registration_date') }}</dt><dd>{{ fmt_date($document->registration_date) }}</dd>
                    <dt>{{ __('documents.review_date') }}</dt><dd>{{ fmt_date($document->review_date) }}</dd>
                    <dt>{{ __('documents.expiry_date') }}</dt><dd>{{ fmt_date($document->expiry_date) }}</dd>
                    <dt>{{ __('documents.retain_until') }}</dt><dd>{{ fmt_date($document->retain_until) }}</dd>
                    <dt>{{ __('documents.approved_by') }}</dt><dd>{{ $document->approvedBy ? $document->approvedBy->name.' · '.fmt_datetime($document->approved_at) : '—' }}</dd>
                    @if($document->archived_at)<dt>{{ __('documents.archived_at') }}</dt><dd>{{ fmt_datetime($document->archived_at) }}</dd>@endif
                </dl>
                @if($document->description)<p class="prose mt-2 mb-0">{{ $document->description }}</p>@endif
            </x-card>
            <x-history :model="$document" />
        </div>
    </div>
</x-layout>
