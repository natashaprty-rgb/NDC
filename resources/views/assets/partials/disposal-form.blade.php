<div class="card" id="request-disposal">
    <div class="card__head"><h2>{{ __('Disposal or retirement') }}</h2></div>
    <div class="card__body">
        <details @if ($errors->hasAny(['action', 'reason'])) open @endif>
            <summary>{{ __('Request disposal or retirement') }}</summary>
            <p class="small muted">{{ __('The request must be approved by an authorised officer other than you before it takes effect.') }}</p>
            <form method="POST" action="{{ route('assets.disposals.store', $asset) }}">
                @csrf
                <x-form.select name="action" id="disp_action" :label="__('Request')" :options="\App\Support\Status::options('disposal_action')" required :placeholder="false" />
                <x-form.textarea name="reason" id="disp_reason" :label="__('Reason')" rows="3" required />
                <x-form.input name="method" id="disp_method" :label="__('Proposed method')" maxlength="255" :help="__('For example: auction, recycling, donation, destruction.')" />
                <x-form.input name="proposed_date" id="disp_date" type="date" :label="__('Proposed date')" />
                <button class="btn btn-danger">{{ __('Submit for approval') }}</button>
            </form>
        </details>
    </div>
</div>
