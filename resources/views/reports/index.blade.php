<x-layouts.app :title="__('Reports')">
    <x-page-header :title="__('Reports')" :subtitle="__('Every report is generated from current database records and respects your access rights.')" />
    @if (empty($reports))
        <div class="card"><x-empty icon="chart" :message="__('No reports are available to your role.')" /></div>
    @else
        <div class="grid grid-3">
            @foreach ($reports as $report)
                <a href="{{ route('reports.show', $report->key()) }}" class="card" style="display:block;color:inherit;text-decoration:none">
                    <div class="card__body">
                        <h2 style="color:var(--brand-700)">{{ $report->title() }}</h2>
                        <p class="muted small" style="margin:0">{{ $report->description() }}</p>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.app>
