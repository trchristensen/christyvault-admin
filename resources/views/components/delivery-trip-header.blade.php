@props([
    'trip',
    'stopCount',
])

<div class="delivery-trip-group-header">
    <div class="delivery-trip-group-heading-row">
        <div class="delivery-trip-group-label">Split load · {{ $stopCount }} stops</div>
        @if (! $trip->isStopOrderConfirmed())
            <span class="delivery-trip-unconfirmed-badge">Stop order not confirmed</span>
        @endif
    </div>
    <div class="delivery-trip-group-meta">
        {{ $trip->driver?->name ?? 'Driver unassigned' }} · {{ $trip->trip_number }}
    </div>
</div>
