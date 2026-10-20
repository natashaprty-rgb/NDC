{{-- Follow-up actions for a letter; each form is shown only when the policy allows it. --}}
@php
    $user = auth()->user();
    $canAny = ! $letter->isClosed() && ($user->can('assign', $letter) || $user->can('handle', $letter));
@endphp

@if ($letter->isClosed())
    <div class="card"><div class="card__body">
        <p class="muted mb-0">{{ __('This letter is closed. No further follow-up actions can be recorded.') }}</p>
    </div></div>
@elseif (! $canAny)
    <div class="card"><div class="card__body">
        <p class="muted mb-0">{{ __('You can view this letter but not record follow-up actions on it.') }}</p>
    </div></div>
@endif

@if ($letter->allowsAction('respond') && $user->can('respond', $letter))
    <div class="card" id="respond">
        <div class="card__head"><h2>{{ __('Record response') }}</h2></div>
        <form method="POST" action="{{ route('correspondence.respond', $letter) }}" enctype="multipart/form-data" class="card__body">
            @csrf
            @php $mode = old('mode', 'new'); @endphp
            <fieldset>
                <legend>{{ __('How was the letter answered?') }}</legend>
                <div class="checks">
                    <label class="check"><input type="radio" name="mode" value="new" @checked($mode === 'new')> {{ __('Register a new outgoing reply') }}</label>
                    <label class="check"><input type="radio" name="mode" value="link" @checked($mode === 'link')> {{ __('Link an outgoing letter already registered') }}</label>
                    <label class="check"><input type="radio" name="mode" value="none" @checked($mode === 'none')> {{ __('Answered without a letter (note only)') }}</label>
                </div>
                @error('mode')<div class="field-error">{{ $message }}</div>@enderror
            </fieldset>
            <fieldset>
                <legend>{{ __('New outgoing reply') }}</legend>
                <x-form.input name="reply_subject" :label="__('Subject')" :value="__('Re: :subject', ['subject' => $letter->subject])" maxlength="255" />
                <x-form.input name="reply_recipient" :label="__('Recipient')" :value="$letter->sender" maxlength="255" />
                <x-form.input name="reply_date" type="date" :label="__('Letter date')" :value="today()" />
                <x-form.textarea name="reply_summary" :label="__('Summary')" rows="2" maxlength="10000" />
                <x-form.input type="file" name="reply_scan" :label="__('Scanned reply (optional)')" />
                <x-form.checkbox name="reply_sent" :label="__('The reply has already been sent')" />
            </fieldset>
            @if ($replyOptions)
                <fieldset>
                    <legend>{{ __('Existing outgoing letter') }}</legend>
                    <x-form.select name="reply_id" :label="__('Reply letter')" :options="$replyOptions" />
                </fieldset>
            @endif
            <x-form.textarea name="note" :label="__('Note')" rows="2" maxlength="2000" id="respond_note" />
            <button type="submit" class="btn btn-success">{{ __('Record response') }}</button>
        </form>
    </div>
@endif

@if ($letter->allowsAction('send') && $user->can('markSent', $letter))
    <div class="card" id="send">
        <div class="card__head"><h2>{{ __('Mark as sent') }}</h2></div>
        <form method="POST" action="{{ route('correspondence.send', $letter) }}" class="card__body">
            @csrf
            <x-form.input name="sent_date" type="date" :label="__('Date sent')" :value="$letter->received_or_sent_date ?? today()" />
            <x-form.textarea name="note" :label="__('Dispatch note')" rows="2" maxlength="2000" id="send_note" :help="__('For example, delivery method or courier reference.')" />
            <button type="submit" class="btn btn-success">{{ __('Mark as sent') }}</button>
        </form>
    </div>
@endif

@if ($letter->allowsAction('assign') && $user->can('assign', $letter))
    <div class="card" id="assign">
        <div class="card__head"><h2>{{ __('Assign for action') }}</h2></div>
        <form method="POST" action="{{ route('correspondence.assign', $letter) }}" class="card__body">
            @csrf
            <x-form.select name="assigned_to" :label="__('Assign to')" :options="$users" :value="$letter->assigned_to" required id="assign_to" />
            <x-form.input name="response_due_date" type="date" :label="__('Response due date')" :value="$letter->response_due_date" id="assign_due" />
            <x-form.textarea name="note" :label="__('Instructions')" rows="2" maxlength="2000" id="assign_note" />
            <button type="submit" class="btn btn-primary">{{ __('Assign') }}</button>
        </form>
    </div>
@endif

@if ($letter->allowsAction('forward') && $user->can('handle', $letter))
    <div class="card" id="forward">
        <div class="card__head"><h2>{{ __('Forward') }}</h2></div>
        <form method="POST" action="{{ route('correspondence.forward', $letter) }}" class="card__body">
            @csrf
            <x-form.select name="to_user_id" :label="__('Forward to')" :options="collect($users)->except($user->id)->all()" required />
            <x-form.textarea name="note" :label="__('Note')" rows="2" maxlength="2000" id="forward_note" />
            <button type="submit" class="btn">{{ __('Forward') }}</button>
        </form>
    </div>

    <div class="card" id="note">
        <div class="card__head"><h2>{{ __('Add follow-up note') }}</h2></div>
        <form method="POST" action="{{ route('correspondence.note', $letter) }}" class="card__body">
            @csrf
            <x-form.textarea name="note" :label="__('Note')" rows="2" maxlength="2000" required id="followup_note" />
            <button type="submit" class="btn">{{ __('Add note') }}</button>
        </form>
    </div>
@endif

@if ($letter->allowsAction('close') && $user->can('close', $letter))
    <div class="card" id="close">
        <div class="card__head"><h2>{{ __('Close letter') }}</h2></div>
        <form method="POST" action="{{ route('correspondence.close', $letter) }}" class="card__body" data-confirm="{{ __('Close this letter? No further actions can be recorded.') }}">
            @csrf
            <x-form.textarea name="note" :label="__('Closing note')" rows="2" maxlength="2000" id="close_note" />
            <button type="submit" class="btn btn-danger">{{ __('Close letter') }}</button>
        </form>
    </div>
@endif
