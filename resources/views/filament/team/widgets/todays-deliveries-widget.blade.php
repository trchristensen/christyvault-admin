<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Today's Deliveries</x-slot>

        <x-slot name="description">
            {{ now()->format('D, M j') }} · {{ $total }} {{ \Illuminate\Support\Str::plural('delivery', $total) }}
        </x-slot>

        <x-slot name="afterHeader">
            <a
                href="{{ $scheduleUrl }}"
                class="text-sm font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400"
            >
                View schedule →
            </a>
        </x-slot>

        @if ($groupedOrders->isEmpty())
            <div class="rounded-lg border border-dashed border-gray-300 px-4 py-8 text-center dark:border-gray-700">
                <x-filament::icon
                    icon="heroicon-o-truck"
                    class="mx-auto mb-2 h-8 w-8 text-gray-400"
                />
                <p class="text-sm font-medium text-gray-600 dark:text-gray-300">No deliveries scheduled today.</p>
            </div>
        @else
            <div class="team-deliveries-widget space-y-6">
                @foreach ($groupedOrders as $plant => $orders)
                    <div>
                        <h3 class="mb-2 text-sm font-bold text-gray-950 dark:text-white">
                            {{ match ($plant) {
                                'colma_main' => 'Colma',
                                'colma_locals' => 'Locals (Colma)',
                                'tulare_plant' => 'Tulare',
                                default => \Illuminate\Support\Str::headline($plant),
                            } }}
                        </h3>

                        <ul class="delivery-widget-order-list">
                            @php
                                $deliveryGroups = $orders
                                    ->groupBy(fn ($order) => $order->trip && !$order->trip->trashed() && $order->trip->deliveryStopCount() > 1
                                        ? 'trip-'.$order->trip_id
                                        : 'order-'.$order->id)
                                    ->map(function ($group) {
                                        $trip = $group->first()?->trip;
                                        $stopOrderConfirmed = ! $trip
                                            || $group->count() <= 1
                                            || $trip->isStopOrderConfirmed();

                                        return $group
                                            ->sortBy($stopOrderConfirmed ? 'stop_number' : 'id')
                                            ->values();
                                    });
                            @endphp

                            @foreach ($deliveryGroups as $deliveryGroup)
                                @php
                                    $deliveryTrip = $deliveryGroup->count() > 1 ? $deliveryGroup->first()->trip : null;
                                    $isDeliveryTrip = $deliveryTrip !== null;
                                    $stopOrderConfirmed = ! $isDeliveryTrip || $deliveryTrip->isStopOrderConfirmed();
                                @endphp

                                <li class="{{ $isDeliveryTrip ? 'delivery-trip-group-card delivery-widget-trip-item' : 'delivery-widget-order-item' }}">
                                    @if ($isDeliveryTrip)
                                        <x-delivery-trip-header
                                            :trip="$deliveryTrip"
                                            :stop-count="$deliveryGroup->count()"
                                        />
                                    @endif

                                    <div class="{{ $isDeliveryTrip ? 'delivery-trip-group-stops' : '' }}">
                                    @foreach ($deliveryGroup as $order)
                                <div class="delivery-order-card {{ $isDeliveryTrip ? 'delivery-trip-stop-card' : 'delivery-order-card-embedded' }}">
                                    <x-delivery-order-summary
                                        :order="$order"
                                        :is-delivery-trip="$isDeliveryTrip"
                                        :stop-order-confirmed="$stopOrderConfirmed"
                                        :stop-count="$deliveryGroup->count()"
                                    />

                                    <x-delivery-order-products :order="$order" />
                                </div>
                                    @endforeach
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
