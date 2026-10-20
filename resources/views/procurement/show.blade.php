@php
    $user = auth()->user();
    $canManage = $user->can('manage', $pr);
    $outstanding = $pr->items->sum(fn ($i) => $i->outstandingQuantity());
@endphp
<x-layouts.app :title="$pr->reference_number">
    <x-page-header :title="$pr->title" :breadcrumbs="[__('Purchase requests') => route('procurement.index')]">
        <x-slot:meta>
            <p><span class="mono">{{ $pr->reference_number }}</span> · <x-status domain="procurement" :value="$pr->status" /></p>
        </x-slot:meta>
        <x-slot:actions>
            @can('edit', $pr)
                <a href="{{ route('procurement.edit', $pr) }}" class="btn"><x-icon name="edit" /> {{ __('Edit') }}</a>
            @endcan
            @if ($canManage && $pr->canBeOrdered())
                <a href="{{ route('purchase-orders.create', $pr) }}" class="btn btn-primary"><x-icon name="plus" /> {{ __('Create purchase order') }}</a>
            @endif
            @can('delete', $pr)
                <x-delete-button :action="route('procurement.destroy', $pr)" class="btn btn-danger" :confirm="__('Delete this purchase request?')" />
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-main">
        <div class="stack">
            <div class="card">
                <div class="card__head"><h2>{{ __('Request details') }}</h2></div>
                <div class="card__body">
                    <dl class="dl">
                        <dt>{{ __('Reference') }}</dt><dd class="mono">{{ $pr->reference_number }}</dd>
                        <dt>{{ __('Requested by') }}</dt><dd>{{ $pr->requester?->name ?? '—' }}</dd>
                        <dt>{{ __('Department') }}</dt><dd>{{ $pr->department?->displayName() ?? '—' }}</dd>
                        <dt>{{ __('Project') }}</dt><dd>@if ($pr->project)@if (Route::has('projects.show'))<a href="{{ route('projects.show', $pr->project) }}">{{ $pr->project->code }} — {{ $pr->project->title }}</a>@else{{ $pr->project->code }} — {{ $pr->project->title }}@endif @else — @endif</dd>
                        <dt>{{ __('Budget line') }}</dt><dd>@if ($pr->budget)@if (Route::has('budgets.show'))<a href="{{ route('budgets.show', $pr->budget) }}">{{ $pr->budget->code }} — {{ $pr->budget->name }}</a>@else{{ $pr->budget->code }} — {{ $pr->budget->name }}@endif @else — @endif</dd>
                        <dt>{{ __('Needed by') }}</dt><dd>{{ fmt_date($pr->needed_by) }}</dd>
                        <dt>{{ __('Estimated total') }}</dt><dd><strong>{{ money($pr->estimated_total, $pr->currency) }}</strong></dd>
                        <dt>{{ __('Submitted') }}</dt><dd>{{ fmt_date($pr->submitted_at, true) }}</dd>
                        <dt>{{ __('Justification') }}</dt><dd class="prose">{{ $pr->justification ?: '—' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card" id="items">
                <div class="card__head"><h2>{{ __('Items') }}</h2>
                    @if (in_array($pr->status, ['ordered', 'partially_received', 'received'], true))
                        <span class="muted small">{{ $outstanding > 0 ? __('Items outstanding') : __('All items received') }}</span>
                    @endif
                </div>
                @include('procurement.partials.items-table', ['pr' => $pr])
            </div>

            @include('procurement.partials.quotations')

            <div class="card" id="orders">
                <div class="card__head"><h2>{{ __('Purchase orders') }}</h2></div>
                @if ($pr->purchaseOrders->isEmpty())
                    <x-empty icon="file" :message="__('No purchase order has been issued for this request.')" />
                @else
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>{{ __('PO number') }}</th><th>{{ __('Supplier') }}</th><th>{{ __('Order date') }}</th><th>{{ __('Expected delivery') }}</th><th class="text-right">{{ __('Amount') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                        @foreach ($pr->purchaseOrders as $po)
                            <tr>
                                <td class="nowrap"><a href="{{ route('purchase-orders.show', $po) }}">{{ $po->po_number }}</a></td>
                                <td>{{ $po->supplier?->name ?? '—' }}</td>
                                <td class="nowrap">{{ fmt_date($po->order_date) }}</td>
                                <td class="nowrap">{{ fmt_date($po->expected_delivery_date) }}</td>
                                <td class="num text-right nowrap">{{ money($po->total_amount, $po->currency) }}</td>
                                <td><x-status domain="purchase_order" :value="$po->status" /></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </div>

            <x-approval-panel :model="$pr" />
            <x-attachments :model="$pr" :can-upload="$user->can('update', $pr)" :title="__('Supporting documents')" />
            <x-audit-trail :model="$pr" />
        </div>

        <div class="stack">
            <div class="card">
                <div class="card__head"><h2>{{ __('Progress') }}</h2></div>
                <div class="card__body">
                    <ul class="timeline">
                        @foreach ($timeline as $event)
                            <li>
                                <div><strong>{{ $event['label'] }}</strong></div>
                                <div class="small muted">{{ fmt_date($event['date'], true) }}@if ($event['by']) · {{ $event['by'] }}@endif</div>
                                @if ($event['note'])<div class="small prose">{{ $event['note'] }}</div>@endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            @can('submit', $pr)
                <div class="card">
                    <div class="card__head"><h2>{{ __('Submit for approval') }}</h2></div>
                    <form method="POST" action="{{ route('procurement.submit', $pr) }}" class="card__body">
                        @csrf
                        <p class="small muted">{{ __('Once submitted, the request cannot be edited unless it is returned. Another authorised officer must approve it.') }}</p>
                        <x-form.textarea name="note" :label="__('Note to approver')" rows="2" />
                        <button class="btn btn-primary">{{ __('Submit for approval') }}</button>
                    </form>
                </div>
            @endcan

            @can('cancel', $pr)
                <div class="card">
                    <div class="card__body">
                        <details @if ($errors->has('reason')) open @endif>
                            <summary>{{ __('Cancel this request') }}</summary>
                            <form method="POST" action="{{ route('procurement.cancel', $pr) }}" style="margin-top:.75rem" data-confirm="{{ __('Cancel this purchase request?') }}">
                                @csrf
                                <x-form.textarea name="reason" id="cancel_reason" :label="__('Reason')" rows="2" required />
                                <button class="btn btn-danger">{{ __('Cancel request') }}</button>
                            </form>
                        </details>
                    </div>
                </div>
            @endcan
        </div>
    </div>
</x-layouts.app>
