@props([
    'trip',
])

@php
    $isMultiStop = $trip->deliveryStopCount() > 1;
    $label = $isMultiStop ? 'Edit delivery trip' : 'Edit delivery';
@endphp

@if (auth()->user()?->can('manage delivery trip dispatch'))
    <x-filament::icon-button
        type="button"
        icon="heroicon-o-pencil-square"
        color="primary"
        size="sm"
        :label="$label"
        :tooltip="$label"
        class="delivery-trip-dispatch-trigger"
        x-data
        :x-on:click="'$wire.mountAction(\'manageDeliveryTripDispatch\', { trip: '.((int) $trip->getKey()).' })'"
    />
@endif
