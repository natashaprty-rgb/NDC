{{-- Risk register fields, shared by the add form on the project page and the edit page. --}}
<div class="form-grid">
    <x-form.select name="type" :label="__('Type')" :options="\App\Support\Status::options('risk_type')" :value="$risk->type ?? 'risk'" required :placeholder="false" id="rk_type" />
    <x-form.select name="status" :label="__('Status')" :options="\App\Support\Status::options('risk')" :value="$risk->status ?? 'open'" required :placeholder="false" id="rk_status" />
    <x-form.input name="title" :label="__('Title')" :value="$risk->title" required maxlength="255" class="span-2" id="rk_title" />
    <x-form.textarea name="description" :label="__('Description')" :value="$risk->description" rows="3" class="span-2" id="rk_description" />
    <x-form.select name="likelihood" :label="__('Likelihood')" :options="\App\Support\Status::options('level')" :value="$risk->likelihood ?? 'medium'" required :placeholder="false" id="rk_likelihood" />
    <x-form.select name="impact" :label="__('Impact')" :options="\App\Support\Status::options('level')" :value="$risk->impact ?? 'medium'" required :placeholder="false" id="rk_impact" />
    <x-form.textarea name="corrective_action" :label="__('Corrective action')" :value="$risk->corrective_action" rows="3" class="span-2" id="rk_corrective_action" />
    <x-form.select name="owner_id" :label="__('Responsible person')" :options="$users" :value="$risk->owner_id" id="rk_owner_id" />
    <div></div>
    <x-form.input type="date" name="identified_on" :label="__('Identified on')" :value="$risk->identified_on" id="rk_identified_on" />
    <x-form.input type="date" name="resolved_on" :label="__('Resolved on')" :value="$risk->resolved_on" id="rk_resolved_on"
        :help="__('Filled in automatically when the entry is closed.')" />
</div>
