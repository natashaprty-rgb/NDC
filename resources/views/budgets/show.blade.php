@php
    $currency = $budget->currency;
    $tone = \App\Models\Budget::utilisationTone($summary['utilisation']);
@endphp
<x-layouts.app :title="$budget->code">
    <x-page-header :title="$budget->name" :breadcrumbs="[__('Budgets') => route('budgets.index')]">
        <x-slot:meta>
            <p>{{ $budget->code }} · {{ __('Fiscal year :year', ['year' => $budget->fiscal_year]) }} · <x-status domain="budget" :value="$budget->status" /></p>
        </x-slot:meta>
        <x-slot:actions>
            @if ($budget->permits('recordExpense'))
                <a href="{{ route('expenses.create', ['budget' => $budget->id]) }}" class="btn btn-primary"><x-icon name="plus" /> {{ __('Record expense') }}</a>
            @endif
            @if ($budget->permits('update'))
                <a href="{{ route('budgets.edit', $budget) }}" class="btn"><x-icon name="edit" /> {{ __('Edit') }}</a>
            @endif
            <a href="{{ route('budgets.report', ['q' => $budget->code]) }}" class="btn"><x-icon name="printer" /> {{ __('Printable summary') }}</a>
            @if ($budget->permits('close'))
                <x-delete-button :action="route('budgets.close', $budget)" method="POST" :label="__('Close budget')" class="btn"
                    :confirm="__('Close this budget? No further expenses can be recorded against it.')" />
            @endif
            @if ($budget->permits('delete'))
                <x-delete-button :action="route('budgets.destroy', $budget)" :confirm="__('Delete this budget? It will be archived and removed from the register.')" class="btn btn-danger" />
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($summary['over_spent'])
        <div class="alert alert--error" role="alert">{{ __('Approved expenses (:approved) exceed the budget amount of :amount.', ['approved' => money($summary['approved'], $currency), 'amount' => money($summary['amount'], $currency)]) }}</div>
    @elseif ($summary['over_committed'])
        <div class="alert alert--warning" role="status">{{ __('Approved and pending expenses (:committed) exceed the budget amount of :amount. Review pending expenses before approving them.', ['committed' => money($summary['committed'], $currency), 'amount' => money($summary['amount'], $currency)]) }}</div>
    @endif
    @if ($budget->status === 'closed')
        <div class="alert alert--info" role="status">{{ __('This budget is closed. No further expenses can be recorded against it.') }}</div>
    @endif

    <div class="grid grid-4" style="margin-bottom:1rem">
        <div class="card stat">
            <span class="stat__label">{{ __('Budget amount') }}</span>
            <span class="stat__value" style="font-size:1.3rem">{{ money($summary['amount'], $currency) }}</span>
            <span class="stat__meta">{{ __('Planned: :amount', ['amount' => money($summary['planned'], $currency)]) }}</span>
        </div>
        <div class="card stat">
            <span class="stat__label">{{ __('Approved spend') }}</span>
            <span class="stat__value" style="font-size:1.3rem">{{ money($summary['approved'], $currency) }}</span>
            <span class="stat__meta">{{ __(':percent% of the budget used', ['percent' => $summary['utilisation']]) }}</span>
        </div>
        <div class="card stat">
            <span class="stat__label">{{ __('Pending approval') }}</span>
            <span class="stat__value" style="font-size:1.3rem">{{ money($summary['pending'], $currency) }}</span>
            <span class="stat__meta">{{ __('Not yet counted in utilisation') }}</span>
        </div>
        <div @class(['card', 'stat', 'stat--alert' => $summary['remaining'] < 0])>
            <span class="stat__label">{{ __('Remaining balance') }}</span>
            <span class="stat__value" style="font-size:1.3rem">{{ money($summary['remaining'], $currency) }}</span>
            <span class="stat__meta">{{ __('Amount minus approved spend') }}</span>
        </div>
    </div>

    <div class="grid grid-main">
        <div class="stack">
            <div class="card">
                <div class="card__head"><h2>{{ __('Utilisation') }}</h2></div>
                <div class="card__body">
                    <x-progress :value="$summary['utilisation']" :tone="$tone" />
                    @if ($summary['utilisation'] > 100)
                        <p class="small" style="color:var(--danger)">{{ __('Utilisation is :percent%, above the approved amount.', ['percent' => $summary['utilisation']]) }}</p>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card__head"><h2>{{ __('Planned vs actual by category') }}</h2></div>
                @include('budgets.partials.breakdown', ['breakdown' => $breakdown, 'summary' => $summary, 'currency' => $currency])
            </div>

            <div class="card" id="expenses">
                <div class="card__head">
                    <h2>{{ __('Expenses') }}</h2>
                    <a href="{{ route('expenses.index', ['budget' => $budget->id]) }}" class="small">{{ __('Open in expense register') }}</a>
                </div>
                @if ($expenses->isEmpty())
                    <x-empty icon="wallet" :message="__('No expenses recorded against this budget yet.')" />
                @else
                    <div class="table-wrap"><table class="table">
                        <thead><tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Description') }}</th>
                            <th>{{ __('Category') }}</th>
                            <th>{{ __('Payee') }}</th>
                            <th class="num">{{ __('Amount') }}</th>
                            <th>{{ __('Status') }}</th>
                        </tr></thead>
                        <tbody>
                        @foreach ($expenses as $expense)
                            <tr id="expense-{{ $expense->id }}">
                                <td class="nowrap">{{ fmt_date($expense->expense_date) }}</td>
                                <td class="title-cell"><a href="{{ route('expenses.show', $expense) }}">{{ $expense->description }}</a>
                                    @if ($expense->reference)<span class="sub">{{ $expense->reference }}</span>@endif</td>
                                <td>{{ $expense->category?->displayName() ?? '—' }}</td>
                                <td>{{ $expense->payee ?? '—' }}</td>
                                <td class="num">{{ money($expense->amount, $currency) }}</td>
                                <td><x-status domain="expense" :value="$expense->status" /></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
                {{ $expenses->fragment('expenses')->links() }}
            </div>
        </div>

        <div class="stack">
            <div class="card">
                <div class="card__head"><h2>{{ __('Details') }}</h2></div>
                <div class="card__body">
                    <dl class="dl">
                        <dt>{{ __('Budget code') }}</dt><dd>{{ $budget->code }}</dd>
                        <dt>{{ __('Fiscal year') }}</dt><dd>{{ $budget->fiscal_year }}</dd>
                        <dt>{{ __('Project') }}</dt><dd>
                            @if ($budget->project)
                                @if (Route::has('projects.show') && auth()->user()->can('projects.view'))
                                    <a href="{{ route('projects.show', $budget->project) }}">{{ $budget->project->code }} — {{ $budget->project->title }}</a>
                                @else
                                    {{ $budget->project->code }} — {{ $budget->project->title }}
                                @endif
                            @else — @endif
                        </dd>
                        <dt>{{ __('Department') }}</dt><dd>{{ $budget->department?->displayName() ?? '—' }}</dd>
                        <dt>{{ __('Funding source') }}</dt><dd>{{ $budget->fundingSource?->displayName() ?? '—' }}</dd>
                        <dt>{{ __('Period') }}</dt><dd>{{ fmt_date($budget->start_date) }} – {{ fmt_date($budget->end_date) }}</dd>
                        <dt>{{ __('Currency') }}</dt><dd>{{ $currency }}</dd>
                        <dt>{{ __('Created by') }}</dt><dd>{{ $budget->creator?->name ?? '—' }} · {{ fmt_date($budget->created_at) }}</dd>
                        @if ($budget->notes)
                            <dt>{{ __('Notes') }}</dt><dd class="prose">{{ $budget->notes }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            @if ($budget->permits('submit'))
                <div class="card">
                    <div class="card__head"><h2>{{ __('Submit for approval') }}</h2></div>
                    <form method="POST" action="{{ route('budgets.submit', $budget) }}" class="card__body">
                        @csrf
                        <x-form.textarea name="note" :label="__('Note to approver')" rows="2" maxlength="2000" />
                        <button type="submit" class="btn btn-accent">{{ __('Submit for approval') }}</button>
                        <p class="small muted">{{ __('Another user with budget approval rights must approve it. You cannot approve your own request.') }}</p>
                    </form>
                </div>
            @endif

            <x-approval-panel :model="$budget" :title="__('Budget approval')" />

            <div class="card">
                <div class="card__head"><h2>{{ __('Planned allocations') }}</h2>
                    <span class="small muted">{{ money($summary['planned'], $currency) }}</span></div>
                @if ($budget->allocations->isEmpty())
                    <x-empty icon="chart" :message="__('No allocations planned yet.')" />
                @else
                    <ul class="list">
                        @foreach ($budget->allocations as $allocation)
                            <li><div class="list__row">
                                <div>{{ $allocation->category?->displayName() ?? __('Uncategorised') }}
                                    @if ($allocation->description)<div class="list__meta">{{ $allocation->description }}</div>@endif</div>
                                <span class="num">{{ money($allocation->planned_amount, $currency) }}</span>
                            </div></li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <x-attachments :model="$budget" :can-upload="$budget->permits('update')" />
            <x-audit-trail :model="$budget" />
        </div>
    </div>
</x-layouts.app>
