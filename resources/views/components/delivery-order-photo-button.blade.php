@props([
    'order',
])

<x-filament::icon-button
    type="button"
    icon="heroicon-o-camera"
    color="gray"
    size="sm"
    label="Upload delivery photos"
    tooltip="Upload delivery photos"
    class="delivery-order-photo-trigger"
    x-data
    :x-on:click="'$wire.mountAction(\'uploadDeliveryPhotos\', { order: '.((int) $order->getKey()).' })'"
/>
