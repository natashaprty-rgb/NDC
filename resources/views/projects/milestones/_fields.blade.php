{{-- Milestone fields, shared by the add form on the project page and the edit page. --}}
<div class="form-grid">
    <x-form.input name="title" :label="__('Milestone')" :value="$milestone->title" required maxlength="255" class="span-2" id="ms_title" />
    <x-form.input type="date" name="due_date" :label="__('Deadline')" :value="$milestone->due_date" id="ms_due_date" />
    <x-form.select name="status" :label="__('Status')" :options="\App\Support\Status::options('milestone')" :value="$milestone->status ?? 'pending'" required :placeholder="false" id="ms_status" />
    <x-form.textarea name="description" :label="__('Activities and description')" :value="$milestone->description" rows="3" id="ms_description" />
    <x-form.textarea name="deliverables" :label="__('Deliverables')" :value="$milestone->deliverables" rows="3" id="ms_deliverables" />
    <x-form.input type="number" name="sort_order" :label="__('Order')" :value="$milestone->sort_order" min="0" max="9999" id="ms_sort_order"
        :help="__('Leave blank to add at the end.')" />
</div>
