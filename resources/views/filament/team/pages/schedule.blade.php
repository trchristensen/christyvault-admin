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

        .order-status-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 9999px;
            border: 1px solid #d1d5db;
            padding: 1px 8px;
            font-size: 0.72rem;
            font-weight: 700;
            line-height: 1.25rem;
            color: #374151;
            background: #f9fafb;
        }

        .order-status-will_call,
        .order-status-picked_up {
            border-color: #f59e0b;
            color: #92400e;
            background: #fef3c7;
        }

        .order-status-cancelled {
            border-color: #fca5a5;
            color: #991b1b;
            background: #fee2e2;
        }

        .order-status-delivered,
        .order-status-completed {
            border-color: #86efac;
            color: #166534;
            background: #dcfce7;
        }

        .order-status-confirmed,
        .order-status-ready_for_delivery,
        .order-status-out_for_delivery {
            border-color: #93c5fd;
            color: #1e40af;
            background: #dbeafe;
        }

        .delivery-tag-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border-radius: 9999px;
            border: 1px solid;
            padding: 1px 8px;
            font-size: 0.72rem;
            font-weight: 700;
            line-height: 1.25rem;
            white-space: nowrap;
        }

        .delivery-tag-badge svg {
            width: .85rem;
            height: .85rem;
        }

        .delivery-tag-printed {
            border-color: #86efac;
            color: #166534;
            background: #dcfce7;
        }

        .delivery-tag-not-printed {
            border-color: #fcd34d;
            color: #92400e;
            background: #fef3c7;
        }

        .delivery-photo-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border-radius: 9999px;
            border: 1px solid #bfdbfe;
            padding: 1px 8px;
            font-size: 0.72rem;
            font-weight: 700;
            line-height: 1.25rem;
            color: #1d4ed8;
            background: #eff6ff;
            white-space: nowrap;
        }

        .delivery-photo-badge svg,
        .delivery-photo-upload-button svg {
            width: .85rem;
            height: .85rem;
        }

        .delivery-photo-upload-button {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 9999px;
            border: 1px solid #c7d2fe;
            padding: 4px 10px;
            font-size: .78rem;
            font-weight: 700;
            color: #3730a3;
            background: #eef2ff;
        }

        .delivery-photo-upload-button:hover {
            background: #e0e7ff;
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
    <div class="p-4" x-data="{
        deliveryPhotoViewerOpen: false,
        deliveryPhotoViewerPhotos: [],
        deliveryPhotoViewerIndex: 0,
        openDeliveryPhotoViewer(photos, index = 0) {
            this.deliveryPhotoViewerPhotos = photos || [];
            this.deliveryPhotoViewerIndex = index || 0;
            this.deliveryPhotoViewerOpen = true;
            document.body.style.overflow = 'hidden';
        },
        closeDeliveryPhotoViewer() {
            this.deliveryPhotoViewerOpen = false;
            document.body.style.overflow = '';
        },
        currentDeliveryPhoto() {
            return this.deliveryPhotoViewerPhotos[this.deliveryPhotoViewerIndex] || {};
        },
        nextDeliveryPhoto() {
            if (!this.deliveryPhotoViewerPhotos.length) return;
            this.deliveryPhotoViewerIndex = (this.deliveryPhotoViewerIndex + 1) % this.deliveryPhotoViewerPhotos.length;
        },
        previousDeliveryPhoto() {
            if (!this.deliveryPhotoViewerPhotos.length) return;
            this.deliveryPhotoViewerIndex = (this.deliveryPhotoViewerIndex - 1 + this.deliveryPhotoViewerPhotos.length) % this.deliveryPhotoViewerPhotos.length;
        },
    }" x-on:keydown.escape.window="deliveryPhotoViewerOpen && closeDeliveryPhotoViewer()"
        x-on:keydown.arrow-right.window="deliveryPhotoViewerOpen && nextDeliveryPhoto()"
        x-on:keydown.arrow-left.window="deliveryPhotoViewerOpen && previousDeliveryPhoto()">
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
                            @php
                                $statusEnum = \App\Enums\OrderStatus::tryFrom($order->status);
                                $statusLabel =
                                    $statusEnum?->label() ?? \Illuminate\Support\Str::headline((string) $order->status);
                                $statusClass = preg_replace('/[^a-z0-9]+/', '_', strtolower((string) $order->status));
                            @endphp
                            <li class="p-3 border rounded-lg bg-white dark:bg-gray-800">
                                <div class="flex justify-between items-start">
                                    <div class="w-full">
                                        <div class="flex justify-between w-full">
                                            <div
                                                class="flex flex-wrap items-center gap-2 font-semibold text-sm text-gray-500">
                                                <span>Order #{{ $order->id }}</span>
                                                <span class="order-status-badge order-status-{{ $statusClass }}">
                                                    {{ $statusLabel }}
                                                </span>
                                                <span
                                                    class="delivery-tag-badge {{ $order->is_printed ? 'delivery-tag-printed' : 'delivery-tag-not-printed' }}"
                                                    title="{{ $order->is_printed ? 'Delivery tag has been printed' : 'Delivery tag has not been printed yet' }}">
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
                                            </div>
                                            <div>
                                                @if ($order->driver)
                                                    <span
                                                        class="text-sm text-gray-500 font-semibold">{{ $order->driver->name }}</span>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="text-md mt-2 font-semibold text-gray-700 dark:text-gray-200">
                                            {{ $order->location->name ?? 'Customer' }}
                                        </div>

                                        <div class="text-sm flex items-center gap-1">
                                            <a href="geo:0,0?q={{ urlencode($order->location->full_address ?? '') }}">
                                                {{ $order->location->full_address ?? '' }}
                                            </a>
                                            <a href="geo:0,0?q={{ urlencode($order->location->full_address ?? '') }}">
                                                <x-heroicon-o-map-pin class="w-4 h-4" />
                                            </a>
                                        </div>

                                        <div class="mt-3 flex flex-wrap items-center gap-3">
                                            <button type="button" class="delivery-photo-upload-button" x-data
                                                x-on:click="$wire.mountAction('uploadDeliveryPhotos', { order: @js($order->getKey()) })">
                                                <x-heroicon-o-camera />
                                                <span>Upload photos</span>
                                            </button>

                                            @if ($order->deliveryPhotos->isNotEmpty())
                                                @php
                                                    $latestPhoto = $order->deliveryPhotos->first();
                                                @endphp
                                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                                    Latest photo by {{ $latestPhoto->uploadedBy?->name ?? 'Unknown' }}
                                                    {{ $latestPhoto->created_at?->diffForHumans() }}
                                                </span>
                                            @endif
                                        </div>

                                        @if ($order->deliveryPhotos->isNotEmpty())
                                            @php
                                                $deliveryPhotoViewerItems = $order->deliveryPhotos
                                                    ->map(
                                                        fn($photo) => [
                                                            'url' => $photo->url,
                                                            'title' => $photo->original_filename ?? 'Delivery photo',
                                                            'uploadedBy' => $photo->uploadedBy?->name ?? 'Unknown uploader',
                                                            'uploadedAt' => $photo->created_at?->format('M j, Y g:i A'),
                                                            'notes' => $photo->notes,
                                                        ],
                                                    )
                                                    ->values()
                                                    ->all();
                                            @endphp

                                            <div class="delivery-photo-strip" x-data="{ photos: @js($deliveryPhotoViewerItems) }">
                                                @foreach ($order->deliveryPhotos->take(4) as $photo)
                                                    @php
                                                        $thumbnailUrl =
                                                            $deliveryPhotoViewerItems[$loop->index]['url'] ?? $photo->url;
                                                    @endphp
                                                    <button type="button"
                                                        class="delivery-photo-thumb"
                                                        x-on:click="openDeliveryPhotoViewer(photos, {{ $loop->index }})"
                                                        title="{{ $photo->original_filename ?? 'Delivery photo' }} · Uploaded by {{ $photo->uploadedBy?->name ?? 'Unknown' }} {{ $photo->created_at?->format('M j, Y g:i A') }}">
                                                        <img src="{{ $thumbnailUrl }}" alt="Delivery photo for order #{{ $order->id }}">
                                                    </button>
                                                @endforeach

                                                @if ($order->delivery_photos_count > 4)
                                                    <button type="button" class="delivery-photo-more"
                                                        x-on:click="openDeliveryPhotoViewer(photos, 4)">
                                                        +{{ $order->delivery_photos_count - 4 }}
                                                    </button>
                                                @endif
                                            </div>
                                        @endif
                                    </div>

                                    <div class="text-sm">
                                        {{ $order->scheduled_at ? \Carbon\Carbon::parse($order->scheduled_at)->format('g:i A') : '' }}
                                    </div>
                                </div>

                                <table class="orderProducts text-sm" style="padding-left: 2em; margin-top: 1em;">
                                    @foreach ($order->orderProducts as $orderProduct)
                                        @php
                                            $productSku = $orderProduct->is_custom_product
                                                ? 'CUSTOM'
                                                : $orderProduct->product?->sku ?? 'Unknown';
                                            $productName = $orderProduct->is_custom_product
                                                ? $orderProduct->custom_description ?? 'Custom Product'
                                                : $orderProduct->product?->name ?? 'Unknown product';
                                        @endphp
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
                                                    <span>{{ $productSku }}</span>
                                                    <span
                                                        class="text-gray-600 dark:text-gray-500">{{ $productName }}</span>
                                                    @if ($orderProduct->fill_load)
                                                        <p class="fill-load-text text-xs">└ FILL OUT LOAD</p>
                                                    @endif
                                                    @if ($orderProduct->location)
                                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                                            <span class="font-semibold">Location:</span>
                                                            {{ $orderProduct->location }}
                                                        </span>
                                                    @endif
                                                    @if ($orderProduct->notes)
                                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                                            <span class="font-semibold">Notes:</span>
                                                            {{ $orderProduct->notes }}
                                                        </span>
                                                    @endif
                                                    @if ($orderProduct->quantity_delivered !== null && $orderProduct->quantity_delivered !== '')
                                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                                            <span class="font-semibold">Shipped:</span>
                                                            {{ $orderProduct->quantity_delivered }}
                                                        </span>
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

        <div x-cloak x-show="deliveryPhotoViewerOpen" x-transition.opacity
            class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/80 p-3"
            x-on:click.self="closeDeliveryPhotoViewer()">
            <div
                class="relative flex max-h-full w-full max-w-5xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl dark:bg-gray-900">
                <div class="flex items-center justify-between gap-4 border-b border-gray-200 p-4 dark:border-gray-700">
                    <div>
                        <div class="font-semibold text-gray-950 dark:text-white">Delivery photo</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400"
                            x-text="deliveryPhotoViewerPhotos.length ? `${deliveryPhotoViewerIndex + 1} of ${deliveryPhotoViewerPhotos.length}` : ''">
                        </div>
                    </div>
                    <button type="button"
                        class="rounded-full p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
                        x-on:click="closeDeliveryPhotoViewer()" aria-label="Close photo viewer">
                        <x-heroicon-o-x-mark class="h-6 w-6" />
                    </button>
                </div>

                <div class="relative flex min-h-[50vh] items-center justify-center bg-gray-950">
                    <template x-if="deliveryPhotoViewerPhotos.length">
                        <img x-bind:src="currentDeliveryPhoto().url"
                            x-bind:alt="currentDeliveryPhoto().title || 'Delivery photo'"
                            class="max-h-[70vh] w-auto max-w-full object-contain">
                    </template>

                    <button type="button" x-show="deliveryPhotoViewerPhotos.length > 1"
                        class="absolute left-3 top-1/2 -translate-y-1/2 rounded-full bg-white/90 p-2 text-gray-950 shadow-lg transition hover:bg-white"
                        x-on:click.stop="previousDeliveryPhoto()" aria-label="Previous photo">
                        <x-heroicon-o-chevron-left class="h-6 w-6" />
                    </button>

                    <button type="button" x-show="deliveryPhotoViewerPhotos.length > 1"
                        class="absolute right-3 top-1/2 -translate-y-1/2 rounded-full bg-white/90 p-2 text-gray-950 shadow-lg transition hover:bg-white"
                        x-on:click.stop="nextDeliveryPhoto()" aria-label="Next photo">
                        <x-heroicon-o-chevron-right class="h-6 w-6" />
                    </button>
                </div>

                <div class="space-y-1 p-4 text-sm">
                    <div class="font-semibold text-gray-950 dark:text-white"
                        x-text="currentDeliveryPhoto().title || 'Delivery photo'"></div>
                    <div class="text-gray-500 dark:text-gray-400">
                        Uploaded by <span x-text="currentDeliveryPhoto().uploadedBy || 'Unknown uploader'"></span>
                        <span x-show="currentDeliveryPhoto().uploadedAt">
                            · <span x-text="currentDeliveryPhoto().uploadedAt"></span>
                        </span>
                    </div>
                    <div class="text-gray-700 dark:text-gray-300" x-show="currentDeliveryPhoto().notes"
                        x-text="currentDeliveryPhoto().notes"></div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
