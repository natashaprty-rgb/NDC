<x-layout :title="__('budgets.title')" :subtitle="__('budgets.monitoring_note')" :breadcrumbs="[__('budgets.title')]">
    <x-slot:actions>
        @can('create', \App\Models\Budget::class)
            <a href="{{ route('budgets.create') }}" class="btn btn--primary"><x-icon name="plus" width="16" height="16" /> {{ __('budgets.new') }}</a>
        @endcan
    </x-slot:actions>

    <section class="card">
        <x-filter-bar :action="route('budgets.index')">
            <x-field.input name="q" :label="__('common.search')" :value="request('q')" class="field--search" :placeholder="__('budgets.search_hint')" />
            <x-field.select name="fiscal_year" :label="__('budgets.fiscal_year')" :options="$years" :value="request('fiscal_year')" :placeholder="__('common.all')" />
            <x-field.select name="status" :label="__('common.status')" :options="enum_options(\App\Models\Budget::STATUSES)" :value="request('status')" :placeholder="__('common.all')" />
            <x-field.select name="project_id" :label="__('common.project')" :options="$projects" :value="request('project_id')" :placeholder="__('common.all')" />
            <x-field.select name="department_id" :label="__('common.department')" :options="$departments" :value="request('department_id')" :placeholder="__('common.all')" />
            <x-field.select name="funding_source_id" :label="__('budgets.funding_source')" :options="$fundingSources" :value="request('funding_source_id')" :placeholder="__('common.all')" />
        </x-filter-bar>

        @if($budgets->isEmpty())
            <x-empty :message="request()->hasAny(['q', 'fiscal_year', 'status', 'project_id', 'department_id', 'funding_source_id']) ? __('common.no_records') : __('budgets.empty')" icon="wallet" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr>
                        <th>{{ __('common.reference') }}</th><th>{{ __('common.title') }}</th><th>{{ __('budgets.fiscal_year') }}</th>
                        <th>{{ __('budgets.owner') }}</th><th class="num">{{ __('budgets.allocated') }}</th><th class="num">{{ __('budgets.approved_spend') }}</th>
                        <th class="num">{{ __('budgets.remaining') }}</th><th>{{ __('budgets.used') }}</th><th>{{ __('common.status') }}</th>
                    </tr></thead>
                    <tbody>
                    @foreach($budgets as $budget)
                        @php
                            $allocatedCents = \App\Support\Money::toCents((string) $budget->allocated_total);
                            $approvedCents = \App\Support\Money::toCents((string) $budget->approved_total);
                        @endphp
                        <tr>
                            <td class="nowrap">{{ $budget->reference }}</td>
                            <td><a href="{{ route('budgets.show', $budget) }}">{{ $budget->title }}</a></td>
                            <td>{{ $budget->fiscal_year }}</td>
                            <td class="small">{{ collect([$budget->project?->name, $budget->department?->displayName()])->filter()->join(' / ') ?: '—' }}</td>
                            <td class="num">{{ money($allocatedCents, $budget->currency) }}</td>
                            <td class="num">{{ money($approvedCents, $budget->currency) }}</td>
                            <td class="num">{{ money($allocatedCents - $approvedCents, $budget->currency) }}</td>
                            <td style="min-width:130px"><x-progress :value="\App\Support\Money::percent($approvedCents, $allocatedCents)" /></td>
                            <td><x-badge :value="$budget->status" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $budgets->links() }}
        @endif
    </section>
</x-layout>
