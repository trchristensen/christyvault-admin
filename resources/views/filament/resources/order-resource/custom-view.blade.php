@php
    use Propaganistas\LaravelPhone\PhoneNumber;
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
        max-width: calc(100vw - 4rem); /* Account for modal and content padding */
    }

    .products-table-content {
        position: relative;
        min-width: 848px;
        width: max-content;
    }

    @media (max-width: 768px) {
        .grid-cols-2 {
            grid-template-columns: 1fr;
        }
        
        .grid-cols-4 {
            grid-template-columns: 1fr 1fr;
        }
    }

    /* Override Filament's modal styles for better mobile responsiveness */
    .fi-modal-window {
        max-width: calc(100vw - 2rem) !important; /* Account for viewport padding */
        width: calc(100vw - 2rem) !important;
        /* margin: 1rem !important; */
    }

    @media (min-width: 640px) {
        .fi-modal-window {
            max-width: min(90vw, 1200px) !important; /* Cap the maximum width */
            width: auto !important;
            /* margin: 2rem auto !important; */
        }
    }

    /* Ensure modal content doesn't overflow */
    .fi-modal-content {
        max-width: 100%;
        padding: 1rem;
    }
</style>
<div class="p-4 order-container">
    <div class="flex items-start justify-between mb-4">
        <div>
            <h2 class="font-bold text-gray-400 dark:text-gray-600">{{ $record->order_number }}</h2>

        </div>
        <div class="flex items-center gap-4">
            {{-- get the fucking label, not the value --}}
            <p class="text-sm font-bold text-gray-600">
                {{ App\Enums\PlantLocation::from($record->plant_location)->getLabel() }}</p>
            <div class="px-2 py-1 text-sm font-medium text-gray-800 border rounded-full"
                style="background-color: {{ $record->status_color['background'] }}; color: {{ $record->status_color['text'] }}; border-color: {{ $record->status_color['border'] }}">
                {{ ucfirst($record->status) }}
            </div>
        </div>
    </div>

    <div class="relative flex p-4 mb-4 rounded-lg bg-gray-50">
        <div class="flex flex-col items-center justify-center mr-4">
            <div
                class="flex flex-col items-center justify-start w-8 gap-0 text-gray-400 border-r border-gray-200 dark:text-gray-600 font-lighter dark:border-gray-800">
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
                <p class="font-bold">{{ optional($record->location)->name ?? 'N/A' }}</p>
                <p>{{ optional($record->location)->full_address ?? 'N/A' }}</p>

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
                <div>
                    <p class="text-sm text-gray-600">Requested Date</p>
                    <p class="font-medium">{{ $record->requested_delivery_date?->format('M j, Y') }}</p>
                </div>
                <div>
                    <!-- date of order -->
                    <p class="text-sm text-gray-600">Date of Order</p>
                    <p class="font-medium">{{ $record->order_date?->format('M j, Y') }}</p>
                </div>
            </div>

        </div>
    </div>



    <div class="mb-4">
        <div>
            @if ($record->orderProducts->isEmpty())
                <p class="text-gray-500">No products found for this order</p>
            @else
            <div class="products-table-container">
                <div class="p-2 rounded-lg bg-gray-50 products-table-content">
                    <!-- header -->

                    <div
                        class="flex items-center justify-between w-full p-2 m-0 mt-0 border-b border-gray-200 product-item last:border-b-0 dark:border-gray-800">
                        <div class="grid w-full grid-cols-12">
                            <div
                                class="flex items-center justify-center w-8 col-span-1 text-sm text-center text-gray-600 qty">
                                #
                            </div>
                            <div class="flex flex-col items-start col-span-5 -ml-6 product-description">
                                <p class="text-sm text-gray-600">Description</p>
                            </div>
                            <!-- location -->
                            <div class="flex items-start col-span-3 text-sm text-gray-600">Location</div>
                            <div class="col-span-1 text-sm text-gray-600 product-shipped">
                                Shipped
                            </div>

                            <!-- unit price -->
                            <div
                                class="flex justify-center w-full col-span-1 text-sm text-center text-gray-600 product-price">
                                Unit Price
                            </div>
                            <!-- total price -->
                            <div
                                class="flex justify-center w-full col-span-1 text-sm text-center text-gray-600 product-total-price">
                                Amount
                            </div>

                        </div>

                    </div>

                    <!-- end header -->
                    @foreach ($record->orderProducts as $orderProduct)
                        <div
                            class="flex items-center justify-between w-full p-2 m-0 mt-0 border-b border-gray-200 product-item last:border-b-0 dark:border-gray-800">
                            <div class="grid w-full grid-cols-12">
                                <div
                                    class="flex items-center justify-center w-8 col-span-1 text-center border-r border-gray-200 qty dark:border-gray-800">
                                    @if ($orderProduct->fill_load)
                                        <p class="font-medium">FL</p>
                                    @else
                                        <p class="font-medium">{{ $orderProduct->quantity }}</p>
                                    @endif
                                </div>
                                <div class="flex flex-col items-start col-span-5 -ml-6 product-description">
                                    <p class="font-medium">{{ $orderProduct->product->sku }}</p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ $orderProduct->product->name }}</p>

                                    @if ($orderProduct->notes)
                                        <p class="text-sm text-gray-500">{{ $orderProduct->notes }}</p>
                                    @endif
                                </div>
                                <div class="flex items-start col-span-3 text-sm text-gray-600">
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
    <div class="grid grid-cols-4 gap-4">
        <!-- Delivery Info  -->
        <div class="flex flex-col items-start delivery-date">
            <p class="text-sm text-gray-600">Assigned Date</p>
            <p>{{ optional($record->assigned_delivery_date)->format('D m/d/Y') }}</p>
        </div>
        <div class="delivery-time">
            <p class="text-sm text-gray-600">Requested Delivery Time</p>
            <p>{{ optional($record->delivery_time)->format('g:i A') }}</p>
        </div>
        <div class="service-date">
            <p class="text-sm text-gray-600">Service Date</p>
            <p>{{ optional($record->service_date)->format('D m/d/Y') }}</p>
        </div>
        <div class="service-time">
            <p class="text-sm text-gray-600">Service Time</p>
            <p>{{ optional($record->service_time)->format('g:i A') }}</p>
        </div>
    </div>

    @if ($record->special_instructions)
        <div class="p-4 mt-4 rounded-lg bg-yellow-50">
            <h3 class="mb-2 font-medium">Instructions</h3>
            <p class="text-sm">{{ $record->special_instructions }}</p>
        </div>
    @endif
</div>
