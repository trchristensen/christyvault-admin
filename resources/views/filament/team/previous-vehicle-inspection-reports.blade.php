<div class="space-y-3 rounded-xl border border-gray-200 p-4 dark:border-white/10">
    <div>
        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Previous reports for selected equipment</h3>
        <p class="mt-1 text-xs text-gray-500">Review open issues and the latest signed reports before driving.</p>
    </div>

    @if ($openDefects->isNotEmpty())
        @php($hasStopIssue = $openDefects->contains(fn ($issue) => $issue->driver_assessment === \App\Models\TripPreTripInspectionDefect::DRIVER_ASSESSMENT_STOP || $issue->operating_decision === \App\Models\TripPreTripInspectionDefect::OPERATING_DECISION_OUT_OF_SERVICE))
        <div class="rounded-lg border {{ $hasStopIssue ? 'border-danger-200 bg-danger-50 dark:border-danger-900 dark:bg-danger-950/30' : 'border-warning-200 bg-warning-50 dark:border-warning-900 dark:bg-warning-950/30' }} p-3">
            <div class="text-sm font-semibold {{ $hasStopIssue ? 'text-danger-800 dark:text-danger-200' : 'text-warning-800 dark:text-warning-200' }}">
                {{ $hasStopIssue ? 'Do not operate — immediate safety concern' : 'Manager review needed before dispatch' }}
            </div>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm {{ $hasStopIssue ? 'text-danger-700 dark:text-danger-300' : 'text-warning-700 dark:text-warning-300' }}">
                @foreach ($openDefects as $defect)
                    <li>{{ $defect->asset?->display_name }}: {{ $defect->component_label }} — {{ $defect->description }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @forelse ($reports as $report)
        <div class="flex flex-col gap-1 border-t border-gray-100 pt-3 text-sm first:border-0 first:pt-0 dark:border-white/5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <span class="font-medium text-gray-950 dark:text-white">{{ $report->report_type_label }}</span>
                <span class="text-gray-500"> · {{ $report->driver_name }}</span>
            </div>
            <div class="text-xs {{ $report->safe_to_operate ? 'text-success-600' : 'text-danger-600' }}">
                {{ $report->completed_at?->format('M j, Y g:i A') }} · {{ $report->safe_to_operate ? 'No issues' : 'Issue reported' }}
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-500">No previous reports were found for the selected equipment.</p>
    @endforelse
</div>
