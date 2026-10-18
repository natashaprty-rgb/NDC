<x-field.select name="type" :label="__('common.type')" :options="enum_options(\App\Models\ProjectRisk::TYPES)" :value="$risk->type" :placeholder="false" />
<x-field.input name="title" :label="__('common.title')" :value="$risk->title" :required="true" maxlength="200" />
<x-field.textarea name="description" :label="__('common.description')" :value="$risk->description" rows="2" />
<x-field.select name="likelihood" :label="__('projects.likelihood')" :options="enum_options(\App\Models\ProjectRisk::LEVELS)" :value="$risk->likelihood" :placeholder="false" />
<x-field.select name="impact" :label="__('projects.impact')" :options="enum_options(\App\Models\ProjectRisk::LEVELS)" :value="$risk->impact" :placeholder="false" />
<x-field.select name="status" :label="__('common.status')" :options="enum_options(\App\Models\ProjectRisk::STATUSES)" :value="$risk->status" :placeholder="false" />
<x-field.select name="owner_id" :label="__('projects.risk_owner')" :options="$users" :value="$risk->owner_id" :placeholder="__('common.not_set')" />
<x-field.textarea name="corrective_action" :label="__('projects.corrective_action')" :value="$risk->corrective_action" rows="2" />
<x-field.input name="due_date" type="date" :label="__('projects.due_date')" :value="$risk->due_date" />
