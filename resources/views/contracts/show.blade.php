<x-layout :title="$contract->title" :subtitle="$contract->reference"
          :breadcrumbs="[__('suppliers.title') => route('suppliers.index'), $contract->supplier->name => route('suppliers.show', $contract->supplier), $contract->reference]">
    <x-slot:actions>
        @can('update', $contract)
            <a href="{{ route('contracts.edit', $contract) }}" class="btn"><x-icon name="edit" width="16" height="16" /> {{ __('common.edit') }}</a>
        @endcan
        @can('delete', $contract)
            <x-delete-button :action="route('contracts.destroy', $contract)" :label="__('common.archive')" :message="__('common.confirm_archive')" class="btn btn--danger" />
        @endcan
    </x-slot:actions>
    <div class="layout-main-side">
        <div>
            <x-card :title="__('common.details')">
                <dl class="dl">
                    <dt>{{ __('suppliers.supplier') }}</dt><dd><a href="{{ route('suppliers.show', $contract->supplier) }}">{{ $contract->supplier->name }}</a></dd>
                    <dt>{{ __('common.status') }}</dt><dd><x-badge :value="$contract->status" />
                        @if($contract->end_date && $contract->end_date->lt(today()) && $contract->status === 'active') <x-badge value="expired" /> @endif</dd>
                    <dt>{{ __('suppliers.start_date') }}</dt><dd>{{ fmt_date($contract->start_date) }}</dd>
                    <dt>{{ __('suppliers.end_date') }}</dt><dd>{{ fmt_date($contract->end_date) }}</dd>
                    <dt>{{ __('suppliers.contract_value') }}</dt><dd>{{ $contract->contract_value !== null ? money($contract->contract_value, $contract->currency) : '—' }}</dd>
                </dl>
                @if($contract->scope)<h3 class="mt-2">{{ __('suppliers.scope') }}</h3><p class="prose">{{ $contract->scope }}</p>@endif
                @if($contract->notes)<h3 class="mt-2">{{ __('common.notes') }}</h3><p class="prose mb-0">{{ $contract->notes }}</p>@endif
            </x-card>
            <x-attachments :model="$contract" :categories="['contract', 'general', 'report']" />
        </div>
        <div><x-history :model="$contract" /></div>
    </div>
</x-layout>
