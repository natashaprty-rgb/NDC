<x-layouts.app :title="__('Budgets')">
    <x-page-header :title="__('Budgets')" :subtitle="__('Approved budgets by project, department or funding source, with spending against them.')">
        <x-slot:actions>
            <a href="{{ route('expenses.index') }}" class="btn"><x-icon name="wallet" /> {{ __('Expense register') }}</a>
            <a href="{{ route('budgets.report', request()->only(\App\Http\Controllers\Budgets\BudgetController::FILTERS)) }}" class="btn"><x-icon name="printer" /> {{ __('Budget summary') }}</a>
            @can('create', \App\Models\Budget::class)
                <a href="{{ route('budgets.create') }}" class="btn btn-primary"><x-icon name="plus" /> {{ __('New budget') }}</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <x-filters>
            <x-search-field :placeholder="__('Code or name')" />
            <x-filter-select name="fiscal_year" :label="__('Fiscal year')" :options="$fiscalYears" />
            <x-filter-select name="project" :label="__('Project')" :options="$projects" />
            <x-filter-select name="department" :label="__('Department')" :options="$departments" />
            <x-filter-select name="funding_source" :label="__('Funding source')" :options="$fundingSources" />
            <x-filter-select name="status" :label="__('Status')" :options="\App\Support\Status::options('budget')" />
        </x-filters>

        @if ($budgets->isEmpty())
            <x-empty icon="wallet" :message="__('No budgets match the selected filters.')" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr>
                    <th><x-sort-link column="code" :label="__('Budget')" /></th>
                    <th><x-sort-link column="fiscal_year" :label="__('Fiscal year')" /></th>
                    <th>{{ __('Budget holder') }}</th>
                    <th class="num"><x-sort-link column="amount" :label="__('Amount')" /></th>
                    <th class="num">{{ __('Approved spend') }}</th>
                    <th class="num">{{ __('Remaining') }}</th>
                    <th>{{ __('Utilisation') }}</th>
                    <th><x-sort-link column="status" :label="__('Status')" /></th>
                </tr></thead>
                <tbody>
                @foreach ($budgets as $budget)
                    @php
                        $amountCents = \App\Models\Budget::cents($budget->amount);
                        $approvedCents = \App\Models\Budget::cents($budget->approved_spend);
                        $pct = $amountCents > 0 ? (int) round($approvedCents / $amountCents * 100) : 0;
                    @endphp
                    <tr>
                        <td class="title-cell"><a href="{{ route('budgets.show', $budget) }}">{{ $budget->name }}</a><span class="sub">{{ $budget->code }}</span></td>
                        <td class="nowrap">{{ $budget->fiscal_year }}</td>
                        <td>
                            @foreach (array_filter([$budget->project ? $budget->project->code.' — '.$budget->project->title : null, $budget->department?->displayName(), $budget->fundingSource?->displayName()]) as $holder)
                                <div class="small">{{ $holder }}</div>
                            @endforeach
                        </td>
                        <td class="num">{{ money($budget->amount, $budget->currency) }}</td>
                        <td class="num">{{ money($approvedCents / 100, $budget->currency) }}
                            @if (\App\Models\Budget::cents($budget->pending_spend) > 0)
                                <span class="sub">{{ __('+ :amount pending', ['amount' => money($budget->pending_spend, $budget->currency)]) }}</span>
                            @endif
                        </td>
                        <td class="num">{{ money(($amountCents - $approvedCents) / 100, $budget->currency) }}</td>
                        <td style="min-width:130px"><x-progress :value="$pct" :tone="\App\Models\Budget::utilisationTone($pct)" /></td>
                        <td><x-status domain="budget" :value="$budget->status" /></td>
                    </tr>
                @endforeach
                </tbody>
                @if ($totals->isNotEmpty())
                    <tfoot>
                    @foreach ($totals as $currency => $row)
                        <tr>
                            <th scope="row" colspan="3">{{ $totals->count() > 1 ? __('Total (:currency, all filtered budgets)', ['currency' => $currency]) : __('Total (all filtered budgets)') }}</th>
                            <td class="num"><strong>{{ money($row['amount'], $currency) }}</strong></td>
                            <td class="num"><strong>{{ money($row['approved'], $currency) }}</strong></td>
                            <td class="num"><strong>{{ money($row['remaining'], $currency) }}</strong></td>
                            <td colspan="2"></td>
                        </tr>
                    @endforeach
                    </tfoot>
                @endif
            </table></div>
        @endif
        {{ $budgets->links() }}
    </div>
</x-layouts.app>
