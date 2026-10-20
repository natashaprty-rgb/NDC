{{-- Register filters, shared by the list and the printable register. --}}
<x-filters :action="$action ?? null">
    <x-search-field :placeholder="__('Title, reference or description')" />
    <x-filter-select name="category" :label="__('Document type')" :options="$categories" />
    <x-filter-select name="department" :label="__('Department')" :options="$departments" />
    <x-filter-select name="project" :label="__('Project')" :options="$projects" />
    <x-filter-select name="confidentiality" :label="__('Confidentiality')" :options="\App\Support\Status::options('confidentiality')" />
    <x-filter-select name="status" :label="__('Status')" :options="\App\Support\Status::options('document')" />
    <x-filter-select name="expiring" :label="__('Dates')" :options="[
        'soon' => __('Expiring within :days days', ['days' => $expiryDays]),
        'expired' => __('Expired'),
        'review' => __('Review due'),
    ]" />
    <x-filter-select name="archived" :label="__('Archive status')" :options="['1' => __('Archived documents only')]" />
</x-filters>
