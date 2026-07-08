<x-filament-widgets::widget>
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

        @if ($orders->isEmpty())
            <div class="rounded-lg border border-dashed border-gray-300 px-4 py-8 text-center dark:border-gray-700">
                <x-filament::icon
                    icon="heroicon-o-truck"
                    class="mx-auto mb-2 h-8 w-8 text-gray-400"
                />
                <p class="text-sm font-medium text-gray-600 dark:text-gray-300">No deliveries scheduled today.</p>
            </div>
        @else
            <div class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($orders as $order)
                    @php
                        $productSummary = $order->orderProducts
                            ->take(3)
                            ->map(function ($orderProduct) {
                                $label = $orderProduct->is_custom_product
                                    ? ($orderProduct->custom_description ?? 'Custom')
                                    : ($orderProduct->product?->sku ?? 'Unknown');

                                return $orderProduct->fill_load
                                    ? "Fill load × {$label}"
                                    : "{$orderProduct->quantity} × {$label}";
                            })
                            ->join(', ');
                    @endphp

                    <div class="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                            <x-filament::icon icon="heroicon-o-truck" class="h-5 w-5" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                <p class="truncate font-semibold text-gray-950 dark:text-white">
                                    {{ $order->location?->name ?? 'Customer' }}
                                </p>
                                <span class="shrink-0 text-xs font-medium text-gray-500 dark:text-gray-400">
                                    {{ $order->delivery_time ? \Carbon\Carbon::parse($order->delivery_time)->format('g:i A') : 'Time TBD' }}
                                </span>
                            </div>

                            <p class="truncate text-sm text-gray-500 dark:text-gray-400">
                                {{ $order->location?->city ?? 'Address unavailable' }}
                                @if ($order->driver)
                                    · {{ $order->driver->name }}
                                @endif
                            </p>

                            @if ($productSummary)
                                <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">
                                    {{ $productSummary }}
                                    @if ($order->orderProducts->count() > 3)
                                        · +{{ $order->orderProducts->count() - 3 }} more
                                    @endif
                                </p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($total > $orders->count())
                <a
                    href="{{ $scheduleUrl }}"
                    class="mt-4 block rounded-lg bg-gray-50 px-4 py-2.5 text-center text-sm font-semibold text-primary-600 hover:bg-gray-100 dark:bg-white/5 dark:text-primary-400 dark:hover:bg-white/10"
                >
                    View {{ $total - $orders->count() }} more
                </a>
            @endif
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
