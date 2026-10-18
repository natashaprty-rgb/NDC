@php $cur = $budget->currency; @endphp
<x-layout :title="$budget->title" :subtitle="$budget->reference.' · '.__('budgets.fiscal_year').' '.$budget->fiscal_year"
          :breadcrumbs="[__('budgets.title') => route('budgets.index'), $budget->reference]">
    <x-slot:actions>
        @can('update', $budget)
            <a href="{{ route('budgets.edit', $budget) }}" class="btn"><x-icon name="edit" width="16" height="16" /> {{ __('common.edit') }}</a>
        @endcan
        @can('submit', $budget)
            <form method="POST" action="{{ route('budgets.submit', $budget) }}" class="inline-form" data-confirm="{{ __('budgets.confirm_submit') }}">@csrf<button class="btn btn--primary" type="submit">{{ __('budgets.submit') }}</button></form>
        @endcan
        @can('approve', $budget)
            <form method="POST" action="{{ route('budgets.approve', $budget) }}" class="inline-form" data-confirm="{{ __('budgets.confirm_approve') }}">@csrf<button class="btn btn--success" type="submit">{{ __('budgets.approve') }}</button></form>
        @endcan
        @can('close', $budget)
            <form method="POST" action="{{ route('budgets.close', $budget) }}" class="inline-form" data-confirm="{{ __('budgets.confirm_close') }}">@csrf<button class="btn" type="submit">{{ __('budgets.close') }}</button></form>
        @endcan
        @if($budget->status === 'approved')
            @can('create', \App\Models\Expense::class)
                <a href="{{ route('expenses.create', ['budget_id' => $budget->id]) }}" class="btn btn--primary"><x-icon name="plus" width="16" height="16" /> {{ __('budgets.record_expense') }}</a>
            @endcan
        @endif
        @can('delete', $budget)
            <x-delete-button :action="route('budgets.destroy', $budget)" :message="__('budgets.confirm_delete')" class="btn btn--danger" />
        @endcan
    </x-slot:actions>

    <div class="alert alert--info">{{ __('budgets.monitoring_note') }}</div>

    @can('approve', $budget)
        <x-card :title="__('budgets.reject')">
            <form method="POST" action="{{ route('budgets.reject', $budget) }}" class="form-grid">
                @csrf
                <x-field.textarea name="review_note" :label="__('common.reason')" :required="true" :help="__('budgets.reject_help')" rows="2" class="span-2" />
                <div class="span-2 text-right"><button class="btn btn--danger btn--sm" type="submit">{{ __('budgets.reject') }}</button></div>
            </form>
        </x-card>
    @endcan

    <x-card :title="__('budgets.summary')">
        <div class="grid grid-4">
            <div><div class="stat__label">{{ __('budgets.allocated') }}</div><strong>{{ money($summary['allocated'], $cur) }}</strong><div class="small muted">{{ __('budgets.figure_allocated') }}</div></div>
            <div><div class="stat__label">{{ __('budgets.recorded') }}</div><strong>{{ money($summary['recorded'], $cur) }}</strong><div class="small muted">{{ __('budgets.figure_recorded') }}</div></div>
            <div><div class="stat__label">{{ __('budgets.approved_spend') }}</div><strong>{{ money($summary['approved'], $cur) }}</strong><div class="small muted">{{ __('budgets.figure_approved') }}</div></div>
            <div><div class="stat__label">{{ __('budgets.remaining') }}</div><strong>{{ money($summary['remaining'], $cur) }}</strong><div class="small muted">{{ __('budgets.figure_remaining') }}</div></div>
            <div><div class="stat__label">{{ __('budgets.paid') }}</div><strong>{{ money($summary['paid'], $cur) }}</strong><div class="small muted">{{ __('budgets.figure_paid') }}</div></div>
            <div><div class="stat__label">{{ __('budgets.reconciled') }}</div><strong>{{ money($summary['reconciled'], $cur) }}</strong><div class="small muted">{{ __('budgets.figure_reconciled') }}</div></div>
            <div><div class="stat__label">{{ __('budgets.remaining_after_recorded') }}</div><strong>{{ money($summary['remaining_after_recorded'], $cur) }}</strong><div class="small muted">{{ __('budgets.figure_remaining_recorded') }}</div></div>
        </div>
        <div class="mt-2"><x-progress :value="\App\Support\Money::percent($summary['approved'], $summary['allocated'])" /></div>
    </x-card>

    <div class="layout-main-side">
        <div>
            <x-card :title="__('budgets.allocations')" :flush="true">
                @if($categories->isEmpty())
                    <x-empty :message="__('budgets.no_allocations')" icon="wallet" />
                @else
                    @php $canEdit = auth()->user()->can('update', $budget); @endphp
                    <div class="table-wrap"><table class="table">
                        <thead><tr>
                            <th>{{ __('budgets.category') }}</th><th class="num">{{ __('budgets.allocated') }}</th><th class="num">{{ __('budgets.recorded') }}</th>
                            <th class="num">{{ __('budgets.approved_spend') }}</th><th class="num">{{ __('budgets.remaining') }}</th>@if($canEdit)<th></th>@endif
                        </tr></thead>
                        <tbody>
                        @foreach($categories as $row)
                            @php $a = $row['allocation']; $s = $row['summary']; @endphp
                            <tr>
                                <td>{{ $a->category?->displayName() }}@if($a->description)<br><span class="small muted">{{ $a->description }}</span>@endif</td>
                                <td class="num">{{ money($s['allocated'], $cur) }}</td>
                                <td class="num">{{ money($s['recorded'], $cur) }}</td>
                                <td class="num">{{ money($s['approved'], $cur) }}</td>
                                <td class="num">{{ money($s['remaining'], $cur) }}</td>
                                @if($canEdit)
                                    <td class="nowrap text-right">
                                        <details>
                                            <summary class="btn btn--sm">{{ __('common.edit') }}</summary>
                                            <form method="POST" action="{{ route('budgets.allocations.update', [$budget, $a]) }}" class="stack mt-1" style="min-width:220px">
                                                @csrf @method('PUT')
                                                <div class="field"><label for="alloc_{{ $a->id }}_amount">{{ __('budgets.amount') }}</label>
                                                    <input id="alloc_{{ $a->id }}_amount" class="input" name="amount" value="{{ $a->amount }}" required inputmode="decimal"></div>
                                                <div class="field"><label for="alloc_{{ $a->id }}_desc">{{ __('common.description') }}</label>
                                                    <input id="alloc_{{ $a->id }}_desc" class="input" name="description" value="{{ $a->description }}" maxlength="300"></div>
                                                <button class="btn btn--primary btn--sm" type="submit">{{ __('common.save') }}</button>
                                            </form>
                                        </details>
                                        <x-delete-button :action="route('budgets.allocations.destroy', [$budget, $a])" :label="__('common.remove')" />
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot><tr>
                            <td>{{ __('common.total') }}</td><td class="num">{{ money($summary['allocated'], $cur) }}</td><td class="num">{{ money($summary['recorded'], $cur) }}</td>
                            <td class="num">{{ money($summary['approved'], $cur) }}</td><td class="num">{{ money($summary['remaining'], $cur) }}</td>@if($canEdit)<td></td>@endif
                        </tr></tfoot>
                    </table></div>
                @endif
                @can('update', $budget)
                    <x-slot:footer>
                        @if(empty($availableCategories))
                            <p class="small muted mb-0">{{ __('budgets.all_categories_used') }}</p>
                        @else
                            <form method="POST" action="{{ route('budgets.allocations.store', $budget) }}" class="form-grid">
                                @csrf
                                <x-field.select name="expense_category_id" :label="__('budgets.category')" :options="$availableCategories" :required="true" />
                                <x-field.input name="amount" :label="__('budgets.amount').' ('.$cur.')'" :required="true" inputmode="decimal" />
                                <x-field.input name="description" :label="__('common.description')" class="span-2" />
                                <div class="span-2 text-right"><button type="submit" class="btn btn--primary btn--sm">{{ __('budgets.add_allocation') }}</button></div>
                            </form>
                        @endif
                    </x-slot:footer>
                @else
                    @if(in_array($budget->status, ['approved', 'closed'], true))
                        <x-slot:footer><p class="small muted mb-0">{{ __('budgets.allocations_locked') }}</p></x-slot:footer>
                    @endif
                @endcan
            </x-card>

            <x-card :title="__('budgets.expenses')" :flush="true">
                @if($expenses->isEmpty())
                    <x-empty :message="__('budgets.no_expenses')" icon="receipt" />
                @else
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>{{ __('common.reference') }}</th><th>{{ __('common.date') }}</th><th>{{ __('common.description') }}</th><th>{{ __('budgets.category') }}</th><th class="num">{{ __('budgets.amount') }}</th><th>{{ __('common.status') }}</th></tr></thead>
                        <tbody>@foreach($expenses as $expense)
                            <tr>
                                <td class="nowrap"><a href="{{ route('expenses.show', $expense) }}">{{ $expense->reference }}</a></td>
                                <td class="nowrap">{{ fmt_date($expense->expense_date) }}</td>
                                <td>{{ $expense->description }}</td>
                                <td>{{ $expense->category?->displayName() }}</td>
                                <td class="num">{{ money($expense->amount, $expense->currency) }}</td>
                                <td><x-badge :value="$expense->status" /></td>
                            </tr>
                        @endforeach</tbody>
                    </table></div>
                    {{ $expenses->links() }}
                @endif
            </x-card>
        </div>
        <div>
            <x-card :title="__('common.details')">
                <dl class="dl">
                    <dt>{{ __('common.status') }}</dt><dd><x-badge :value="$budget->status" /></dd>
                    <dt>{{ __('common.project') }}</dt><dd>@if($budget->project && route_if('projects.show', $budget->project))<a href="{{ route('projects.show', $budget->project) }}">{{ $budget->project->name }}</a>@else{{ $budget->project?->name ?? '—' }}@endif</dd>
                    <dt>{{ __('common.department') }}</dt><dd>{{ $budget->department?->displayName() ?? '—' }}</dd>
                    <dt>{{ __('budgets.funding_source') }}</dt><dd>{{ $budget->fundingSource?->name ?? '—' }}</dd>
                    <dt>{{ __('budgets.period') }}</dt><dd>{{ fmt_date($budget->period_start) }} – {{ fmt_date($budget->period_end) }}</dd>
                    <dt>{{ __('budgets.currency') }}</dt><dd>{{ $budget->currency }}</dd>
                    <dt>{{ __('budgets.prepared_by') }}</dt><dd>{{ $budget->creator?->name ?? '—' }}</dd>
                    <dt>{{ __('budgets.approved_by') }}</dt><dd>{{ $budget->approvedBy?->name ?? '—' }} @if($budget->approved_at)<span class="small muted">{{ fmt_datetime($budget->approved_at) }}</span>@endif</dd>
                </dl>
                @if($budget->notes)<p class="prose mt-2 mb-0">{{ $budget->notes }}</p>@endif
            </x-card>
            <x-attachments :model="$budget" :categories="['general', 'report', 'contract']" />
            <x-history :model="$budget" />
        </div>
    </div>
</x-layout>
