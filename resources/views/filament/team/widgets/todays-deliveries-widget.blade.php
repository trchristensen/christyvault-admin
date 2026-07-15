<x-filament-widgets::widget>
    <style>
        .team-deliveries-widget .order-status-badge {
            display: inline-flex;
            align-items: center;
            border: 1px solid #d1d5db;
            border-radius: 9999px;
            padding: 1px 8px;
            background: #f9fafb;
            color: #374151;
            font-size: .72rem;
            font-weight: 700;
            line-height: 1.25rem;
        }

        .team-deliveries-widget .order-status-will_call,
        .team-deliveries-widget .order-status-picked_up {
            border-color: #f59e0b;
            background: #fef3c7;
            color: #92400e;
        }

        .team-deliveries-widget .order-status-cancelled {
            border-color: #fca5a5;
            background: #fee2e2;
            color: #991b1b;
        }

        .team-deliveries-widget .order-status-delivered,
        .team-deliveries-widget .order-status-completed {
            border-color: #86efac;
            background: #dcfce7;
            color: #166534;
        }

        .team-deliveries-widget .order-status-confirmed,
        .team-deliveries-widget .order-status-ready_for_delivery,
        .team-deliveries-widget .order-status-out_for_delivery {
            border-color: #93c5fd;
            background: #dbeafe;
            color: #1e40af;
        }

        .team-deliveries-widget .delivery-tag-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: 1px solid;
            border-radius: 9999px;
            padding: 1px 8px;
            font-size: .72rem;
            font-weight: 700;
            line-height: 1.25rem;
            white-space: nowrap;
        }

        .team-deliveries-widget .delivery-tag-badge svg {
            width: .85rem;
            height: .85rem;
        }

        .team-deliveries-widget .delivery-tag-printed {
            border-color: #86efac;
            background: #dcfce7;
            color: #166534;
        }

        .team-deliveries-widget .delivery-tag-not-printed {
            border-color: #fcd34d;
            background: #fef3c7;
            color: #92400e;
        }

        .team-deliveries-widget .delivery-photo-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: 1px solid #bfdbfe;
            border-radius: 9999px;
            padding: 1px 8px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: .72rem;
            font-weight: 700;
            line-height: 1.25rem;
            white-space: nowrap;
        }

        .team-deliveries-widget .delivery-photo-badge svg {
            width: .85rem;
            height: .85rem;
        }

        .team-deliveries-widget table.orderProducts {
            margin-top: 1rem;
            margin-left: 1rem;
            font-size: .875rem;
            line-height: 1.25rem;
            border-collapse: collapse;
        }

        .team-deliveries-widget table.orderProducts tr {
            border-bottom: 1px solid #d1d5db;
        }

        .team-deliveries-widget table.orderProducts tr:last-child {
            border-bottom: 0;
        }

        .team-deliveries-widget table.orderProducts .qty,
        .team-deliveries-widget table.orderProducts .product-details {
            padding: 4px 8px;
            vertical-align: top;
        }

        .team-deliveries-widget table.orderProducts .qty {
            width: 2rem;
            border-right: 1px solid #e5e7eb;
            text-align: right;
        }

        .team-deliveries-widget .fill-load-text {
            margin-left: 1rem;
            font-weight: 700;
        }

        .dark .team-deliveries-widget table.orderProducts tr,
        .dark .team-deliveries-widget table.orderProducts .qty {
            border-color: rgb(255 255 255 / .1);
        }
    </style>

    <x-filament::section>
        <x-slot name="heading">Today's Deliveries</x-slot>

        <x-slot name="description">
            {{ now()->format('l, F j') }} · {{ $total }} {{ \Illuminate\Support\Str::plural('delivery', $total) }}
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

                        <ul class="space-y-2">
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

                                <li class="{{ $isDeliveryTrip ? 'delivery-trip-group-card' : '' }}">
                                    @if ($isDeliveryTrip)
                                        <x-delivery-trip-header
                                            :trip="$deliveryTrip"
                                            :stop-count="$deliveryGroup->count()"
                                        />
                                    @endif

                                    <div class="{{ $isDeliveryTrip ? 'delivery-trip-group-stops' : '' }}">
                                    @foreach ($deliveryGroup as $order)
                                    @php
                                        $statusEnum = \App\Enums\OrderStatus::tryFrom($order->status);
                                        $statusLabel = $statusEnum?->label() ?? \Illuminate\Support\Str::headline((string) $order->status);
                                        $statusClass = preg_replace('/[^a-z0-9]+/', '_', strtolower((string) $order->status));
                                    @endphp
                                <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-gray-900 {{ $isDeliveryTrip ? 'delivery-trip-stop-card' : '' }}">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold text-gray-500 dark:text-gray-400">
                                                <span>Order #{{ $order->id }}</span>
                                                @if ($order->driver)
                                                    <span class="text-gray-700 dark:text-gray-300">{{ $order->driver->name }}</span>
                                                @endif
                                                @if ($order->delivery_time)
                                                    <span class="text-gray-700 dark:text-gray-300">
                                                        {{ \Carbon\Carbon::parse($order->delivery_time)->format('g:i A') }}
                                                    </span>
                                                @endif
                                                @if ($isDeliveryTrip && $stopOrderConfirmed)
                                                    <span class="delivery-trip-stop-label">
                                                        <span class="delivery-trip-stop-number">{{ $order->activeTripStop?->sequence ?? $order->stop_number }}</span>
                                                        Stop {{ $order->activeTripStop?->sequence ?? $order->stop_number }} of {{ $deliveryGroup->count() }}
                                                    </span>
                                                @endif
                                            </div>

                                            <p class="mt-1 font-semibold text-gray-950 dark:text-white">
                                                {{ $order->location?->name ?? 'Customer' }}
                                            </p>

                                            @if ($order->location?->full_address)
                                                <a
                                                    href="https://www.google.com/maps/search/?api=1&query={{ urlencode($order->location->full_address) }}"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="text-sm text-primary-600 hover:underline dark:text-primary-400"
                                                >
                                                    {{ $order->location->full_address }}
                                                </a>
                                            @endif
                                        </div>

                                        <div class="ml-auto flex shrink-0 flex-wrap items-center justify-end gap-2">
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
                                                    <span class="delivery-photo-badge"
                                                        title="{{ $order->delivery_photos_count }} {{ \Illuminate\Support\Str::plural('delivery photo', $order->delivery_photos_count) }} attached">
                                                        <x-heroicon-o-camera />
                                                        <span>{{ $order->delivery_photos_count }} {{ \Illuminate\Support\Str::plural('photo', $order->delivery_photos_count) }}</span>
                                                    </span>
                                                @endif
                                            <x-delivery-order-actions-menu :order="$order" />
                                        </div>
                                    </div>

                                    @if ($order->orderProducts->isNotEmpty())
                                        <table class="orderProducts">
                                            @foreach ($order->orderProducts as $orderProduct)
                                                @php
                                                    $productSku = $orderProduct->is_custom_product
                                                        ? 'CUSTOM'
                                                        : ($orderProduct->product?->sku ?? 'Unknown');
                                                    $productName = $orderProduct->is_custom_product
                                                        ? ($orderProduct->custom_description ?? 'Custom Product')
                                                        : ($orderProduct->product?->name ?? 'Unknown product');
                                                @endphp

                                                <tr>
                                                    <td class="qty">
                                                        @if ($orderProduct->fill_load)
                                                            <strong>*</strong>
                                                        @else
                                                            {{ $orderProduct->quantity }}
                                                        @endif
                                                    </td>
                                                    <td class="product-details">
                                                        <div class="flex flex-col">
                                                            <span>{{ $productSku }}</span>
                                                            <span class="text-gray-600 dark:text-gray-500">{{ $productName }}</span>
                                                        @if ($orderProduct->fill_load)
                                                                <span class="fill-load-text text-xs">└ FILL OUT LOAD</span>
                                                        @endif
                                                        @if ($orderProduct->location)
                                                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                                                    <strong>Location:</strong> {{ $orderProduct->location }}
                                                                </span>
                                                        @endif
                                                        @if ($orderProduct->notes)
                                                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                                                    <strong>Notes:</strong> {{ $orderProduct->notes }}
                                                                </span>
                                                        @endif
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    @endif
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
