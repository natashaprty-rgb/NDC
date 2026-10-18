<x-layout :title="__('documents.title')" :subtitle="__('documents.subtitle')" :breadcrumbs="[__('documents.title')]">
    <x-slot:actions>
        @can('create', \App\Models\Document::class)
            <a href="{{ route('documents.create') }}" class="btn btn--primary"><x-icon name="plus" width="16" height="16" /> {{ __('documents.new') }}</a>
        @endcan
    </x-slot:actions>

    <section class="card">
        <x-filter-bar :action="route('documents.index')">
            <x-field.input name="q" :label="__('common.search')" :value="request('q')" class="field--search" :placeholder="__('documents.search_hint')" />
            <x-field.select name="category" :label="__('common.category')" :options="$categories" :value="request('category')" :placeholder="__('common.all')" />
            <x-field.select name="department" :label="__('common.department')" :options="$departments" :value="request('department')" :placeholder="__('common.all')" />
            <x-field.select name="confidentiality" :label="__('documents.confidentiality')" :options="enum_options(\App\Models\Document::CONFIDENTIALITY)" :value="request('confidentiality')" :placeholder="__('common.all')" />
            <x-field.select name="status" :label="__('common.status')" :options="enum_options(\App\Models\Document::STATUSES)" :value="request('status')" :placeholder="__('documents.all_active')" />
            <x-field.select name="expiring" :label="__('documents.expiry')" :options="['1' => __('documents.expiring_30')]" :value="request('expiring')" :placeholder="__('common.all')" />
            <x-field.select name="review_due" :label="__('documents.review')" :options="['1' => __('documents.review_due')]" :value="request('review_due')" :placeholder="__('common.all')" />
            @if(request('project'))<input type="hidden" name="project" value="{{ request('project') }}">@endif
        </x-filter-bar>

        @if($documents->isEmpty())
            <x-empty :message="request()->hasAny(['q', 'category', 'department', 'confidentiality', 'status', 'expiring', 'review_due', 'project']) ? __('common.no_records') : __('documents.empty')" icon="file" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr>
                        <th>{{ __('common.reference') }}</th><th>{{ __('documents.doc_title') }}</th><th>{{ __('common.category') }}</th>
                        <th>{{ __('common.department') }}</th><th>{{ __('documents.confidentiality') }}</th><th>{{ __('common.status') }}</th>
                        <th>{{ __('documents.registration_date') }}</th><th class="num">{{ __('documents.version') }}</th>
                    </tr></thead>
                    <tbody>
                    @foreach($documents as $document)
                        <tr @class(['row-overdue' => $document->isExpired() && $document->status !== 'archived'])>
                            <td class="nowrap">{{ $document->reference }}</td>
                            <td><a href="{{ route('documents.show', $document) }}">{{ $document->title }}</a><br><span class="small muted">{{ $document->owner?->name }}</span></td>
                            <td>{{ $document->category?->displayName() ?? '—' }}</td>
                            <td>{{ $document->department?->displayName() ?? '—' }}</td>
                            <td><x-badge :value="$document->confidentiality" /></td>
                            <td><x-badge :value="$document->status" /></td>
                            <td class="nowrap">{{ fmt_date($document->registration_date) }}</td>
                            <td class="num">v{{ $document->current_version }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $documents->links() }}
        @endif
    </section>
</x-layout>
