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
            min-height: 70px;
            margin: 0 2px;
            transition: all 0.2s ease-in-out;
        }

        .date-item.selected {
            transform: scale(1.04);
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
        <div class="overflow-x-auto -mx-4 px-4 static top-8 z-10">
            <div class="flex space-x-2 py-2" x-data x-init="$nextTick(() => { const el = $el.querySelector('.date-item.selected'); if (el) el.scrollIntoView({ behavior: 'smooth', inline: 'center' }); })"
                @date-selected.window="$nextTick(()=>{ const el = $el.querySelector('.date-item.selected'); if(el) el.scrollIntoView({behavior:'smooth', inline:'center'}); })">

                @foreach ($dates as $d)
                    <button wire:click="selectDate('{{ $d['iso'] }}')" wire:key="date-{{ $d['iso'] }}"
                        class="date-item flex-shrink-0 flex flex-col items-center justify-center rounded-md px-3 py-2 text-center border transition-colors"
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
                    </button>
                @endforeach
            </div>
        </div>



        <!-- orders for selected date -->
        <div class="mt-4" style="margin-top: 2em;">

            <h2 class="text-lg font-semibold mb-4">
                {{ \Carbon\Carbon::parse($selectedDate)->format('l, M j, Y') }}
            </h2>


            @if ($orders && $orders->count())
                <ul class="space-y-2">
                    @foreach ($orders as $order)
                        <li class="p-3 border rounded-lg">
                            <div class="flex justify-between items-start">
                                <div class="w-full">
                                    <div class="flex justify-between w-full">
                                        <div class="font-semibold text-sm text-gray-500">Order
                                            #{{ $order->id }}</div>
                                        <div>
                                            @if ($order->driver)
                                                <span
                                                    class="text-sm text-gray-500 font-semibold
                                            ">
                                                    {{ $order->driver->name }}</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="text-md">
                                        {{ $order->Location->name ?? 'Customer' }}
                                    </div>


                                    <div class="text-sm ">

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
                                                    <p
                                                        class="fill-load-text
                                            text-xs">
                                                        └ FILL OUT LOAD
                                                    </p>
                                                @endif
                                            </div>
                                        </td>

                                    </tr>
                                @endforeach
                            </table>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="text-sm
                                            ">No deliveries
                    scheduled for this day.
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
