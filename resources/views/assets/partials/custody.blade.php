<div class="card" id="custody">
    <div class="card__head"><h2>{{ __('Custody') }}</h2></div>
    <div class="card__body">
        @if ($openAssignment)
            <dl class="dl">
                <dt>{{ __('Held by') }}</dt><dd>{{ $openAssignment->user?->name ?? __('Department pool') }}</dd>
                <dt>{{ __('Department') }}</dt><dd>{{ $openAssignment->department?->displayName() ?? '—' }}</dd>
                <dt>{{ __('Location') }}</dt><dd>{{ $openAssignment->location ?? '—' }}</dd>
                <dt>{{ __('Since') }}</dt><dd>{{ fmt_date($openAssignment->assigned_on) }}</dd>
            </dl>
        @else
            <p class="muted mb-0">{{ __('Not assigned to anyone.') }}</p>
        @endif
    </div>

    @if ($canUpdate && $inService)
        @if (! $openAssignment && $asset->status === 'available' && ! $openDisposal)
            <div class="card__foot">
                <form method="POST" action="{{ route('assets.assign', $asset) }}">
                    @csrf
                    <h3>{{ __('Assign asset') }}</h3>
                    <x-form.select name="user_id" id="assign_user" :label="__('Employee')" :options="$users" />
                    <x-form.select name="department_id" id="assign_department" :label="__('Department')" :options="$departments"
                        :help="__('Defaults to the employee’s department.')" />
                    <x-form.input name="location" id="assign_location" :label="__('Location')" :value="$asset->location" maxlength="255" />
                    <x-form.input name="assigned_on" id="assign_date" type="date" :label="__('Assignment date')" :value="today()->toDateString()" required />
                    <x-form.textarea name="notes" id="assign_notes" :label="__('Notes')" rows="2" />
                    <button class="btn btn-primary">{{ __('Assign') }}</button>
                </form>
            </div>
        @elseif ($openAssignment)
            <div class="card__foot">
                <form method="POST" action="{{ route('assets.return', $asset) }}">
                    @csrf
                    <h3>{{ __('Return asset') }}</h3>
                    <x-form.input name="returned_on" id="return_date" type="date" :label="__('Return date')" :value="today()->toDateString()" required />
                    <x-form.select name="condition_on_return" id="return_condition" :label="__('Condition on return')" :options="\App\Support\Status::options('asset_condition')" :value="$asset->condition" required :placeholder="false" />
                    <x-form.textarea name="return_notes" id="return_notes" :label="__('Notes')" rows="2" />
                    <button class="btn">{{ __('Record return') }}</button>
                </form>
            </div>
            @if ($asset->status === 'assigned')
                <div class="card__foot">
                    <details @if ($errors->hasAny(['user_id', 'department_id', 'transferred_on'])) open @endif>
                        <summary><strong>{{ __('Transfer to another holder') }}</strong></summary>
                        <form method="POST" action="{{ route('assets.transfer', $asset) }}" style="margin-top:.75rem">
                            @csrf
                            <x-form.select name="user_id" id="transfer_user" :label="__('New employee')" :options="$users" />
                            <x-form.select name="department_id" id="transfer_department" :label="__('New department')" :options="$departments" />
                            <x-form.input name="location" id="transfer_location" :label="__('Location')" :value="$asset->location" maxlength="255" />
                            <x-form.input name="transferred_on" id="transfer_date" type="date" :label="__('Transfer date')" :value="today()->toDateString()" required />
                            <x-form.select name="condition_on_transfer" id="transfer_condition" :label="__('Condition at handover')" :options="\App\Support\Status::options('asset_condition')" :value="$asset->condition" required :placeholder="false" />
                            <x-form.textarea name="notes" id="transfer_notes" :label="__('Notes')" rows="2" />
                            <button class="btn btn-primary">{{ __('Transfer') }}</button>
                        </form>
                    </details>
                </div>
            @endif
        @elseif ($openDisposal)
            <div class="card__foot small muted">{{ __('This asset has an open disposal or retirement request.') }}</div>
        @endif
    @endif
</div>
