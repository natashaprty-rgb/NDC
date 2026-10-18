<x-field.select name="type" :label="__('common.type')" :options="$stakeholderTypes" :value="$stakeholder->type" :placeholder="false" />
<x-field.input name="name" :label="__('common.name')" :value="$stakeholder->name" :required="true" maxlength="150" />
<x-field.input name="organization" :label="__('projects.organization')" :value="$stakeholder->organization" maxlength="200" />
<x-field.select name="supplier_id" :label="__('projects.supplier')" :options="$suppliers" :value="$stakeholder->supplier_id" :placeholder="__('common.none')" />
<x-field.input name="role_description" :label="__('projects.role_description')" :value="$stakeholder->role_description" maxlength="255" />
<x-field.input name="email" type="email" :label="__('projects.email')" :value="$stakeholder->email" />
<x-field.input name="phone" :label="__('projects.phone')" :value="$stakeholder->phone" />
