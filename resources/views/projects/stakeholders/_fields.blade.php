{{-- Stakeholder fields, shared by the add form on the project page and the edit page. --}}
<div class="form-grid">
    <x-form.select name="type" :label="__('Type')" :options="\App\Support\Status::options('stakeholder_type')" :value="$stakeholder->type ?? 'stakeholder'" required :placeholder="false" id="sh_type" />
    <x-form.input name="name" :label="__('Name')" :value="$stakeholder->name" required maxlength="255" id="sh_name" />
    <x-form.input name="organization" :label="__('Organisation')" :value="$stakeholder->organization" maxlength="255" id="sh_organization" />
    <x-form.input name="role" :label="__('Role in the project')" :value="$stakeholder->role" maxlength="255" id="sh_role" />
    <x-form.input type="email" name="email" :label="__('Email')" :value="$stakeholder->email" maxlength="255" id="sh_email" />
    <x-form.input name="phone" :label="__('Phone')" :value="$stakeholder->phone" maxlength="50" id="sh_phone" />
    <x-form.textarea name="notes" :label="__('Notes')" :value="$stakeholder->notes" rows="2" class="span-2" id="sh_notes" />
</div>
