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

    @if ($report->report_type === \App\Models\TripPreTripInspection::TYPE_EQUIPMENT_CARE)
        @php
            $completedTasks = collect(data_get($report->responses, 'completed_tasks', []));
            $tireReadings = collect(data_get($report->responses, 'tire_readings', []));
            $careNotes = data_get($report->responses, 'care_notes');
            $meterReading = data_get($report->responses, 'meter_reading');
        @endphp

        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
            <div class="font-semibold">Optional care completed</div>

            @if ($completedTasks->isEmpty())
                <p class="mt-2 text-sm text-gray-500">No care task was selected; this report records an observed issue.</p>
            @else
                <ul class="mt-2 space-y-2 text-sm">
                    @foreach ($completedTasks as $task)
                        <li class="flex items-start gap-2">
                            <x-filament::icon icon="heroicon-m-check-circle" class="mt-0.5 size-4 shrink-0 text-success-600" />
                            <span>{{ data_get($task, 'label') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if (filled($meterReading))
                <p class="mt-3 text-sm"><span class="font-medium">Meter:</span> {{ number_format((float) $meterReading, 1) }}</p>
            @endif

            @if ($tireReadings->isNotEmpty())
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs uppercase text-gray-500">
                            <tr><th class="pb-2 pr-4">Tire position</th><th class="pb-2 pr-4">Measured</th><th class="pb-2">Target</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @foreach ($tireReadings as $reading)
                                <tr>
                                    <td class="py-2 pr-4">{{ data_get($reading, 'position') }}</td>
                                    <td class="py-2 pr-4">{{ data_get($reading, 'psi') }} PSI</td>
                                    <td class="py-2">{{ filled(data_get($reading, 'target_psi')) ? data_get($reading, 'target_psi').' PSI' : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if (filled($careNotes))
                <div class="mt-4 rounded-lg bg-gray-50 p-3 text-sm dark:bg-white/5">
                    <div class="font-medium">Notes</div>
                    <p class="mt-1 whitespace-pre-line">{{ $careNotes }}</p>
                </div>
            @endif
        </div>
    @endif

    <div class="rounded-xl bg-gray-50 p-4 text-sm dark:bg-white/5">
        <div class="font-semibold">Driver certification</div>
        <p class="mt-1">{{ $report->certification_text }}</p>
        <p class="mt-2 text-xs text-gray-500">Version: {{ $report->checklist_version }}</p>
    </div>
</div>
