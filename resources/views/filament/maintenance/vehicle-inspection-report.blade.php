@php($equipment = collect($report->equipment_snapshot ?? [])->filter())

<div class="space-y-5">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/5"><div class="text-xs uppercase text-gray-500">Driver</div><div class="mt-1 font-semibold">{{ $report->driver_name }}</div></div>
        <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/5"><div class="text-xs uppercase text-gray-500">Submitted</div><div class="mt-1 font-semibold">{{ $report->completed_at?->format('M j, Y g:i A') }}</div></div>
        <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/5"><div class="text-xs uppercase text-gray-500">Report</div><div class="mt-1 font-semibold">{{ $report->report_type_label }}</div></div>
        <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/5"><div class="text-xs uppercase text-gray-500">Result</div><div class="mt-1 font-semibold {{ $report->safe_to_operate ? 'text-success-600' : 'text-warning-600' }}">{{ $report->safe_to_operate ? 'No issues' : 'Issue reported' }}</div></div>
    </div>

    <div class="grid gap-2 sm:grid-cols-3">
        @foreach ($equipment as $role => $asset)
            <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10"><div class="text-xs uppercase text-gray-500">{{ str($role)->headline() }}</div><div class="mt-1 text-sm font-medium">{{ data_get($asset, 'asset_tag') }} — {{ data_get($asset, 'name') }}</div></div>
        @endforeach
    </div>

    @if ($report->inspectionDefects->isNotEmpty())
        <div class="space-y-2">
            @foreach ($report->inspectionDefects as $defect)
                <div class="rounded-xl border p-4 {{ $defect->isOpen() ? 'border-danger-200 bg-danger-50 dark:border-danger-900 dark:bg-danger-950/30' : 'border-success-200 bg-success-50 dark:border-success-900 dark:bg-success-950/20' }}">
                    <div class="font-semibold">{{ $defect->component_label }} · {{ $defect->asset?->display_name }}</div>
                    <div class="mt-1 text-sm">{{ $defect->description }}</div>
                    <div class="mt-2 text-xs font-medium">
                        Driver: {{ $defect->driver_assessment === \App\Models\TripPreTripInspectionDefect::DRIVER_ASSESSMENT_STOP ? 'Immediate safety concern' : 'Needs manager review' }}
                        · Status: {{ str($defect->status)->replace('_', ' ')->headline() }}@if ($defect->workOrder) · {{ $defect->workOrder->number }}@endif
                    </div>
                    @if ($defect->resolution_certification)<div class="mt-2 text-sm">{{ $defect->resolution_certification }}</div>@endif
                    @if ($defect->review_notes)<div class="mt-1 text-sm">{{ $defect->review_notes }}</div>@endif
                </div>
            @endforeach
        </div>
    @endif

    <div class="rounded-xl bg-gray-50 p-4 text-sm dark:bg-white/5">
        <div class="font-semibold">Driver certification</div>
        <p class="mt-1">{{ $report->certification_text }}</p>
        <p class="mt-2 text-xs text-gray-500">Version: {{ $report->checklist_version }}</p>
    </div>
</div>
