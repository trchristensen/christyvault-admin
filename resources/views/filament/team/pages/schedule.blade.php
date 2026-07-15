<x-filament-panels::page>
    <style>
        [x-cloak] {
            display: none !important;
        }

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

        .delivery-photo-strip {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            margin-top: .75rem;
        }

        .delivery-photo-thumb {
            padding: 0;
            width: 54px;
            height: 54px;
            overflow: hidden;
            border: 1px solid #d1d5db;
            border-radius: .5rem;
            background: #f3f4f6;
            cursor: pointer;
            transition: border-color .15s ease, transform .15s ease;
        }

        .delivery-photo-thumb:hover,
        .delivery-photo-thumb:focus-visible {
            border-color: #6366f1;
            transform: translateY(-1px);
        }

        .delivery-photo-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .delivery-photo-more {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 54px;
            height: 54px;
            border: 1px dashed #c7d2fe;
            border-radius: .5rem;
            color: #4338ca;
            background: #eef2ff;
            font-size: .8rem;
            font-weight: 700;
            cursor: pointer;
        }

    </style>
    <div class="p-4">
        <!-- horizontal date bar -->
        <div class="overflow-x-auto -mx-4 px-4 static z-10">
            <div class="flex space-x-2 py-2" x-data x-init="$nextTick(() => { const el = $el.querySelector('.date-item.selected'); if (el) el.scrollIntoView({ behavior: 'smooth', inline: 'center' }); })"
                @date-selected.window="$nextTick(()=>{ const el = $el.querySelector('.date-item.selected'); if(el) el.scrollIntoView({behavior:'smooth', inline:'center'}); })">

                @foreach ($dates as $d)
                    <button wire:click="selectDate('{{ $d['iso'] }}')" wire:key="date-{{ $d['iso'] }}"
                        class="date-item relative flex-shrink-0 flex flex-col items-center justify-center rounded-lg border border-gray-300 bg-white px-3 pt-2 pb-4 text-center transition-colors dark:border-gray-700 dark:bg-gray-900 {{ $d['blocks_delivery'] && $selectedDate !== $d['iso'] ? 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/20' : '' }}"
                        :class="$wire.selectedDate === '{{ $d['iso'] }}' ?
                            'selected border-primary-300 bg-primary-50 text-primary-700 ring-1 ring-primary-200 dark:border-primary-700 dark:bg-primary-950/40 dark:text-primary-200 dark:ring-primary-800' :
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
                        @php
                            $deliveryGroups = $groupOrders
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
                            <div class="delivery-order-card {{ $isDeliveryTrip ? 'delivery-trip-stop-card' : '' }}">
                                <x-delivery-order-summary
                                    :order="$order"
                                    :is-delivery-trip="$isDeliveryTrip"
                                    :stop-order-confirmed="$stopOrderConfirmed"
                                    :stop-count="$deliveryGroup->count()"
                                />

                                @if ($order->deliveryPhotos->isNotEmpty())
                                    @php
                                        $latestPhoto = $order->deliveryPhotos->first();
                                        $deliveryPhotoViewerItems = $order->deliveryPhotos
                                            ->map(fn ($photo) => [
                                                'url' => $photo->url,
                                                'thumbnailUrl' => $photo->thumbnail_url,
                                                'displayUrl' => $photo->display_url,
                                                'title' => $photo->original_filename ?? 'Delivery photo',
                                                'uploadedBy' => $photo->uploadedBy?->name ?? 'Unknown uploader',
                                                'uploadedAt' => $photo->created_at?->format('M j, Y g:i A'),
                                                'notes' => $photo->notes,
                                            ])
                                            ->values()
                                            ->all();
                                    @endphp

                                    <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                        Latest photo by {{ $latestPhoto->uploadedBy?->name ?? 'Unknown' }}
                                        {{ $latestPhoto->created_at?->diffForHumans() }}
                                    </div>

                                    <x-delivery-photo-viewer :photos="$deliveryPhotoViewerItems">
                                        <div class="delivery-photo-strip">
                                            @foreach ($order->deliveryPhotos->take(4) as $photo)
                                                @php
                                                    $thumbnailUrl = $deliveryPhotoViewerItems[$loop->index]['thumbnailUrl']
                                                        ?? $photo->thumbnail_url;
                                                @endphp
                                                <button
                                                    type="button"
                                                    class="delivery-photo-thumb"
                                                    x-on:click="openDeliveryPhotoViewer({{ $loop->index }})"
                                                    title="{{ $photo->original_filename ?? 'Delivery photo' }} · Uploaded by {{ $photo->uploadedBy?->name ?? 'Unknown' }} {{ $photo->created_at?->format('M j, Y g:i A') }}"
                                                >
                                                    <img
                                                        src="{{ $thumbnailUrl }}"
                                                        loading="lazy"
                                                        decoding="async"
                                                        alt="Delivery photo for order #{{ $order->id }}"
                                                    >
                                                </button>
                                            @endforeach

                                            @if ($order->delivery_photos_count > 4)
                                                <button
                                                    type="button"
                                                    class="delivery-photo-more"
                                                    x-on:click="openDeliveryPhotoViewer(4)"
                                                >
                                                    +{{ $order->delivery_photos_count - 4 }}
                                                </button>
                                            @endif
                                        </div>
                                    </x-delivery-photo-viewer>
                                @endif

                                <x-delivery-order-products :order="$order" />
                            </div>
                                @endforeach
                                </div>
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
