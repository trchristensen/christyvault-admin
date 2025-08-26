@php
    use Propaganistas\LaravelPhone\PhoneNumber;
    use Illuminate\Support\Facades\Storage;
@endphp
<!-- add css -->
<style>
    .grid-cols-2 {
        grid-template-columns: 1fr 1fr;
    }

    .grid-cols-4 {
        grid-template-columns: 1fr 1fr 1fr 1fr;
    }

    .grid-cols-12 {
        grid-template-columns: 1fr 1fr 1fr 1fr 1fr 1fr 1fr 1fr 1fr 1fr 1fr 1fr;
    }

    .col-span-1 {
        grid-column: span 1;
    }

    .col-span-5 {
        grid-column: span 5;
    }

    .col-span-3 {
        grid-column: span 3;
    }

    .col-span-12 {
        grid-column: span 12;
    }

    /* last product row border none */
    .product-item:last-of-type {
        border-bottom: none;
    }

    .products-table-container {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        max-width: calc(100vw - 4rem);
        cursor: grab;
        @apply touch-pan-x;
    }

    .products-table-container:active {
        cursor: grabbing;
    }

    .products-table-content {
        position: relative;
        min-width: 848px;
        /* width: max-content; */
        width: 100%;
    }

    /* Product items and borders */
    .product-item {
        @apply border-gray-200 dark:border-gray-700;
    }

    /* Text colors */
    .text-gray-400 {
        @apply dark:text-gray-500;
    }

    .text-gray-500 {
        @apply dark:text-gray-400;
    }

    .text-gray-600 dark:text-gray-400 {
        @apply dark:text-gray-300;
    }

    .text-gray-800 {
        @apply dark:text-gray-100;
    }

    /* Modal styles */
    .fi-modal-window {
        max-width: calc(100vw - 2rem) !important;
        width: calc(100vw - 2rem) !important;
        @apply bg-white dark:bg-gray-900;
    }

    @media (min-width: 640px) {
        .fi-modal-window {
            max-width: min(90vw, 1200px) !important;
            width: auto !important;
        }
    }

    .fi-modal-content {
        max-width: 100%;
        padding: 1rem;
    }

    /* Background colors */
    .bg-gray-50 {
        @apply dark:bg-gray-900;
    }

    @media (max-width: 768px) {
        .grid-cols-2 {
            grid-template-columns: 1fr;
        }

        .grid-cols-4 {
            grid-template-columns: 1fr 1fr;
        }
    }

    /* Dark mode text and background colors */
    .order-container {
        @apply text-gray-900 dark:text-white;
        @apply bg-white dark:bg-gray-800;

    }

    .products-table-container {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        max-width: calc(100vw - 4rem);
    }

    /* Table styles */
    .products-table-content {
        @apply bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700;
        min-width: 848px;
        /* width: max-content; */
        width: 100%;
    }

    /* Table header and content */
    .products-table-content .product-item {
        @apply bg-white dark:bg-gray-800;
    }

    /* Grid sections */
    .grid-cols-4>div {
        @apply bg-white dark:bg-gray-800 p-4 rounded-lg;
    }
</style>
<div class="p-4 order-container">
    <div class="flex items-start justify-between mb-4">
        <div>
            <h2 class="font-bold text-gray-400 dark:text-gray-600 dark:text-white">{{ $record->order_number }}</h2>

        </div>
        <div class="flex items-center gap-4">
            {{-- get the fucking label, not the value --}}
            <p class="text-sm font-bold text-gray-600 dark:text-gray-400">
                {{ App\Enums\PlantLocation::from($record->plant_location)->getLabel() }}</p>
            
            {{-- Delivery Tag Status --}}
            @if($record->is_printed)
                <span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-green-800 bg-green-100 rounded-full dark:bg-green-900 dark:text-green-200">
                    <x-heroicon-s-check-circle class="w-3 h-3" />
                    Printed
                </span>
            @else
                <span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-gray-800 bg-gray-100 rounded-full dark:bg-gray-700 dark:text-gray-200">
                    <x-heroicon-o-printer class="w-3 h-3" />
                    Not Printed
                </span>
            @endif
            
            {{-- Delivery Tag Attachment Status --}}
            @if($record->delivery_tag_url)
                <button 
                    @click="$dispatch('toggle-delivery-tag')"
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-blue-800 bg-blue-100 rounded-full hover:bg-blue-200 transition-colors cursor-pointer dark:bg-blue-900 dark:text-blue-200 dark:hover:bg-blue-800"
                    title="Click to view delivery tag"
                    x-data="{ showTag: false }"
                    x-on:toggle-delivery-tag.window="showTag = !showTag"
                >
                    <x-heroicon-s-document-text class="w-3 h-3" />
                    <span x-text="showTag ? 'Hide Tag' : 'View Tag'"></span>
                </button>
            @endif
            
            <div class="px-2 py-1 text-sm font-medium text-gray-800 border rounded-full"
                style="background-color: {{ $record->status_color['background'] }}; color: {{ $record->status_color['text'] }}; border-color: {{ $record->status_color['border'] }}">
                {{-- get the status label (it's an enum) --}}
                {{ App\Enums\OrderStatus::from($record->status)->label() }}
            </div>
        </div>
    </div>

    {{-- Expandable Delivery Tag Preview --}}
    @if($record->delivery_tag_url)
        <div x-data="{ showTag: false }" 
             x-on:toggle-delivery-tag.window="showTag = !showTag" 
             class="mb-4">
            <div x-show="showTag" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 transform -translate-y-2"
                 x-transition:enter-end="opacity-100 transform translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 transform translate-y-0"
                 x-transition:leave-end="opacity-0 transform -translate-y-2"
                 class="p-4 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800"
                 style="display: none;"
            >
                <div class="flex items-start justify-between mb-3">
                    <h3 class="font-medium text-blue-900 dark:text-blue-100">Delivery Tag Attachment</h3>
                    <div class="flex items-center gap-2">
                        <a href="{{ Storage::disk('r2')->url($record->delivery_tag_url) }}" 
                           target="_blank" 
                           class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-blue-800 bg-blue-200 rounded hover:bg-blue-300 transition-colors dark:bg-blue-800 dark:text-blue-200 dark:hover:bg-blue-700">
                            <x-heroicon-o-arrow-top-right-on-square class="w-3 h-3" />
                            Open Full Size
                        </a>
                        <button @click="showTag = false" 
                                class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200">
                            <x-heroicon-o-x-mark class="w-4 h-4" />
                        </button>
                    </div>
                </div>
                
                @php
                    $fileExtension = strtolower(pathinfo($record->delivery_tag_url, PATHINFO_EXTENSION));
                    $isImage = in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                @endphp
                
                @if($isImage)
                    <div class="flex justify-center">
                        <img src="{{ Storage::disk('r2')->url($record->delivery_tag_url) }}" 
                             alt="Delivery Tag" 
                             class="max-w-full h-auto max-h-96 rounded-lg shadow-sm border border-blue-200 dark:border-blue-700 cursor-pointer hover:shadow-md transition-shadow"
                             onclick="window.open('{{ Storage::disk('r2')->url($record->delivery_tag_url) }}', '_blank')"
                        />
                    </div>
                @elseif($fileExtension === 'pdf')
                    <div class="w-full">
                        <iframe src="{{ Storage::disk('r2')->url($record->delivery_tag_url) }}#toolbar=0&navpanes=0&scrollbar=0&view=FitH" 
                                class="w-full h-96 rounded-lg border border-blue-200 dark:border-blue-700"
                                title="Delivery Tag PDF Preview">
                        </iframe>
                        <p class="text-xs text-blue-600 dark:text-blue-400 mt-2 text-center">
                            PDF preview - <a href="{{ Storage::disk('r2')->url($record->delivery_tag_url) }}" target="_blank" class="underline hover:no-underline">open in new tab</a> if not displaying properly
                        </p>
                    </div>
                @else
                    <div class="flex items-center justify-center p-8 bg-white dark:bg-gray-800 rounded-lg border-2 border-dashed border-blue-300 dark:border-blue-600">
                        <div class="text-center">
                            <x-heroicon-o-document-text class="w-12 h-12 mx-auto text-blue-500 mb-2" />
                            <p class="text-sm text-blue-700 dark:text-blue-300 mb-2">
                                {{ strtoupper($fileExtension) }} Document
                            </p>
                            <a href="{{ Storage::disk('r2')->url($record->delivery_tag_url) }}" 
                               target="_blank" 
                               class="inline-flex items-center gap-1 px-3 py-1 text-sm font-medium text-blue-800 bg-blue-200 rounded hover:bg-blue-300 transition-colors dark:bg-blue-800 dark:text-blue-200 dark:hover:bg-blue-700">
                                <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                                Download/View
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <div class="relative flex p-4 mb-4 rounded-lg bg-gray-50 dark:bg-gray-800">
        <div class="flex flex-col items-center justify-center mr-4">
            <div
                class="flex flex-col items-center justify-start w-8 gap-0 text-gray-400 border-r border-gray-200 dark:text-gray-600 dark:text-gray-400 font-lighter dark:border-gray-600">
                <span>S</span>
                <span>O</span>
                <span>L</span>
                <span>D</span>
                <span class="mb-2"></span>
                <span>T</span>
                <span>O</span>
            </div>
        </div>
        <div class="grid justify-between w-full grid-cols-2 gap-4">

            <div class="flex flex-col items-start gap-1">
                <p class="font-bold">{{ optional($record->location)->name }}</p>
                @if(optional($record->location)->full_address)
                    <div class="flex items-center gap-1">
                        <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($record->location->full_address) }}" 
                           target="_blank" 
                           rel="noopener noreferrer" 
                           class="text-primary-600 dark:text-primary-400 hover:underline">
                            {{ $record->location->full_address }}
                        </a>
                        <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($record->location->full_address) }}" 
                           target="_blank" 
                           rel="noopener noreferrer"
                           class="text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300">
                            <x-heroicon-o-map-pin class="w-4 h-4" />
                        </a>
                    </div>
                @endif

                @if ($record->location && $record->location->preferredDeliveryContact)
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-phone class="w-4 h-4" />
                        @php
                            try {
                                $formattedPhone = (new PhoneNumber(
                                    $record->location->preferredDeliveryContact->phone,
                                    'US',
                                ))->formatNational();
                            } catch (\Exception $e) {
                                $formattedPhone = $record->location->preferredDeliveryContact->phone;
                            }
                        @endphp
                        <p>Contact: {{ $record->location->preferredDeliveryContact->name }}
                            @if ($record->location->preferredDeliveryContact->phone)
                                - {{ $formattedPhone }}
                                @if ($record->location->preferredDeliveryContact->phone_extension)
                                    x{{ $record->location->preferredDeliveryContact->phone_extension }}
                                @endif
                            @endif
                            @if ($record->location->preferredDeliveryContact->mobile_phone)
                                • Mobile: {{ $record->location->preferredDeliveryContact->mobile_phone }}
                            @endif
                        </p>
                    </div>
                @endif

                @if ($record->location && $record->location->phone)
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-phone class="w-4 h-4" />
                        @php
                            try {
                                $formattedPhone = (new PhoneNumber($record->location->phone, 'US'))->formatNational();
                            } catch (\Exception $e) {
                                $formattedPhone = $record->location->phone;
                            }
                        @endphp
                        <p>Location: {{ $formattedPhone }}
                            @if ($record->location->phone_extension)
                                x{{ $record->location->phone_extension }}
                            @endif
                        </p>
                    </div>
                @endif
            </div>
            <div class="grid grid-cols-1 gap-4">
                @if($record->customer_order_number)
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Customer Order #</p>
                        <p class="font-medium">{{ $record->customer_order_number }}</p>
                    </div>
                @endif
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Requested Date</p>
                    <p class="font-medium">{{ $record->requested_delivery_date?->format('M j, Y') }}</p>
                </div>
                <div>
                    <!-- date of order -->
                    <p class="text-sm text-gray-600 dark:text-gray-400">Date of Order</p>
                    <p class="font-medium">{{ $record->order_date?->format('M j, Y') }}</p>
                </div>
                 <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Ordered By</p>
                    <p class="font-medium">{{ $record->ordered_by }}</p>
                </div>
            </div>

        </div>
    </div>



    <div class="mb-4">
        <div>
            @if ($record->orderProducts->isEmpty())
                <p class="text-gray-500">No products found for this order</p>
            @else
                <div class="products-table-container" x-data="{
                    isDown: false,
                    startX: null,
                    scrollLeft: null,
                    handleMouseDown(e) {
                        this.isDown = true;
                        this.$el.style.cursor = 'grabbing';
                        this.startX = e.pageX - this.$el.offsetLeft;
                        this.scrollLeft = this.$el.scrollLeft;
                    },
                    handleMouseLeave() {
                        this.isDown = false;
                        this.$el.style.cursor = 'grab';
                    },
                    handleMouseUp() {
                        this.isDown = false;
                        this.$el.style.cursor = 'grab';
                    },
                    handleMouseMove(e) {
                        if (!this.isDown) return;
                        e.preventDefault();
                        const x = e.pageX - this.$el.offsetLeft;
                        const walk = (x - this.startX) * 2;
                        this.$el.scrollLeft = this.scrollLeft - walk;
                    }
                }" @mousedown="handleMouseDown"
                    @mouseleave="handleMouseLeave" @mouseup="handleMouseUp" @mousemove="handleMouseMove">
                    <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-800 products-table-content">
                        <!-- header -->

                        <div
                            class="flex items-center justify-between w-full p-2 m-0 mt-0 border-b border-gray-200 product-item last:border-b-0 dark:border-gray-600">
                            <div class="grid w-full grid-cols-12">
                                <div
                                    class="flex items-center justify-center w-8 col-span-1 text-sm text-center text-gray-600 dark:text-gray-400 qty">
                                    #
                                </div>
                                <div class="flex flex-col items-start col-span-5 -ml-6 product-description">
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Description</p>
                                </div>
                                <!-- location -->
                                <div class="flex items-start col-span-3 text-sm text-gray-600 dark:text-gray-400">
                                    Location</div>
                                <div class="col-span-1 text-sm text-gray-600 dark:text-gray-400 product-shipped">
                                    Shipped
                                </div>

                                <!-- unit price -->
                                <div
                                    class="flex justify-center w-full col-span-1 text-sm text-center text-gray-600 dark:text-gray-400 product-price">
                                    Unit Price
                                </div>
                                <!-- total price -->
                                <div
                                    class="flex justify-center w-full col-span-1 text-sm text-center text-gray-600 dark:text-gray-400 product-total-price">
                                    Amount
                                </div>

                            </div>

                        </div>

                        <!-- end header -->
                        @foreach ($record->orderProducts as $orderProduct)
                            <div
                                class="flex items-center justify-between w-full p-2 m-0 mt-0 border-b border-gray-200 product-item last:border-b-0 dark:border-gray-600">
                                <div class="grid w-full grid-cols-12">
                                    <div
                                        class="flex items-center justify-center w-8 col-span-1 text-center text-black border-r border-gray-200 dark:text-gray-200 qty dark:border-gray-600">
                                        @if ($orderProduct->fill_load)
                                            <p class="font-medium">FL</p>
                                        @else
                                            <p class="font-medium">{{ $orderProduct->quantity }}</p>
                                        @endif
                                    </div>
                                    <div class="flex flex-col items-start col-span-5 -ml-6 product-description">
                                        @if ($orderProduct->is_custom_product)
                                            <p class="font-medium text-black dark:text-gray-200">
                                                {{ $orderProduct->custom_description }}</p>
                                        @elseif ($orderProduct->product)
                                            <p class="font-medium text-black dark:text-gray-200">
                                                {{ $orderProduct->product->sku }}</p>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                {{ $orderProduct->product->name }}</p>
                                        @else
                                            <p class="font-medium text-red-600 dark:text-red-400">
                                                Product not found
                                            </p>
                                        @endif

                                        @if ($orderProduct->notes)
                                            <p class="text-sm text-gray-500">{{ $orderProduct->notes }}</p>
                                        @endif
                                    </div>
                                    <div class="flex items-start col-span-3 text-sm text-gray-600 dark:text-gray-400">
                                        {{ $orderProduct->location }}</div>
                                    <!-- quantity delivered -->
                                    <div class="col-span-1 text-center product-shipped">
                                        {{ $orderProduct->quantity_delivered }}
                                    </div>
                                    <!-- unit price -->
                                    <div class="flex justify-center w-full col-span-1 text-center product-price">
                                        @if ($orderProduct->price > 0)
                                            ${{ number_format($orderProduct->price, 2) }}
                                        @endif
                                    </div>
                                    <!-- total price -->
                                    <div class="flex justify-center w-full col-span-1 text-center product-total-price">
                                        @if ($orderProduct->quantity_delivered && $orderProduct->price > 0)
                                            ${{ number_format($orderProduct->price * $orderProduct->quantity_delivered, 2) }}
                                        @elseif($orderProduct->quantity && $orderProduct->price > 0)
                                            ${{ number_format($orderProduct->price * $orderProduct->quantity, 2) }}
                                        @endif
                                    </div>

                                </div>

                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
    <div class="grid grid-cols-4 gap-4 px-4">
        <!-- Delivery Info  -->
        <div class="flex flex-col items-start delivery-date">
            <p class="text-sm text-gray-600 dark:text-gray-400">Assigned Date</p>
            <p>{{ optional($record->assigned_delivery_date)->format('D m/d/Y') }}</p>
        </div>
        <div class="delivery-time">
            <p class="text-sm text-gray-600 dark:text-gray-400">Requested Delivery Time</p>
            <p>{{ optional($record->delivery_time)->format('g:i A') }}</p>
        </div>
        <div class="service-date">
            <p class="text-sm text-gray-600 dark:text-gray-400">Service Date</p>
            <p>{{ optional($record->service_date)->format('D m/d/Y') }}</p>
        </div>
        <div class="service-time">
            <p class="text-sm text-gray-600 dark:text-gray-400">Service Time</p>
            <p>{{ optional($record->service_time)->format('g:i A') }}</p>
        </div>
    </div>

    @if ($record->special_instructions)
        <div class="p-4 mt-4 rounded-lg bg-yellow-50">
            <h3 class="mb-2 font-medium">Notes</h3>
            <p class="text-sm">{{ $record->special_instructions }}</p>
        </div>
    @endif


    @if ($record->status == 'delivered' || $record->status == 'completed' || $record->status == 'picked_up' || $record->status == 'invoiced')
    <div class="grid grid-cols-2 gap-4 bg-gray-50 p-2 rounded-lg">

        @if ($record->delivered_at)
        <div class="p-4 mt-4 rounded-lg bg-yellow-50">
            <h3 class="mb-2 font-medium">Delivered At</h3>
            <p class="text-sm">{{ $record->delivered_at?->format('D m/d/Y g:i A') }}</p>
        </div>
        @endif

        @if ($record->signature_path)
        <div class="p-4 mt-4 rounded-lg bg-yellow-50">
            <h3 class="mb-2 font-medium">Signature</h3>
            <img src="{{ Storage::disk('r2')->url($record->signature_path) }}" alt="Signature" class="w-full h-auto">
        </div>
        @endif
        
        @if ($record->delivery_notes)
        <div class="p-4 mt-4 rounded-lg bg-yellow-50">
            <h3 class="mb-2 font-medium">Delivery Notes</h3>
            <p class="text-sm">{{ $record->delivery_notes }}</p>
        </div>
        @endif

    </div>
    @endif
</div>
