<x-layouts.app :title="__('Documents')">
    <x-page-header :title="__('Documents')" :subtitle="__('Document register: official records, versions, approvals and access.')">
        <x-slot:actions>
            <a href="{{ route('documents.register', request()->except('page')) }}" class="btn"><x-icon name="printer" /> {{ __('Printable register') }}</a>
            @can('create', \App\Models\Document::class)
                <a href="{{ route('documents.create') }}" class="btn btn-primary"><x-icon name="upload" /> {{ __('Upload document') }}</a>
            @endcan
        </x-slot:actions>
    </x-page-header>
    <div class="card">
        @include('documents._filters')
        @if ($documents->isEmpty())
            <x-empty icon="file" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr>
                    <th><x-sort-link column="reference_number" :label="__('Reference')" /></th>
                    <th><x-sort-link column="title" :label="__('Title')" /></th>
                    <th>{{ __('Type') }}</th>
                    <th>{{ __('Department') }}</th>
                    <th><x-sort-link column="confidentiality" :label="__('Confidentiality')" /></th>
                    <th><x-sort-link column="status" :label="__('Status')" /></th>
                    <th class="num">{{ __('Version') }}</th>
                    <th><x-sort-link column="expiry_date" :label="__('Expiry')" /></th>
                    <th><x-sort-link column="updated_at" :label="__('Updated')" /></th>
                </tr></thead>
                <tbody>
                @foreach ($documents as $document)
                    <tr>
                        <td class="mono nowrap">{{ $document->reference_number }}</td>
                        <td class="title-cell"><a href="{{ route('documents.show', $document) }}">{{ $document->title }}</a>
                            <span class="sub">{{ $document->owner?->name ?? '—' }} · {{ fmt_date($document->document_date) }}</span></td>
                        <td>{{ $document->category?->displayName() ?? '—' }}</td>
                        <td>{{ $document->department?->displayName() ?? '—' }}</td>
                        <td><x-status domain="confidentiality" :value="$document->confidentiality" /></td>
                        <td><x-status domain="document" :value="$document->status" /></td>
                        <td class="num">v{{ $document->current_version }}</td>
                        <td class="nowrap">{{ fmt_date($document->expiry_date) }} @include('documents._date_flags')</td>
                        <td class="nowrap">{{ fmt_date($document->updated_at) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
        {{ $documents->links() }}
    </div>
</x-layouts.app>
