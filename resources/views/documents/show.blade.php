@php
    $me = auth()->user();
    $latest = $document->versions->first();
@endphp
<x-layouts.app :title="$document->title">
    <x-page-header :title="$document->title" :subtitle="$document->reference_number" :breadcrumbs="[__('Documents') => route('documents.index')]">
        <x-slot:meta>
            <p>
                <x-status domain="document" :value="$document->status" />
                <x-status domain="confidentiality" :value="$document->confidentiality" />
                @include('documents._date_flags')
            </p>
        </x-slot:meta>
        <x-slot:actions>
            @if ($latest)
                <a href="{{ route('documents.versions.download', [$document, $latest]) }}" class="btn btn-primary"><x-icon name="download" /> {{ __('Download') }} (v{{ $latest->version }})</a>
            @endif
            @can('update', $document)
                <a href="{{ route('documents.edit', $document) }}" class="btn"><x-icon name="edit" /> {{ __('Edit') }}</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if ($document->isArchived())
        <div class="alert alert--info">{{ __('This document was archived on :date. It is kept for reference and cannot be changed.', ['date' => fmt_date($document->archived_at, true)]) }}</div>
    @elseif ($document->isExpired())
        <div class="alert alert--warning">{{ __('This document expired on :date and needs renewal or archiving.', ['date' => fmt_date($document->expiry_date)]) }}</div>
    @endif

    <div class="grid grid-main">
        <div class="stack">
            <div class="card">
                <div class="card__head"><h2>{{ __('Details') }}</h2></div>
                <div class="card__body">
                    <dl class="dl">
                        <dt>{{ __('Reference number') }}</dt><dd class="mono">{{ $document->reference_number }}</dd>
                        <dt>{{ __('Document type') }}</dt><dd>{{ $document->category?->displayName() ?? '—' }}</dd>
                        <dt>{{ __('Document date') }}</dt><dd>{{ fmt_date($document->document_date) }}</dd>
                        <dt>{{ __('Department') }}</dt><dd>{{ $document->department?->displayName() ?? '—' }}</dd>
                        <dt>{{ __('Project') }}</dt><dd>
                            @if ($document->project)
                                @if (Route::has('projects.show') && $me->can('projects.view'))<a href="{{ route('projects.show', $document->project) }}">{{ $document->project->code }} — {{ $document->project->title }}</a>@else{{ $document->project->code }} — {{ $document->project->title }}@endif
                            @else — @endif
                        </dd>
                        <dt>{{ __('Owner') }}</dt><dd>{{ $document->owner?->name ?? '—' }}</dd>
                        <dt>{{ __('Registered by') }}</dt><dd>{{ $document->creator?->name ?? '—' }} <span class="muted small">· {{ fmt_date($document->created_at, true) }}</span></dd>
                        <dt>{{ __('Confidentiality') }}</dt><dd><x-status domain="confidentiality" :value="$document->confidentiality" /></dd>
                        <dt>{{ __('Next review date') }}</dt><dd>{{ fmt_date($document->review_date) }}</dd>
                        <dt>{{ __('Expiry / renewal date') }}</dt><dd>{{ fmt_date($document->expiry_date) }}</dd>
                        <dt>{{ __('Published') }}</dt><dd>{{ fmt_date($document->published_at, true) }}</dd>
                        @if ($document->description)
                            <dt>{{ __('Description') }}</dt><dd class="prose">{{ $document->description }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            <div class="card" id="versions">
                <div class="card__head">
                    <h2>{{ __('Versions and revision history') }}</h2>
                    <span class="muted small">{{ __('Current version: :number', ['number' => $document->current_version]) }}</span>
                </div>
                @if ($document->versions->isEmpty())
                    <x-empty icon="file" :message="__('No file has been uploaded yet.')" />
                @else
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>{{ __('Version') }}</th><th>{{ __('File') }}</th><th>{{ __('Change summary') }}</th><th>{{ __('Uploaded') }}</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($document->versions as $version)
                            <tr>
                                <td class="nowrap"><strong>v{{ $version->version }}</strong> @if ($loop->first)<span class="badge badge--success">{{ __('Current') }}</span>@endif</td>
                                <td class="title-cell">{{ $version->original_name }}<span class="sub">{{ $version->humanSize() }} · <span class="mono" title="SHA-256">{{ \Illuminate\Support\Str::limit((string) $version->checksum, 12, '…') }}</span></span></td>
                                <td class="prose">{{ $version->change_summary ?? '—' }}</td>
                                <td class="nowrap">{{ $version->uploader?->name ?? __('System') }}<span class="sub">{{ fmt_date($version->created_at, true) }}</span></td>
                                <td class="actions"><a href="{{ route('documents.versions.download', [$document, $version]) }}" class="btn btn-sm"><x-icon name="download" /> {{ __('Download') }}</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
                @can('uploadVersion', $document)
                    <div class="card__foot">
                        <form method="POST" action="{{ route('documents.versions.store', $document) }}" enctype="multipart/form-data">
                            @csrf
                            <h3 class="small">{{ __('Upload new version') }}</h3>
                            <div class="form-grid">
                                <x-form.input type="file" name="file" :label="__('File')" required
                                    :help="__('Allowed: :types (max :size MB)', ['types' => strtoupper(str_replace(',', ', ', setting('upload_allowed_extensions'))), 'size' => round(setting('upload_max_kb') / 1024)])" />
                                <x-form.input name="change_summary" :label="__('Change summary')" required maxlength="1000" />
                            </div>
                            <div class="form-actions"><button class="btn btn-primary"><x-icon name="upload" /> {{ __('Upload version :number', ['number' => $document->current_version + 1]) }}</button></div>
                        </form>
                    </div>
                @endcan
            </div>

            <x-approval-panel :model="$document" :title="__('Approval')" />

            @if ($canViewLog)
                <div class="card" id="access-log">
                    <div class="card__head"><h2>{{ __('Access log') }}</h2><span class="muted small">{{ __('Latest :count entries', ['count' => 50]) }}</span></div>
                    @if ($accessLogs->isEmpty())
                        <x-empty icon="history" :message="__('No access recorded.')" />
                    @else
                        <div class="table-wrap"><table class="table">
                            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('User') }}</th><th>{{ __('Action') }}</th><th>{{ __('Version') }}</th><th>{{ __('IP address') }}</th></tr></thead>
                            <tbody>
                            @foreach ($accessLogs as $log)
                                <tr>
                                    <td class="nowrap">{{ fmt_date($log->created_at, true) }}</td>
                                    <td>{{ $log->user?->name ?? '—' }}</td>
                                    <td>{{ $log->action === 'download' ? __('Downloaded') : __('Viewed') }}</td>
                                    <td>{{ $log->version ? 'v'.$log->version->version : '—' }}</td>
                                    <td class="mono small">{{ $log->ip_address ?? '—' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table></div>
                    @endif
                </div>
            @endif

            <x-audit-trail :model="$document" />
        </div>

        <div class="stack">
            <div class="card" id="workflow">
                <div class="card__head"><h2>{{ __('Workflow') }}</h2><x-status domain="document" :value="$document->status" /></div>
                <div class="card__body stack">
                    @can('submit', $document)
                        <form method="POST" action="{{ route('documents.submit', $document) }}">
                            @csrf
                            <x-form.textarea name="note" :label="__('Note to approvers')" rows="2" maxlength="2000" />
                            <button class="btn btn-accent">{{ __('Submit for approval') }}</button>
                        </form>
                    @endcan
                    @if ($document->status === 'pending_review')
                        <p class="small muted">{{ __('Waiting for an authorised approver to review this document.') }}</p>
                    @endif
                    @can('publish', $document)
                        <form method="POST" action="{{ route('documents.publish', $document) }}" data-confirm="{{ __('Publish this document?') }}">
                            @csrf
                            <button class="btn btn-success"><x-icon name="globe" /> {{ __('Publish') }}</button>
                        </form>
                    @endcan
                    @can('archive', $document)
                        <form method="POST" action="{{ route('documents.archive', $document) }}" data-confirm="{{ __('Archive this document? It will be hidden from the active register.') }}">
                            @csrf
                            <button class="btn"><x-icon name="archive" /> {{ __('Archive') }}</button>
                        </form>
                    @endcan
                    @can('unarchive', $document)
                        <form method="POST" action="{{ route('documents.unarchive', $document) }}">
                            @csrf
                            <button class="btn"><x-icon name="archive" /> {{ __('Restore from archive') }}</button>
                        </form>
                    @endcan
                    <dl class="dl small">
                        <dt>{{ __('Published') }}</dt><dd>{{ fmt_date($document->published_at, true) }}</dd>
                        <dt>{{ __('Archived') }}</dt><dd>{{ fmt_date($document->archived_at, true) }}</dd>
                    </dl>
                </div>
            </div>

            @if ($canManageAccess)
                <div class="card" id="access">
                    <div class="card__head"><h2><x-icon name="lock" /> {{ __('Individual access') }}</h2></div>
                    <div class="card__body"><p class="small muted mb-0">{{ __('Restricted documents are visible only to the owner, the creator, users allowed to access confidential documents and the people listed here.') }}</p></div>
                    @if ($document->grantedUsers->isEmpty())
                        <x-empty icon="users" :message="__('No individual access granted.')" />
                    @else
                        <ul class="list">
                            @foreach ($document->grantedUsers as $grantee)
                                <li><div class="list__row">
                                    <div>{{ $grantee->name }}
                                        <div class="list__meta">{{ __('Granted by :name on :date', ['name' => $grantors[$grantee->pivot->granted_by] ?? '—', 'date' => fmt_date($grantee->pivot->created_at)]) }}</div></div>
                                    <x-delete-button :action="route('documents.access.destroy', [$document, $grantee])" :label="__('Revoke')" class="btn-link danger"
                                        :confirm="__('Revoke this user\'s access to the document?')" />
                                </div></li>
                            @endforeach
                        </ul>
                    @endif
                    <div class="card__foot">
                        <form method="POST" action="{{ route('documents.access.store', $document) }}">
                            @csrf
                            <x-form.select name="user_id" :label="__('Grant access to')" :options="$grantableUsers" required />
                            <button class="btn btn-primary">{{ __('Grant access') }}</button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
