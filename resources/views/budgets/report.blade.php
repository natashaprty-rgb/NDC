<x-layouts.app :title="__('Budget summary')">
    <x-page-header :title="__('Budget summary')" :subtitle="__('Allocation against actual spending for each budget. Generated :date.', ['date' => fmt_date(now(), true)])"
        :breadcrumbs="[__('Budgets') => route('budgets.index')]">
        <x-slot:actions>
            <button type="button" class="btn btn-primary" data-print><x-icon name="printer" /> {{ __('Print') }}</button>
        </x-slot:actions>
    </x-page-header>

    @if ($filterLabels)
        <p class="small muted">{{ __('Filters') }}:
            @foreach ($filterLabels as $label => $value){{ $label }}: <strong>{{ $value }}</strong>@if (! $loop->last) · @endif @endforeach
        </p>
    @endif
    @if ($total > $limit)
        <div class="alert alert--warning" role="status">{{ __('Showing the first :limit of :total budgets. Narrow the filters to include the rest.', ['limit' => $limit, 'total' => $total]) }}</div>
    @endif

    @if ($rows->isEmpty())
        <div class="card"><x-empty icon="wallet" :message="__('No budgets match the selected filters.')" /></div>
    @else
        <div class="card">
            <div class="card__head"><h2>{{ __('Overview') }}</h2></div>
            <div class="table-wrap"><table class="table">
                <thead><tr>
                    <th scope="col">{{ __('Budget') }}</th>
                    <th scope="col">{{ __('Fiscal year') }}</th>
                    <th scope="col">{{ __('Status') }}</th>
                    <th scope="col" class="num">{{ __('Amount') }}</th>
                    <th scope="col" class="num">{{ __('Planned') }}</th>
                    <th scope="col" class="num">{{ __('Approved spend') }}</th>
                    <th scope="col" class="num">{{ __('Pending') }}</th>
                    <th scope="col" class="num">{{ __('Remaining') }}</th>
                    <th scope="col" class="num">{{ __('Used') }}</th>
                </tr></thead>
                <tbody>
                @foreach ($rows as $row)
                    @php [$b, $s] = [$row['budget'], $row['summary']]; @endphp
                    <tr>
                        <td class="title-cell"><a href="#budget-{{ $b->id }}">{{ $b->name }}</a><span class="sub">{{ $b->code }}</span></td>
                        <td>{{ $b->fiscal_year }}</td>
                        <td><x-status domain="budget" :value="$b->status" /></td>
                        <td class="num">{{ money($s['amount'], $b->currency) }}</td>
                        <td class="num">{{ money($s['planned'], $b->currency) }}</td>
                        <td class="num">{{ money($s['approved'], $b->currency) }}</td>
                        <td class="num">{{ money($s['pending'], $b->currency) }}</td>
                        <td class="num">{{ money($s['remaining'], $b->currency) }}</td>
                        <td class="num">{{ $s['utilisation'] }}%</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </div>

        @foreach ($rows as $row)
            @php [$b, $s] = [$row['budget'], $row['summary']]; @endphp
            <div class="card" id="budget-{{ $b->id }}" style="break-inside:avoid">
                <div class="card__head">
                    <h2>{{ $b->code }} — {{ $b->name }}</h2>
                    <x-status domain="budget" :value="$b->status" />
                </div>
                <div class="card__body">
                    <dl class="dl">
                        <dt>{{ __('Budget holder') }}</dt>
                        <dd>{{ collect([$b->project ? $b->project->code.' — '.$b->project->title : null, $b->department?->displayName(), $b->fundingSource?->displayName()])->filter()->join(' · ') ?: '—' }}</dd>
                        <dt>{{ __('Period') }}</dt><dd>{{ fmt_date($b->start_date) }} – {{ fmt_date($b->end_date) }}</dd>
                        <dt>{{ __('Utilisation') }}</dt><dd><x-progress :value="$s['utilisation']" :tone="\App\Models\Budget::utilisationTone($s['utilisation'])" /></dd>
                    </dl>
                </div>
                @include('budgets.partials.breakdown', ['breakdown' => $row['breakdown'], 'summary' => $s, 'currency' => $b->currency])
            </div>
        @endforeach
    @endif
</x-layouts.app>
