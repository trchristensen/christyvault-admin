@props([
    'order',
    'isDeliveryTrip' => false,
    'stopOrderConfirmed' => true,
    'stopCount' => null,
])

@php
    $statusEnum = \App\Enums\OrderStatus::tryFrom($order->status);
    $statusLabel = $statusEnum?->label() ?? \Illuminate\Support\Str::headline((string) $order->status);
    $statusClass = preg_replace('/[^a-z0-9]+/', '_', strtolower((string) $order->status));
    $activeTrip = $order->trip && ! $order->trip->trashed() ? $order->trip : null;
    $stopNumber = $order->activeTripStop?->sequence ?? $order->stop_number;
    $deliveryTime = $order->delivery_time ?? $order->scheduled_at;
@endphp

<div class="delivery-order-summary">
    <div class="delivery-order-topline">
        <div class="delivery-order-identity">
            <span>Order #{{ $order->id }}</span>

            @if (! $isDeliveryTrip && $order->driver)
                <span class="delivery-order-identity-separator" aria-hidden="true">·</span>
                <span class="delivery-order-driver">{{ $order->driver->name }}</span>
            @endif

            @if ($deliveryTime)
                <span class="delivery-order-identity-separator" aria-hidden="true">·</span>
                <span class="delivery-order-time">{{ \Carbon\Carbon::parse($deliveryTime)->format('g:i A') }}</span>
            @endif

            @if ($isDeliveryTrip && $stopOrderConfirmed && $stopNumber)
                <span class="delivery-trip-stop-label">
                    <span class="delivery-trip-stop-number">{{ $stopNumber }}</span>
                    Stop {{ $stopNumber }} of {{ $stopCount }}
                </span>
            @endif
        </div>

        <div class="delivery-order-actions">
            @if (! $isDeliveryTrip && $activeTrip)
                <x-delivery-trip-dispatch-button :trip="$activeTrip" />
            @endif

            <x-delivery-order-photo-button :order="$order" />
        </div>
    </div>

    <div class="delivery-order-destination">
        {{ $order->location?->name ?? 'Customer' }}
    </div>

    @if ($order->location?->full_address)
        <a
            href="https://www.google.com/maps/search/?api=1&query={{ urlencode($order->location->full_address) }}"
            target="_blank"
            rel="noopener noreferrer"
            class="delivery-order-address"
        >
            <span>{{ $order->location->full_address }}</span>
            <x-heroicon-o-map-pin />
        </a>
    @endif

    <div class="delivery-order-statuses">
        <span class="order-status-badge order-status-{{ $statusClass }}">
            {{ $statusLabel }}
        </span>

        <span
            class="delivery-tag-badge {{ $order->is_printed ? 'delivery-tag-printed' : 'delivery-tag-not-printed' }}"
            title="{{ $order->is_printed ? 'Delivery tag has been printed' : 'Delivery tag has not been printed yet' }}"
        >
            @if ($order->is_printed)
                <x-heroicon-o-printer />
                <span>Tag printed</span>
            @else
                <x-heroicon-o-exclamation-triangle />
                <span>Tag not printed</span>
            @endif
        </span>

        @if ($order->delivery_photos_count > 0)
            <span
                class="delivery-photo-badge"
                title="{{ $order->delivery_photos_count }} {{ \Illuminate\Support\Str::plural('delivery photo', $order->delivery_photos_count) }} attached"
            >
                <x-heroicon-o-camera />
                <span>{{ $order->delivery_photos_count }} {{ \Illuminate\Support\Str::plural('photo', $order->delivery_photos_count) }}</span>
            </span>
        @endif
    </div>
</div>
