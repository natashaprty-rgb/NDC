<x-layouts.app :title="__('Document register')">
    <x-page-header :title="__('Document register')" :subtitle="__('Generated :date · :count documents', ['date' => fmt_date(now(), true), 'count' => $total])"
        :breadcrumbs="[__('Documents') => route('documents.index')]">
        <x-slot:actions>
            <button type="button" class="btn btn-primary" data-print><x-icon name="printer" /> {{ __('Print') }}</button>
        </x-slot:actions>
    </x-page-header>
    @if ($total > $limit)
        <div class="alert alert--warning">{{ __('Only the first :limit documents are shown. Narrow the filters to print the rest.', ['limit' => $limit]) }}</div>
    @endif
    <div class="card">
        <div class="no-print">@include('documents._filters', ['action' => route('documents.register')])</div>
        @if ($documents->isEmpty())
            <x-empty icon="file" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr>
                    <th>{{ __('Reference') }}</th><th>{{ __('Title') }}</th><th>{{ __('Type') }}</th><th>{{ __('Department') }}</th>
                    <th>{{ __('Project') }}</th><th>{{ __('Owner') }}</th><th>{{ __('Document date') }}</th><th>{{ __('Confidentiality') }}</th>
                    <th>{{ __('Status') }}</th><th class="num">{{ __('Version') }}</th><th>{{ __('Review date') }}</th><th>{{ __('Expiry') }}</th>
                </tr></thead>
                <tbody>
                @foreach ($documents as $document)
                    <tr>
                        <td class="mono nowrap">{{ $document->reference_number }}</td>
                        <td>{{ $document->title }}</td>
                        <td>{{ $document->category?->displayName() ?? '—' }}</td>
                        <td>{{ $document->department?->displayName() ?? '—' }}</td>
                        <td>{{ $document->project?->code ?? '—' }}</td>
                        <td>{{ $document->owner?->name ?? '—' }}</td>
                        <td class="nowrap">{{ fmt_date($document->document_date) }}</td>
                        <td>{{ \App\Support\Status::label('confidentiality', $document->confidentiality) }}</td>
                        <td>{{ \App\Support\Status::label('document', $document->status) }}</td>
                        <td class="num">v{{ $document->current_version }}</td>
                        <td class="nowrap">{{ fmt_date($document->review_date) }}</td>
                        <td class="nowrap">{{ fmt_date($document->expiry_date) }} @if ($document->isExpired())({{ __('Expired') }})@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </div>
</x-layouts.app>
