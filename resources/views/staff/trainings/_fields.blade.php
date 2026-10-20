{{-- Training record fields; $training may be a new, unsaved instance. --}}
<div class="form-grid">
    <x-form.input name="title" :label="__('Training or programme')" :value="$training->title" required maxlength="255" />
    <x-form.select name="type" :label="__('Type')" :options="collect(\App\Models\StaffTraining::TYPES)->map(fn ($l) => __($l))" :value="$training->type" />
    <x-form.input name="provider" :label="__('Provider or institution')" :value="$training->provider" maxlength="255" />
    <x-form.input name="certificate_number" :label="__('Certificate number')" :value="$training->certificate_number" maxlength="100" />
    <x-form.input name="start_date" type="date" :label="__('Start date')" :value="$training->start_date" />
    <x-form.input name="end_date" type="date" :label="__('End date')" :value="$training->end_date" />
    <x-form.textarea name="notes" :label="__('Notes')" :value="$training->notes" rows="2" maxlength="2000" class="span-2" />
</div>
