@php
    $equipment = collect($inspection->equipment_snapshot ?? [])->filter();
    $responseLabels = [
        \App\Support\TripPreTripChecklist::RESPONSE_OK => 'Looks good',
        \App\Support\TripPreTripChecklist::RESPONSE_DEFECT => 'Issue reported',
        \App\Support\TripPreTripChecklist::RESPONSE_NOT_APPLICABLE => 'N/A',
    ];
    $hasOpenIssues = $inspection->hasOpenIssues();
    $requiresStop = $inspection->requiresImmediateStop();
@endphp

<div class="space-y-5">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/5">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Driver</div>
            <div class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $inspection->driver_name }}</div>
        </div>
        <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/5">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Completed</div>
            <div class="mt-1 font-semibold text-gray-950 dark:text-white">
                {{ $inspection->completed_at?->format('M j, Y g:i A') }}
            </div>
        </div>
        <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/5">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Result</div>
            <div class="mt-1 font-semibold {{ ($inspection->safe_to_operate || ! $hasOpenIssues) ? 'text-success-600 dark:text-success-400' : ($requiresStop ? 'text-danger-600 dark:text-danger-400' : 'text-warning-600 dark:text-warning-400') }}">
                @if ($inspection->safe_to_operate)
                    No issues reported
                @elseif (! $hasOpenIssues)
                    Issue reviewed
                @elseif ($requiresStop)
                    Immediate safety concern
                @else
                    Manager review needed
                @endif
            </div>
        </div>
        <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/5">
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Configuration</div>
            <div class="mt-1 font-semibold text-gray-950 dark:text-white">
                {{ data_get($inspection->vehicle_configuration_snapshot, 'name', 'Not recorded') }}
            </div>
        </div>
    </div>

    <div>
        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Equipment inspected</h3>
        <div class="mt-2 grid gap-2 sm:grid-cols-3">
            @foreach ($equipment as $type => $asset)
                <div class="rounded-lg border border-gray-200 px-3 py-2 dark:border-white/10">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ str($type)->headline() }}</div>
                    <div class="mt-1 text-sm font-medium text-gray-950 dark:text-white">
                        #{{ data_get($asset, 'asset_tag') }} · {{ data_get($asset, 'name') }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    @if (! $inspection->safe_to_operate)
        <div class="rounded-xl border {{ $requiresStop ? 'border-danger-200 bg-danger-50 text-danger-900 dark:border-danger-900 dark:bg-danger-950/30 dark:text-danger-100' : 'border-warning-200 bg-warning-50 text-warning-900 dark:border-warning-900 dark:bg-warning-950/30 dark:text-warning-100' }} p-4">
            <div class="font-semibold">Reported issues</div>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                @foreach ($inspection->defects ?? [] as $defect)
                    <li>{{ $defect }}</li>
                @endforeach
            </ul>
            @if ($inspection->defect_notes)
                <div class="mt-3 whitespace-pre-line text-sm">{{ $inspection->defect_notes }}</div>
            @endif
            @foreach ($inspection->inspectionDefects as $issue)
                <div class="mt-3 rounded-lg bg-white/60 p-3 text-sm dark:bg-black/10">
                    <div class="font-semibold">{{ $issue->asset?->display_name }} · {{ $issue->component_label }}</div>
                    @if ($issue->isOpen())
                        <div class="mt-1">{{ $issue->operating_decision === \App\Models\TripPreTripInspectionDefect::OPERATING_DECISION_OUT_OF_SERVICE || $issue->driver_assessment === \App\Models\TripPreTripInspectionDefect::DRIVER_ASSESSMENT_STOP ? 'Do not operate before management clearance.' : 'Awaiting manager review; the asset was marked restricted, not automatically taken out of service.' }}</div>
                        @if (auth()->user()?->can('manage delivery trip dispatch'))
                            <div class="mt-3">
                                <x-filament::button
                                    size="sm"
                                    color="warning"
                                    icon="heroicon-o-clipboard-document-check"
                                    wire:click="mountAction('reviewTripInspectionIssue', { issue: {{ (int) $issue->getKey() }} })"
                                >
                                    Record operating decision
                                </x-filament::button>
                            </div>
                        @endif
                    @else
                        <div class="mt-1">Reviewed {{ $issue->reviewed_at?->format('M j, Y g:i A') }}@if ($issue->reviewedBy) by {{ $issue->reviewedBy->name }}@endif: {{ $issue->resolution_certification }}</div>
                        @if ($issue->review_notes)<div class="mt-1">{{ $issue->review_notes }}</div>@endif
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <div class="space-y-4">
        @if ($inspection->report_type === \App\Models\TripPreTripInspection::TYPE_DAILY_REPORT)
            <section>
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Daily vehicle condition</h3>
                @if (array_key_exists('daily_condition', $inspection->responses ?? []))
                    <div class="mt-2 rounded-xl border border-gray-200 px-3 py-3 text-sm dark:border-white/10">
                        {{ data_get($inspection->responses, 'daily_condition') === 'no_defects'
                            ? 'The driver reported no issues.'
                            : 'The driver reported the issues listed above.' }}
                    </div>
                @else
                    <div class="mt-2 divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200 dark:divide-white/5 dark:border-white/10">
                        @foreach (\App\Support\TripDailyVehicleReportChecklist::items() as $key => $item)
                            @php($response = data_get($inspection->responses, $key))
                            <div class="flex items-start justify-between gap-4 px-3 py-2.5 text-sm">
                                <span class="text-gray-700 dark:text-gray-200">{{ $item['label'] }}</span>
                                <span class="shrink-0 font-semibold {{ $response === \App\Support\TripPreTripChecklist::RESPONSE_DEFECT ? 'text-warning-600 dark:text-warning-400' : 'text-gray-600 dark:text-gray-300' }}">
                                    {{ $responseLabels[$response] ?? 'Not recorded' }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        @else
        @foreach ($sections as $section)
            <section>
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $section['label'] }}</h3>
                <div class="mt-2 divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200 dark:divide-white/5 dark:border-white/10">
                    @foreach ($section['items'] as $key => $item)
                        @php($response = data_get($inspection->responses, $key))
                        <div class="flex items-start justify-between gap-4 px-3 py-2.5 text-sm">
                            <span class="text-gray-700 dark:text-gray-200">{{ $item['label'] }}</span>
                            <span class="shrink-0 font-semibold {{ $response === \App\Support\TripPreTripChecklist::RESPONSE_DEFECT ? 'text-danger-600 dark:text-danger-400' : 'text-gray-600 dark:text-gray-300' }}">
                                {{ $responseLabels[$response] ?? 'Not recorded' }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
        @endif
    </div>

    <div class="rounded-xl bg-gray-50 p-4 text-sm text-gray-600 dark:bg-white/5 dark:text-gray-300">
        <div class="font-semibold text-gray-950 dark:text-white">Driver certification</div>
        <p class="mt-1">{{ $inspection->certification_text }}</p>
        <p class="mt-2 text-xs">Report type: {{ $inspection->report_type_label }} · Version: {{ $inspection->checklist_version }}</p>
    </div>
</div>
