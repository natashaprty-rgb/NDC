<x-layouts.app :title="__('Edit action point')">
    <x-page-header :title="__('Edit action point')" :subtitle="$item->meeting->title"
        :breadcrumbs="[__('Meetings & Calendar') => route('meetings.index'), $item->meeting->title => route('meetings.show', $item->meeting)]" />

    <form method="POST" action="{{ route('meetings.action-items.update', $item) }}" class="card form">
        @csrf @method('PUT')
        <div class="card__body">
            <div class="form-grid">
                <x-form.select name="type" :label="__('Type')" :options="\App\Support\Status::options('action_item_type')" :value="$item->type" required :placeholder="false" />
                <x-form.select name="status" :label="__('Status')" :options="\App\Support\Status::options('action_item')" :value="$item->status" required :placeholder="false" />
                <x-form.textarea name="description" :label="__('Description')" :value="$item->description" rows="3" required class="span-2" maxlength="5000" />
                <x-form.select name="assigned_to" :label="__('Assigned to')" :options="$users" :value="$item->assigned_to" />
                <x-form.input name="due_date" type="date" :label="__('Due date')" :value="$item->due_date" />
                <x-form.textarea name="notes" :label="__('Notes')" :value="$item->notes" rows="2" class="span-2" maxlength="5000" />
            </div>
        </div>
        <div class="card__foot form-actions">
            <button class="btn btn-primary" type="submit">{{ __('Save') }}</button>
            <a href="{{ route('meetings.show', $item->meeting) }}#action-items" class="btn btn-ghost">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
