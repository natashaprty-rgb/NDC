@if ($asset->disposals->isNotEmpty())
    <div class="card" id="disposals">
        <div class="card__head"><h2>{{ __('Disposal and retirement') }}</h2></div>
        <ul class="list">
            @foreach ($asset->disposals as $disposal)
                <li>
                    <div class="list__row">
                        <div>
                            <x-status domain="disposal_action" :value="$disposal->action" />
                            <x-status domain="disposal" :value="$disposal->status" />
                            <div class="list__meta">
                                {{ __('Requested by :name on :date', ['name' => $disposal->requester?->name ?? '—', 'date' => fmt_date($disposal->created_at)]) }}
                                @if ($disposal->method) · {{ __('Method') }}: {{ $disposal->method }} @endif
                                @if ($disposal->proposed_date) · {{ __('Proposed date') }}: {{ fmt_date($disposal->proposed_date) }} @endif
                                @if ($disposal->completed_on) · {{ __('Completed') }}: {{ fmt_date($disposal->completed_on) }} @endif
                                @if ($disposal->proceeds !== null) · {{ __('Proceeds') }}: {{ money($disposal->proceeds, $asset->currency) }} @endif
                            </div>
                            <div class="small prose">{{ $disposal->reason }}</div>
                        </div>
                    </div>
                    @can('complete', $disposal)
                        <form method="POST" action="{{ route('assets.disposals.complete', [$asset, $disposal]) }}" class="form-grid" style="margin-top:.75rem"
                            data-confirm="{{ __('Record this asset as disposed? This cannot be undone.') }}">
                            @csrf
                            <x-form.input name="completed_on" id="d{{ $disposal->id }}_completed" type="date" :label="__('Disposal date')" :value="today()->toDateString()" required />
                            <x-form.input name="proceeds" id="d{{ $disposal->id }}_proceeds" type="number" step="0.01" min="0" :label="__('Proceeds (if sold)')" />
                            <x-form.input name="method" id="d{{ $disposal->id }}_method" :label="__('Method')" :value="$disposal->method" maxlength="255" class="span-2" />
                            <div class="span-2"><button class="btn btn-danger btn-sm">{{ __('Complete disposal') }}</button></div>
                        </form>
                    @endcan
                </li>
            @endforeach
        </ul>
    </div>
@endif
