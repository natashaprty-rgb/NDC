<x-layout :title="__('audit.reviews_title')" :subtitle="__('audit.reviews_subtitle')" :breadcrumbs="[__('audit.reviews_title')]">
    <x-slot:actions>
        <a href="{{ route('admin.permission-reviews.sheet') }}" class="btn"><x-icon name="clipboard" width="16" height="16" /> {{ __('audit.review_sheet') }}</a>
        <a href="{{ route('admin.permission-reviews.create') }}" class="btn btn--primary"><x-icon name="plus" width="16" height="16" /> {{ __('audit.record_review') }}</a>
    </x-slot:actions>

    @if($overdue)
        <div class="alert alert--warning" role="status">
            {{ $last ? __('audit.review_overdue', ['date' => fmt_date($last->review_date), 'days' => $interval]) : __('audit.review_never') }}
        </div>
    @endif

    <x-card :flush="true">
        @if($reviews->isEmpty())
            <x-empty :message="__('audit.no_reviews')" icon="clipboard" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>{{ __('audit.review_date') }}</th><th>{{ __('audit.reviewer') }}</th><th class="num">{{ __('audit.accounts_reviewed') }}</th><th>{{ __('audit.findings') }}</th><th>{{ __('audit.actions_taken') }}</th><th>{{ __('audit.next_review_due') }}</th></tr></thead>
                <tbody>@foreach($reviews as $review)
                    <tr>
                        <td class="nowrap">{{ fmt_date($review->review_date) }}</td>
                        <td>{{ $review->reviewer?->name }}</td>
                        <td class="num">{{ $review->accounts_reviewed }}</td>
                        <td class="small prose">{{ $review->findings }}</td>
                        <td class="small prose">{{ $review->actions_taken ?? '—' }}</td>
                        <td class="nowrap">{{ fmt_date($review->next_review_due) }}</td>
                    </tr>
                @endforeach</tbody>
            </table></div>
            {{ $reviews->links() }}
        @endif
    </x-card>
</x-layout>
