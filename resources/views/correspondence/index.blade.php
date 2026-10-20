<x-layouts.app :title="__('Correspondence')">
    <x-page-header :title="__('Correspondence')" :subtitle="__('Register and follow up incoming and outgoing official letters.')">
        <x-slot:actions>
            <a href="{{ route('correspondence.register', ['direction' => request('direction') === 'outgoing' ? 'outgoing' : 'incoming']) }}" class="btn"><x-icon name="printer" /> {{ __('Printable register') }}</a>
            @can('create', \App\Models\Correspondence::class)
                <a href="{{ route('correspondence.create', ['direction' => 'incoming']) }}" class="btn btn-primary"><x-icon name="inbox" /> {{ __('Register incoming') }}</a>
                <a href="{{ route('correspondence.create', ['direction' => 'outgoing']) }}" class="btn btn-accent"><x-icon name="mail" /> {{ __('Register outgoing') }}</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-3">
        <div class="stat">
            <div class="stat__label">{{ __('Open letters') }}</div>
            <div class="stat__value">{{ number_format($counts['open']) }}</div>
            <div class="stat__meta"><a href="{{ route('correspondence.index', ['status' => 'received']) }}">{{ __('Awaiting action') }}</a></div>
        </div>
        <div @class(['stat', 'stat--alert' => $counts['overdue'] > 0])>
            <div class="stat__label">{{ __('Overdue responses') }}</div>
            <div class="stat__value">{{ number_format($counts['overdue']) }}</div>
            <div class="stat__meta"><a href="{{ route('correspondence.index', ['overdue' => 1]) }}">{{ __('Show overdue') }}</a></div>
        </div>
        <div class="stat">
            <div class="stat__label">{{ __('Assigned to me') }}</div>
            <div class="stat__value">{{ number_format($counts['mine']) }}</div>
            <div class="stat__meta"><a href="{{ route('correspondence.index', ['assigned_to' => auth()->id()]) }}">{{ __('Show my letters') }}</a></div>
        </div>
    </div>

    <div class="card">
        @php $tab = in_array(request('direction'), ['incoming', 'outgoing'], true) ? request('direction') : null; @endphp
        <nav class="tabs" aria-label="{{ __('Registers') }}" style="padding:0 1rem;margin-bottom:0">
            @foreach ([null => __('All letters'), 'incoming' => __('Incoming register'), 'outgoing' => __('Outgoing register')] as $value => $label)
                <a href="{{ request()->fullUrlWithQuery(['direction' => $value ?: null, 'page' => null]) }}" @class(['is-active' => $tab === ($value ?: null)]) @if($tab === ($value ?: null)) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <x-filters>
            @if ($tab)<input type="hidden" name="direction" value="{{ $tab }}">@endif
            <x-search-field :placeholder="__('Reference, subject, sender or recipient')" />
            <x-filter-select name="status" :label="__('Status')" :options="\App\Support\Status::options('correspondence')" />
            <x-filter-select name="department" :label="__('Department')" :options="$departments" />
            <x-filter-select name="assigned_to" :label="__('Assigned to')" :options="$users" />
            <x-filter-select name="priority" :label="__('Priority')" :options="\App\Support\Status::options('correspondence_priority')" />
            <x-filter-select name="confidential" :label="__('Confidential')" :options="['yes' => __('Confidential only'), 'no' => __('Not confidential')]" />
            <div class="field">
                <label for="flt_from">{{ __('Date from') }}</label>
                <input type="date" id="flt_from" name="from" value="{{ request('from') }}" class="input">
            </div>
            <div class="field">
                <label for="flt_to">{{ __('Date to') }}</label>
                <input type="date" id="flt_to" name="to" value="{{ request('to') }}" class="input">
            </div>
            <div class="field" style="flex:0 0 auto">
                <label class="check" for="flt_overdue"><input type="checkbox" id="flt_overdue" name="overdue" value="1" @checked(request()->boolean('overdue'))> {{ __('Overdue only') }}</label>
            </div>
        </x-filters>

        @if ($letters->isEmpty())
            <x-empty icon="mail" :message="__('No letters match your search.')" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr>
                    <th><x-sort-link column="reference_number" :label="__('Reference')" /></th>
                    <th><x-sort-link column="subject" :label="__('Subject')" /></th>
                    <th>{{ __('From / To') }}</th>
                    <th><x-sort-link column="received_or_sent_date" :label="__('Date')" /></th>
                    <th>{{ __('Assigned to') }}</th>
                    <th><x-sort-link column="response_due_date" :label="__('Response due')" /></th>
                    <th><x-sort-link column="status" :label="__('Status')" /></th>
                </tr></thead>
                <tbody>
                @foreach ($letters as $letter)
                    <tr>
                        <td class="title-cell nowrap">
                            <a href="{{ route('correspondence.show', $letter) }}" class="mono">{{ $letter->reference_number }}</a>
                            <span class="sub"><x-status domain="correspondence_direction" :value="$letter->direction" class="badge--plain" />@if ($letter->external_reference) {{ $letter->external_reference }}@endif</span>
                        </td>
                        <td class="title-cell">
                            <a href="{{ route('correspondence.show', $letter) }}">{{ $letter->subject }}</a>
                            <span class="sub">
                                @if ($letter->is_confidential)<span class="badge badge--danger badge--plain"><x-icon name="lock" /> {{ __('Confidential') }}</span>@endif
                                @if (in_array($letter->priority, ['high', 'urgent'], true))<x-status domain="correspondence_priority" :value="$letter->priority" />@endif
                                {{ $letter->department?->displayName() }}
                            </span>
                        </td>
                        <td>{{ $letter->direction === 'incoming' ? __('From: :name', ['name' => $letter->sender]) : __('To: :name', ['name' => $letter->recipient]) }}</td>
                        <td class="nowrap">{{ fmt_date($letter->received_or_sent_date) }}</td>
                        <td>{{ $letter->assignee?->name ?? '—' }}</td>
                        <td class="nowrap">
                            {{ fmt_date($letter->response_due_date) }}
                            @if ($letter->isOverdue())<span class="badge badge--danger">{{ __('Overdue') }}</span>@endif
                        </td>
                        <td><x-status domain="correspondence" :value="$letter->status" /></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
        {{ $letters->links() }}
    </div>
</x-layouts.app>
