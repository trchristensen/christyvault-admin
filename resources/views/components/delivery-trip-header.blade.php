@props([
    'trip',
    'stopCount',
])

@php
    $deliveryPlanNeedsReview = ! $trip->driver_id || ! $trip->isStopOrderConfirmed();
@endphp

<div class="delivery-trip-group-header">
    <div class="delivery-trip-group-heading-row">
        <div class="delivery-trip-group-label">Split load · {{ $stopCount }} stops</div>
        <x-delivery-trip-dispatch-button :trip="$trip" />
    </div>
    <div class="delivery-trip-group-meta">
        {{ $trip->trip_number }} · {{ $trip->driver?->name ?? 'Driver unassigned' }}
    </div>

    @if ($deliveryPlanNeedsReview)
        <div class="delivery-trip-unconfirmed-badge">
            <x-heroicon-o-exclamation-triangle />
            <span>Delivery plan needs review</span>
        </div>
    @endif
</div>
