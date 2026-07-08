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

        .team-deliveries-widget .product-row + .product-row {
            border-top: 1px solid #e5e7eb;
        }

        .team-deliveries-widget .product-quantity {
            width: 3.5rem;
            border-right: 1px solid #e5e7eb;
            text-align: right;
        }

        .dark .team-deliveries-widget .product-row + .product-row,
        .dark .team-deliveries-widget .product-quantity {
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
                            @foreach ($orders as $order)
                                @php
                                    $statusEnum = \App\Enums\OrderStatus::tryFrom($order->status);
                                    $statusLabel = $statusEnum?->label() ?? \Illuminate\Support\Str::headline((string) $order->status);
                                    $statusClass = preg_replace('/[^a-z0-9]+/', '_', strtolower((string) $order->status));
                                @endphp

                                <li class="rounded-lg border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-gray-900">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold text-gray-500 dark:text-gray-400">
                                                <span>Order #{{ $order->id }}</span>
                                                <span class="order-status-badge order-status-{{ $statusClass }}">
                                                    {{ $statusLabel }}
                                                </span>
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

                                        <div class="shrink-0 text-right text-xs text-gray-500 dark:text-gray-400">
                                            @if ($order->delivery_time)
                                                <div class="font-semibold text-gray-700 dark:text-gray-200">
                                                    {{ \Carbon\Carbon::parse($order->delivery_time)->format('g:i A') }}
                                                </div>
                                            @endif
                                            @if ($order->driver)
                                                <div>{{ $order->driver->name }}</div>
                                            @endif
                                        </div>
                                    </div>

                                    @if ($order->orderProducts->isNotEmpty())
                                        <div class="mt-3 overflow-hidden rounded-md border border-gray-200 text-sm dark:border-white/10">
                                            @foreach ($order->orderProducts as $orderProduct)
                                                @php
                                                    $productSku = $orderProduct->is_custom_product
                                                        ? 'CUSTOM'
                                                        : ($orderProduct->product?->sku ?? 'Unknown');
                                                    $productName = $orderProduct->is_custom_product
                                                        ? ($orderProduct->custom_description ?? 'Custom Product')
                                                        : ($orderProduct->product?->name ?? 'Unknown product');
                                                @endphp

                                                <div class="product-row flex gap-3 px-3 py-2">
                                                    <div class="product-quantity shrink-0 pr-3 font-semibold text-gray-700 dark:text-gray-200">
                                                        {{ $orderProduct->fill_load ? '*' : $orderProduct->quantity }}
                                                    </div>
                                                    <div class="min-w-0">
                                                        <div class="font-medium text-gray-950 dark:text-white">{{ $productSku }}</div>
                                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $productName }}</div>
                                                        @if ($orderProduct->fill_load)
                                                            <div class="text-xs font-semibold text-gray-500 dark:text-gray-400">FILL OUT LOAD</div>
                                                        @endif
                                                        @if ($orderProduct->location)
                                                            <div class="text-xs text-gray-500 dark:text-gray-400">Location: {{ $orderProduct->location }}</div>
                                                        @endif
                                                        @if ($orderProduct->notes)
                                                            <div class="text-xs text-gray-500 dark:text-gray-400">Notes: {{ $orderProduct->notes }}</div>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
