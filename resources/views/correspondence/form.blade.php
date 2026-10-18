@php $editing = $item->exists; @endphp
<x-layout :title="$editing ? __('correspondence.edit') : ($item->direction === 'outgoing' ? __('correspondence.new_outgoing') : __('correspondence.new_incoming'))"
          :breadcrumbs="[__('correspondence.title') => route('correspondence.index'), ($editing ? $item->reference : __('common.new'))]">
    <x-card>
        <form method="POST" action="{{ $editing ? route('correspondence.update', $item) : route('correspondence.store') }}" @unless($editing) enctype="multipart/form-data" @endunless>
            @csrf
            @if($editing) @method('PUT') @endif
            <div class="form-grid">
                @if($editing)
                    <div class="field"><span class="label">{{ __('correspondence.direction') }}</span><span><x-badge :value="$item->direction" /></span></div>
                @else
                    <x-field.select name="direction" :label="__('correspondence.direction')" :options="enum_options(\App\Models\Correspondence::DIRECTIONS)" :value="$item->direction" :required="true" :placeholder="false" />
                @endif
                <x-field.input name="external_reference" :label="__('correspondence.external_reference')" :value="$item->external_reference" maxlength="100" />
                <x-field.input name="subject" :label="__('correspondence.subject')" :value="$item->subject" :required="true" maxlength="300" class="span-2" />
                <x-field.input name="sender" :label="__('correspondence.sender')" :value="$item->sender" :required="true" maxlength="200" />
                <x-field.input name="recipient" :label="__('correspondence.recipient')" :value="$item->recipient" :required="true" maxlength="200" />
                <x-field.input name="letter_date" type="date" :label="__('correspondence.letter_date')" :value="$item->letter_date" />
                <x-field.input name="received_or_sent_date" type="date" :label="__('correspondence.received_or_sent_date')" :value="$item->received_or_sent_date" :required="true" />
                <x-field.select name="delivery_method" :label="__('correspondence.delivery_method')" :options="enum_options(\App\Models\Correspondence::DELIVERY_METHODS)" :value="$item->delivery_method" />
                <x-field.select name="priority" :label="__('correspondence.priority')" :options="enum_options(\App\Models\Correspondence::PRIORITIES)" :value="$item->priority" :required="true" :placeholder="false" />
                <x-field.select name="department_id" :label="__('correspondence.responsible_department')" :options="$departments" :value="$item->department_id" :placeholder="__('common.none')" />
                <x-field.select name="assigned_to" :label="__('correspondence.assigned_officer')" :options="$users" :value="$item->assigned_to" :placeholder="__('common.unassigned')" />
                <x-field.input name="response_deadline" type="date" :label="__('correspondence.response_deadline')" :value="$item->response_deadline" />
                <x-field.select name="confidentiality" :label="__('correspondence.confidentiality')" :options="enum_options(\App\Models\Correspondence::CONFIDENTIALITY)" :value="$item->confidentiality" :required="true" :placeholder="false" :help="__('correspondence.confidentiality_help')" />
                <x-field.select name="in_reply_to_id" :label="__('correspondence.in_reply_to')" :options="$replyOptions" :value="$item->in_reply_to_id" :placeholder="__('common.none')" />
                <x-field.select name="project_id" :label="__('common.project')" :options="$projects" :value="$item->project_id" :placeholder="__('common.none')" />
                <x-field.textarea name="summary" :label="__('correspondence.summary')" :value="$item->summary" class="span-2" />
                @unless($editing)
                    <x-field.file name="file" :label="__('correspondence.scanned_letter')" />
                @endunless
            </div>
            <div class="form-actions">
                <a href="{{ $editing ? route('correspondence.show', $item) : route('correspondence.index') }}" class="btn">{{ __('common.cancel') }}</a>
                <button type="submit" class="btn btn--primary">{{ $editing ? __('common.save_changes') : __('correspondence.register') }}</button>
            </div>
        </form>
    </x-card>
</x-layout>
