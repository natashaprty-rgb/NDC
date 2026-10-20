{{-- Planned (allocations) vs actual (approved expenses) per expense category. Expects $breakdown, $summary and $currency. --}}
@if ($breakdown->isEmpty())
    <x-empty icon="chart" :message="__('No allocations or expenses recorded yet.')" />
@else
    <div class="table-wrap"><table class="table">
        <thead><tr>
            <th scope="col">{{ __('Expense category') }}</th>
            <th scope="col" class="num">{{ __('Planned') }}</th>
            <th scope="col" class="num">{{ __('Actual (approved)') }}</th>
            <th scope="col" class="num">{{ __('Pending') }}</th>
            <th scope="col" class="num">{{ __('Variance') }}</th>
            <th scope="col" class="num">{{ __('Used') }}</th>
        </tr></thead>
        <tbody>
        @foreach ($breakdown as $row)
            <tr>
                <th scope="row" style="font-weight:500">{{ $row['category'] }}</th>
                <td class="num">{{ money($row['planned'], $currency) }}</td>
                <td class="num">{{ money($row['approved'], $currency) }}</td>
                <td class="num">{{ money($row['pending'], $currency) }}</td>
                <td class="num" @if ($row['variance'] < 0) style="color:var(--danger)" @endif>{{ money($row['variance'], $currency) }}</td>
                <td class="num">{{ $row['utilisation'] === null ? '—' : $row['utilisation'].'%' }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
            @php $variance = round($summary['planned'] - $summary['approved'], 2); @endphp
            <tr>
                <th scope="row">{{ __('Total') }}</th>
                <td class="num"><strong>{{ money($summary['planned'], $currency) }}</strong></td>
                <td class="num"><strong>{{ money($summary['approved'], $currency) }}</strong></td>
                <td class="num"><strong>{{ money($summary['pending'], $currency) }}</strong></td>
                <td class="num" @if ($variance < 0) style="color:var(--danger)" @endif><strong>{{ money($variance, $currency) }}</strong></td>
                <td class="num">{{ $summary['planned'] > 0 ? (int) round($summary['approved'] / $summary['planned'] * 100).'%' : '—' }}</td>
            </tr>
        </tfoot>
    </table></div>
    <p class="small muted" style="padding:0 1.1rem">{{ __('Variance = planned minus actual approved spending. A negative variance means the category is over its plan. Rejected expenses are not counted.') }}</p>
@endif
