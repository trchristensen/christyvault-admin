@props([
    'trip',
])

@php
    $inspection = $trip->currentPreTripInspection();
    $dailyReport = $trip->currentDailyVehicleReport();
    $isAssignedDriver = $trip->isAssignedDriver(auth()->user());
    $reusableInspection = $isAssignedDriver && ! $inspection ? $trip->reusablePreTripInspection() : null;
    $inspectionHasOpenIssues = $inspection?->hasOpenIssues() ?? false;
    $inspectionRequiresStop = $inspection?->requiresImmediateStop() ?? false;
    $dailyHasOpenIssues = $dailyReport?->hasOpenIssues() ?? false;
    $dailyRequiresStop = $dailyReport?->requiresImmediateStop() ?? false;
    $canViewInspection = $inspection && (
        $isAssignedDriver
        || (auth()->user()?->can('manage delivery trip dispatch') ?? false)
    );
@endphp

@if ($canViewInspection)
    <button
        type="button"
        class="delivery-trip-pre-trip-action {{ ($inspection->safe_to_operate || ! $inspectionHasOpenIssues) ? 'delivery-trip-pre-trip-action-complete' : ($inspectionRequiresStop ? 'delivery-trip-pre-trip-action-defect' : 'delivery-trip-pre-trip-action-review') }}"
        wire:click.stop="mountAction('viewTripPreTripInspection', { inspection: {{ (int) $inspection->getKey() }} })"
        wire:loading.attr="disabled"
        wire:target="mountAction"
    >
        <span class="delivery-trip-pre-trip-action-icon">
            @if ($inspection->safe_to_operate || ! $inspectionHasOpenIssues)
                <x-heroicon-o-clipboard-document-check />
            @else
                <x-heroicon-o-exclamation-triangle />
            @endif
        </span>
        <span class="delivery-trip-pre-trip-action-copy">
            <span class="delivery-trip-pre-trip-action-label">
                @if ($inspection->safe_to_operate)
                    Pre-trip inspection complete
                @elseif (! $inspectionHasOpenIssues)
                    Pre-trip issue reviewed
                @elseif ($inspectionRequiresStop)
                    Immediate safety concern reported
                @else
                    Pre-trip issue needs review
                @endif
            </span>
            <span class="delivery-trip-pre-trip-action-hint">View inspection record</span>
        </span>
        <x-heroicon-m-chevron-right class="delivery-trip-pre-trip-action-arrow" />
    </button>
@elseif ($isAssignedDriver)
    @if ($reusableInspection)
        <button
            type="button"
            class="delivery-trip-pre-trip-action delivery-trip-pre-trip-action-complete"
            wire:click.stop="mountAction('reuseSameDayTripPreTripInspection', { trip: {{ (int) $trip->getKey() }}, inspection: {{ (int) $reusableInspection->getKey() }} })"
            wire:loading.attr="disabled"
            wire:target="mountAction"
        >
            <span class="delivery-trip-pre-trip-action-icon"><x-heroicon-o-arrow-path /></span>
            <span class="delivery-trip-pre-trip-action-copy">
                <span class="delivery-trip-pre-trip-action-label">Confirm today’s inspection</span>
                <span class="delivery-trip-pre-trip-action-hint">
                    Same equipment: {{ collect($reusableInspection->equipment_snapshot)->filter()->pluck('asset_tag')->map(fn ($tag) => '#'.$tag)->join(' · ') }}
                </span>
            </span>
            <x-heroicon-m-chevron-right class="delivery-trip-pre-trip-action-arrow" />
        </button>
    @endif

    <button
        type="button"
        class="delivery-trip-pre-trip-action"
        wire:click.stop="mountAction('completeTripPreTripInspection', { trip: {{ (int) $trip->getKey() }} })"
        wire:loading.attr="disabled"
        wire:target="mountAction"
    >
        <span class="delivery-trip-pre-trip-action-icon">
            <x-heroicon-o-clipboard-document-list />
        </span>
        <span class="delivery-trip-pre-trip-action-copy">
            <span class="delivery-trip-pre-trip-action-label">{{ $reusableInspection ? 'Inspect different equipment or a new issue' : 'Pre-trip inspection' }}</span>
            <span class="delivery-trip-pre-trip-action-hint">{{ $reusableInspection ? 'Start a fresh inspection instead' : 'Tap to begin · truck, load, and equipment' }}</span>
        </span>
        <x-heroicon-m-chevron-right class="delivery-trip-pre-trip-action-arrow" />
    </button>
@endif

@if ($isAssignedDriver && $inspection && ! $trip->scheduled_date?->isFuture())
    <button
        type="button"
        class="delivery-trip-pre-trip-action {{ $dailyReport ? (($dailyReport->safe_to_operate || ! $dailyHasOpenIssues) ? 'delivery-trip-pre-trip-action-complete' : ($dailyRequiresStop ? 'delivery-trip-pre-trip-action-defect' : 'delivery-trip-pre-trip-action-review')) : '' }}"
        wire:click.stop="mountAction('{{ $dailyReport ? 'viewTripPreTripInspection' : 'completeTripDailyVehicleReport' }}', { {{ $dailyReport ? 'inspection' : 'trip' }}: {{ (int) ($dailyReport?->getKey() ?? $trip->getKey()) }} })"
        wire:loading.attr="disabled"
        wire:target="mountAction"
    >
        <span class="delivery-trip-pre-trip-action-icon">
            @if ($dailyReport && ($dailyReport->safe_to_operate || ! $dailyHasOpenIssues))
                <x-heroicon-o-document-check />
            @elseif ($dailyReport)
                <x-heroicon-o-exclamation-triangle />
            @else
                <x-heroicon-o-document-text />
            @endif
        </span>
        <span class="delivery-trip-pre-trip-action-copy">
            <span class="delivery-trip-pre-trip-action-label">
                @if ($dailyReport)
                    @if ($dailyReport->safe_to_operate)
                        Daily vehicle report complete
                    @elseif (! $dailyHasOpenIssues)
                        Daily vehicle issue reviewed
                    @elseif ($dailyRequiresStop)
                        Daily safety concern reported
                    @else
                        Daily vehicle issue needs review
                    @endif
                @else
                    End-of-day vehicle report
                @endif
            </span>
            <span class="delivery-trip-pre-trip-action-hint">{{ $dailyReport ? 'View signed daily report' : 'Complete after your final use today' }}</span>
        </span>
        <x-heroicon-m-chevron-right class="delivery-trip-pre-trip-action-arrow" />
    </button>
@endif

@if (($isAssignedDriver || (auth()->user()?->can('manage delivery trip dispatch') ?? false)) && $trip->preTripInspections->isNotEmpty())
    <button
        type="button"
        class="delivery-trip-pre-trip-action"
        wire:click.stop="mountAction('viewTripVehicleInspectionHistory', { trip: {{ (int) $trip->getKey() }} })"
        wire:loading.attr="disabled"
        wire:target="mountAction"
    >
        <span class="delivery-trip-pre-trip-action-icon"><x-heroicon-o-clock /></span>
        <span class="delivery-trip-pre-trip-action-copy">
            <span class="delivery-trip-pre-trip-action-label">Previous inspection reports</span>
            <span class="delivery-trip-pre-trip-action-hint">Review vehicle history and repair certifications</span>
        </span>
        <x-heroicon-m-chevron-right class="delivery-trip-pre-trip-action-arrow" />
    </button>
@endif
