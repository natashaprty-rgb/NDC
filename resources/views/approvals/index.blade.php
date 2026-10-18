<x-layout :title="__('approvals.title')" :subtitle="__('approvals.subtitle')" :breadcrumbs="[__('approvals.title')]">
    @php $total = collect($groups)->sum(fn ($c) => $c->count()); @endphp
    @if($total === 0)
        <x-card><x-empty :message="__('approvals.none')" icon="check-circle" /></x-card>
    @endif

    @if($groups['tasks']->isNotEmpty())
        <x-card :title="__('approvals.tasks').' ('.$groups['tasks']->count().')'" :flush="true">
            <div class="table-wrap"><table class="table">
                <thead><tr><th>{{ __('common.reference') }}</th><th>{{ __('common.title') }}</th><th>{{ __('common.assigned_to') }}</th><th>{{ __('approvals.submitted') }}</th></tr></thead>
                <tbody>@foreach($groups['tasks'] as $item)
                    <tr><td class="nowrap">{{ $item->reference }}</td><td><a href="{{ route_if('tasks.show', $item) }}">{{ $item->title }}</a><br><span class="small muted">{{ $item->project?->name }}</span></td><td>{{ $item->assignee?->name }}</td><td class="small">{{ fmt_datetime($item->submitted_at) }}</td></tr>
                @endforeach</tbody>
            </table></div>
        </x-card>
    @endif

    @if($groups['documents']->isNotEmpty())
        <x-card :title="__('approvals.documents').' ('.$groups['documents']->count().')'" :flush="true">
            <div class="table-wrap"><table class="table">
                <thead><tr><th>{{ __('common.reference') }}</th><th>{{ __('common.title') }}</th><th>{{ __('common.owner') }}</th><th>{{ __('common.category') }}</th></tr></thead>
                <tbody>@foreach($groups['documents'] as $item)
                    <tr><td class="nowrap">{{ $item->reference }}</td><td><a href="{{ route_if('documents.show', $item) }}">{{ $item->title }}</a></td><td>{{ $item->owner?->name }}</td><td>{{ $item->category?->displayName() }}</td></tr>
                @endforeach</tbody>
            </table></div>
        </x-card>
    @endif

    @if($groups['purchase_requests']->isNotEmpty())
        <x-card :title="__('approvals.purchase_requests').' ('.$groups['purchase_requests']->count().')'" :flush="true">
            <div class="table-wrap"><table class="table">
                <thead><tr><th>{{ __('common.reference') }}</th><th>{{ __('common.title') }}</th><th>{{ __('approvals.requested_by') }}</th><th class="num">{{ __('approvals.estimated') }}</th></tr></thead>
                <tbody>@foreach($groups['purchase_requests'] as $item)
                    <tr><td class="nowrap">{{ $item->reference }}</td><td><a href="{{ route_if('purchase-requests.show', $item) }}">{{ $item->title }}</a><br><span class="small muted">{{ $item->department?->displayName() }}</span></td><td>{{ $item->requester?->name }}</td><td class="num">{{ money($item->estimated_total, $item->currency) }}</td></tr>
                @endforeach</tbody>
            </table></div>
        </x-card>
    @endif

    @if($groups['budgets']->isNotEmpty())
        <x-card :title="__('approvals.budgets').' ('.$groups['budgets']->count().')'" :flush="true">
            <div class="table-wrap"><table class="table">
                <thead><tr><th>{{ __('common.reference') }}</th><th>{{ __('common.title') }}</th><th>{{ __('approvals.fiscal_year') }}</th></tr></thead>
                <tbody>@foreach($groups['budgets'] as $item)
                    <tr><td class="nowrap">{{ $item->reference }}</td><td><a href="{{ route_if('budgets.show', $item) }}">{{ $item->title }}</a></td><td>{{ $item->fiscal_year }}</td></tr>
                @endforeach</tbody>
            </table></div>
        </x-card>
    @endif

    @if($groups['expenses']->isNotEmpty())
        <x-card :title="__('approvals.expenses').' ('.$groups['expenses']->count().')'" :flush="true">
            <div class="table-wrap"><table class="table">
                <thead><tr><th>{{ __('common.reference') }}</th><th>{{ __('common.description') }}</th><th>{{ __('approvals.recorded_by') }}</th><th class="num">{{ __('approvals.amount') }}</th></tr></thead>
                <tbody>@foreach($groups['expenses'] as $item)
                    <tr><td class="nowrap">{{ $item->reference }}</td><td><a href="{{ route_if('expenses.show', $item) }}">{{ $item->description }}</a><br><span class="small muted">{{ $item->budget?->title }}</span></td><td>{{ $item->recorder?->name }}</td><td class="num">{{ money($item->amount, $item->currency) }}</td></tr>
                @endforeach</tbody>
            </table></div>
        </x-card>
    @endif
</x-layout>
