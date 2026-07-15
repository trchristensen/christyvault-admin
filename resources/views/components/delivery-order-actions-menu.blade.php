@props([
    'order',
])

@php
    $deliveryTrip = $order->trip && ! $order->trip->trashed() ? $order->trip : null;
    $needsStopOrderConfirmation = $deliveryTrip
        && $deliveryTrip->deliveryStopCount() > 1
        && ! $deliveryTrip->isStopOrderConfirmed();
@endphp

<x-filament::dropdown placement="bottom-end" width="xs" shift teleport>
    <x-slot name="trigger">
        <x-filament::icon-button
            icon="heroicon-m-ellipsis-vertical"
            color="gray"
            size="sm"
            label="Order actions"
        />
    </x-slot>

    <x-filament::dropdown.list>
        <x-filament::dropdown.list.item
            icon="heroicon-o-camera"
            :x-on:click="'close(); $wire.mountAction(\'uploadDeliveryPhotos\', { order: '.((int) $order->getKey()).' })'"
        >
            Upload photos
        </x-filament::dropdown.list.item>

        @if ($deliveryTrip && auth()->user()?->can('manage delivery trip dispatch'))
            <x-filament::dropdown.list.item
                icon="heroicon-o-user-circle"
                :x-on:click="'close(); $wire.mountAction(\'manageDeliveryTripDispatch\', { trip: '.((int) $deliveryTrip->getKey()).' })'"
            >
                {{ $needsStopOrderConfirmation ? 'Confirm stop order' : 'Manage delivery' }}
            </x-filament::dropdown.list.item>
        @endif
    </x-filament::dropdown.list>
</x-filament::dropdown>
