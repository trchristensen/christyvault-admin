<div class="space-y-4">
    @forelse ($reports as $report)
        <section class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="font-semibold text-gray-950 dark:text-white">{{ $report->report_type_label }}</h3>
                    <p class="text-sm text-gray-500">{{ $report->completed_at?->format('M j, Y g:i A') }} · {{ $report->driver_name }} · {{ $report->trip?->trip_number }}</p>
                </div>
                <span class="text-sm font-semibold {{ $report->safe_to_operate ? 'text-success-600' : 'text-danger-600' }}">
                    {{ $report->safe_to_operate ? 'No issues reported' : 'Issue reported' }}
                </span>
            </div>

            <div class="mt-3 flex flex-wrap gap-2 text-xs text-gray-600 dark:text-gray-300">
                @foreach ($report->assets as $asset)
                    <span class="rounded-md bg-gray-100 px-2 py-1 dark:bg-white/5">{{ str($asset->pivot->role)->headline() }}: {{ $asset->display_name }}</span>
                @endforeach
            </div>

            @if ($report->inspectionDefects->isNotEmpty())
                <div class="mt-4 space-y-2">
                    @foreach ($report->inspectionDefects as $defect)
                        <div class="rounded-lg {{ $defect->isOpen() ? 'bg-danger-50 dark:bg-danger-950/30' : 'bg-success-50 dark:bg-success-950/20' }} p-3 text-sm">
                            <div class="font-semibold">{{ $defect->component_label }} · {{ $defect->asset?->display_name ?? 'Equipment not identified' }}</div>
                            <div class="mt-1">{{ $defect->description }}</div>
                            <div class="mt-2 text-xs font-medium">
                                @if ($defect->isOpen())
                                    @if ($defect->operating_decision === \App\Models\TripPreTripInspectionDefect::OPERATING_DECISION_OUT_OF_SERVICE || $defect->driver_assessment === \App\Models\TripPreTripInspectionDefect::DRIVER_ASSESSMENT_STOP)
                                        Out of service — repair or management clearance required
                                    @else
                                        Manager review pending — driver reported an observation, not a diagnosis
                                    @endif
                                    @if (auth()->user()?->can('manage delivery trip dispatch'))
                                        <div class="mt-3">
                                            <x-filament::button
                                                size="sm"
                                                color="warning"
                                                icon="heroicon-o-clipboard-document-check"
                                                wire:click="mountAction('reviewTripInspectionIssue', { issue: {{ (int) $defect->getKey() }} })"
                                            >
                                                Record operating decision
                                            </x-filament::button>
                                        </div>
                                    @endif
                                @else
                                    {{ str($defect->status)->replace('_', ' ')->headline() }} {{ $defect->resolved_at?->format('M j, Y') }}
                                    @if ($defect->resolvedBy) · {{ $defect->resolvedBy->name }} @endif
                                    @if ($defect->review_notes) · {{ $defect->review_notes }} @endif
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @empty
        <p class="text-sm text-gray-500">No previous inspection reports were found.</p>
    @endforelse
</div>
