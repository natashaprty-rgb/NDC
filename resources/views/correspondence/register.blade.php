@php
    $title = $direction === 'incoming' ? __('Incoming correspondence register') : __('Outgoing correspondence register');
@endphp
<x-layouts.app :title="$title">
    <x-page-header :title="$title" :breadcrumbs="[__('Correspondence') => route('correspondence.index')]">
        <x-slot:actions>
            <button type="button" class="btn btn-primary" data-print><x-icon name="printer" /> {{ __('Print') }}</button>
        </x-slot:actions>
    </x-page-header>

    <div class="card no-print">
        <x-filters>
            <div class="field">
                <label for="reg_direction">{{ __('Register') }}</label>
                <select id="reg_direction" name="direction" class="select">
                    <option value="incoming" @selected($direction === 'incoming')>{{ __('Incoming register') }}</option>
                    <option value="outgoing" @selected($direction === 'outgoing')>{{ __('Outgoing register') }}</option>
                </select>
            </div>
            <div class="field">
                <label for="reg_from">{{ __('Date from') }}</label>
                <input type="date" id="reg_from" name="from" value="{{ $from }}" class="input">
            </div>
            <div class="field">
                <label for="reg_to">{{ __('Date to') }}</label>
                <input type="date" id="reg_to" name="to" value="{{ $to }}" class="input">
            </div>
        </x-filters>
        @error('to')<div class="card__body"><div class="field-error">{{ $message }}</div></div>@enderror
    </div>

    <div class="card">
        <div class="card__body">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
                <img src="{{ asset('images/logo-horizontal.png') }}" alt="{{ __('National Data Center Somalia') }}" style="max-height:48px;width:auto">
                <div class="text-right">
                    <strong>{{ $title }}</strong>
                    <div class="small muted">
                        @if ($from || $to)
                            {{ __('Period: :from to :to', ['from' => $from ? fmt_date($from) : '…', 'to' => $to ? fmt_date($to) : '…']) }}
                        @else
                            {{ __('All dates') }}
                        @endif
                        · {{ __('Printed :date', ['date' => fmt_date(now(), true)]) }}
                    </div>
                </div>
            </div>
        </div>
        @if ($letters->isEmpty())
            <x-empty icon="mail" :message="__('No letters registered in this period.')" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr>
                    <th>#</th>
                    <th>{{ __('Reference') }}</th>
                    <th>{{ $direction === 'incoming' ? __('Date received') : __('Date sent') }}</th>
                    <th>{{ __('Letter date') }}</th>
                    <th>{{ $direction === 'incoming' ? __('Sender') : __('Recipient') }}</th>
                    <th>{{ __('Sender\'s reference number') }}</th>
                    <th>{{ __('Subject') }}</th>
                    <th>{{ __('Department') }}</th>
                    <th>{{ __('Assigned to') }}</th>
                    <th>{{ __('Status') }}</th>
                </tr></thead>
                <tbody>
                @foreach ($letters as $letter)
                    <tr>
                        <td class="num">{{ $loop->iteration }}</td>
                        <td class="mono nowrap">{{ $letter->reference_number }}</td>
                        <td class="nowrap">{{ fmt_date($letter->received_or_sent_date) }}</td>
                        <td class="nowrap">{{ fmt_date($letter->letter_date) }}</td>
                        <td>{{ $direction === 'incoming' ? $letter->sender : $letter->recipient }}</td>
                        <td>{{ $letter->external_reference ?: '—' }}</td>
                        <td>{{ $letter->subject }}@if ($letter->is_confidential) <span class="badge badge--danger badge--plain">{{ __('Confidential') }}</span>@endif</td>
                        <td>{{ $letter->department?->displayName() ?? '—' }}</td>
                        <td>{{ $letter->assignee?->name ?? '—' }}</td>
                        <td>{{ \App\Support\Status::label('correspondence', $letter->status) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="card__foot small muted">
                {{ trans_choice(':count letter|:count letters', $total) }}
                @if ($total > $limit) — {{ __('Only the first :limit are shown; narrow the date range to print the rest.', ['limit' => $limit]) }}@endif
            </div>
        @endif
    </div>
</x-layouts.app>
