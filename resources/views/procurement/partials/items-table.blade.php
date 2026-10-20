@if ($pr->items->isEmpty())
    <x-empty icon="cart" :message="__('No items have been added.')" />
@else
    <div class="table-wrap"><table class="table">
        <thead><tr>
            <th>{{ __('Item') }}</th>
            <th class="text-right">{{ __('Quantity') }}</th>
            <th class="text-right">{{ __('Estimated unit cost') }}</th>
            <th class="text-right">{{ __('Line total') }}</th>
            <th class="text-right">{{ __('Received') }}</th>
            <th class="text-right">{{ __('Outstanding') }}</th>
        </tr></thead>
        <tbody>
        @foreach ($pr->items as $item)
            <tr>
                <td>{{ $item->description }}@if ($item->specifications)<span class="sub">{{ $item->specifications }}</span>@endif</td>
                <td class="num text-right nowrap">{{ (float) $item->quantity }} {{ $item->unit }}</td>
                <td class="num text-right nowrap">{{ money($item->estimated_unit_cost, $pr->currency) }}</td>
                <td class="num text-right nowrap">{{ money($item->lineTotal(), $pr->currency) }}</td>
                <td class="num text-right">{{ (float) $item->quantity_received }}</td>
                <td class="num text-right">@if ($item->outstandingQuantity() > 0)<strong>{{ $item->outstandingQuantity() }}</strong>@else 0 @endif</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot><tr>
            <th colspan="3" class="text-right">{{ __('Estimated total') }}</th>
            <th class="num text-right nowrap">{{ money($pr->estimated_total, $pr->currency) }}</th>
            <th colspan="2"></th>
        </tr></tfoot>
    </table></div>
@endif
