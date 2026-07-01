<x-filament-panels::page>
    <style>
        .fi-main {
            padding: 0 !important;
        }

        .fi-page>section {
            padding: 0;
        }

        .date-item {
            min-width: 70px;
            min-height: 82px;
            margin: 0 2px;
            transition: all 0.2s ease-in-out;
        }

        .date-item.selected {
            transform: scale(1.04);
        }

        .delivery-markers {
            position: absolute;
            left: 50%;
            bottom: 5px;
            transform: translateX(-50%);
            display: flex;
            gap: 4px;
            pointer-events: none;
        }

        .delivery-marker {
            width: 5px;
            height: 5px;
            border-radius: 9999px;
            opacity: 0.95;
        }

        .delivery-marker-colma {
            background: #2563eb;
        }

        .delivery-marker-locals {
            background: #059669;
        }

        .delivery-marker-tulare {
            background: #d97706;
        }


        .fill-load-text {
            margin-left: 1em;
            font-weight: bold;
        }

        .fill-load-ast {
            font-weight: bold;
        }

        table.orderProducts {


            tr {
                border-bottom: 1px solid #ccc;
            }

            tr:last-child {
                border-bottom: none;
            }

            .qty,
            .product-details {
                vertical-align: top;
                padding: 4px 8px;
            }

            .qty {
                width: 2em;
                border-right: 1px solid #ccc;
                text-align: right;
            }

        }
    </style>
    <div class="p-4">
        <!-- horizontal date bar -->
        <div class="overflow-x-auto -mx-4 px-4 static z-10">
            <div class="flex space-x-2 py-2" x-data x-init="$nextTick(() => { const el = $el.querySelector('.date-item.selected'); if (el) el.scrollIntoView({ behavior: 'smooth', inline: 'center' }); })"
                @date-selected.window="$nextTick(()=>{ const el = $el.querySelector('.date-item.selected'); if(el) el.scrollIntoView({behavior:'smooth', inline:'center'}); })">

                @foreach ($dates as $d)
                    <button wire:click="selectDate('{{ $d['iso'] }}')" wire:key="date-{{ $d['iso'] }}"
                        class="date-item relative flex-shrink-0 flex flex-col items-center justify-center rounded-md px-3 pt-2 pb-4 text-center border transition-colors {{ $d['blocks_delivery'] && $selectedDate !== $d['iso'] ? 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/20' : '' }}"
                        :class="$wire.selectedDate === '{{ $d['iso'] }}' ?
                            'bg-primary-500 text-white border-transparent selected' :
                            'hover:bg-gray-100 dark:hover:bg-gray-800'">

                        @if ($d['label'])
                            <div class="text-xs font-semibold">{{ $d['label'] }}</div>
                        @else
                            <div class="text-[10px] text-gray-400">{{ \Carbon\Carbon::parse($d['iso'])->format('M') }}
                            </div>
                        @endif
                        <div class="text-sm font-semibold">{{ $d['weekday'] }}</div>
                        <div class="text-base">{{ $d['day'] }}</div>
                        @if (!empty($d['calendar_days']))
                            @php
                                $calendarDay = $d['calendar_days'][0];
                            @endphp
                            <div
                                class="mt-1 max-w-[58px] truncate rounded px-1.5 py-0.5 text-[10px] font-semibold {{ $calendarDay['blocks_delivery'] ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-200' : 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-200' }}">
                                {{ \Illuminate\Support\Str::limit($calendarDay['name'], 12) }}
                            </div>
                        @endif

                        @if (!empty($d['delivery_markers']))
                            <span class="delivery-markers">
                                @foreach ($d['delivery_markers'] as $marker)
                                    <span class="delivery-marker {{ $marker['class'] }}"
                                        title="{{ $marker['label'] }}: {{ $marker['count'] }} {{ \Illuminate\Support\Str::plural('delivery', $marker['count']) }}"></span>
                                @endforeach
                            </span>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>



        <!-- orders for selected date -->
        <div class="mt-4" style="margin-top: 2em;">

            <h2 class="text-lg font-semibold mb-4">
                {{ \Carbon\Carbon::parse($selectedDate)->format('l, M j, Y') }}
            </h2>

            @if (!empty($selectedCalendarDays))
                <div class="mb-4 space-y-2">
                    @foreach ($selectedCalendarDays as $calendarDay)
                        <div
                            class="rounded-md border px-3 py-2 text-sm {{ $calendarDay['blocks_delivery'] ? 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950/20 dark:text-red-200' : 'border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-900 dark:bg-blue-950/20 dark:text-blue-200' }}">
                            <div class="font-semibold">
                                {{ $calendarDay['name'] }}
                                <span class="font-normal opacity-75">({{ $calendarDay['type_label'] }})</span>
                            </div>
                            @if (!empty($calendarDay['notes']))
                                <div class="mt-1 opacity-80">{{ $calendarDay['notes'] }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif


            @if ($orders && $orders->count())
                @foreach ($orders as $plant => $groupOrders)
                    <h3 class="text-md font-bold mt-6 mb-2">
                        {{ match ($plant) {
                            'colma_main' => 'Colma',
                            'colma_locals' => 'Locals (Colma)',
                            'tulare_plant' => 'Tulare',
                            default => ucfirst($plant),
                        } }}
                    </h3>

                    <ul class="space-y-2">
                        @foreach ($groupOrders as $order)
                            <li class="p-3 border rounded-lg bg-white dark:bg-gray-800">
                                <div class="flex justify-between items-start">
                                    <div class="w-full">
                                        <div class="flex justify-between w-full">
                                            <div class="font-semibold text-sm text-gray-500">Order #{{ $order->id }}
                                            </div>
                                            <div>
                                                @if ($order->driver)
                                                    <span
                                                        class="text-sm text-gray-500 font-semibold">{{ $order->driver->name }}</span>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="text-md">
                                            {{ $order->location->name ?? 'Customer' }}
                                        </div>

                                        <div class="text-sm">
                                            <a href="geo:0,0?q={{ urlencode($order->location->full_address ?? '') }}">
                                                {{ $order->location->full_address ?? '' }}
                                            </a>
                                        </div>
                                    </div>

                                    <div class="text-sm">
                                        {{ $order->scheduled_at ? \Carbon\Carbon::parse($order->scheduled_at)->format('g:i A') : '' }}
                                    </div>
                                </div>

                                <table class="orderProducts text-sm" style="padding-left: 2em; margin-top: 1em;">
                                    @foreach ($order->orderProducts as $orderProduct)
                                        <tr class="order-product">
                                            <td class="qty">
                                                @if ($orderProduct->fill_load)
                                                    <span class="fill-load-ast">*</span>
                                                @else
                                                    <span>{{ $orderProduct->quantity }}</span>
                                                @endif
                                            </td>
                                            <td class="product-details">
                                                <div class="flex flex-col w-full">
                                                    <span>{{ $orderProduct->product->sku }}</span>
                                                    <span
                                                        class="text-gray-600 dark:text-gray-500">{{ $orderProduct->product->name }}</span>
                                                    @if ($orderProduct->fill_load)
                                                        <p class="fill-load-text text-xs">└ FILL OUT LOAD</p>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            @else
                <div class="text-sm">No deliveries scheduled for this day.</div>
            @endif


        </div>
    </div>
</x-filament-panels::page>
