@props([
    'trip',
])

@if ($trip->loadSummaryIsVisibleTo(auth()->user()))
    <x-filament::icon-button
        type="button"
        icon="heroicon-o-cube-transparent"
        color="info"
        size="sm"
        label="Load summary"
        tooltip="Load summary"
        class="delivery-trip-load-summary-trigger"
        x-data
        :x-on:click="'$wire.mountAction(\'viewDeliveryTripLoadSummary\', { trip: '.((int) $trip->getKey()).' })'"
    />
@endif
