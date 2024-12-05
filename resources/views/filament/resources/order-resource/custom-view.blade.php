<div class="p-4 order-container">
    <div class="flex items-start justify-between mb-4">
        <div>
            <h2 class="font-bold text-gray-400 dark:text-gray-600">{{ $record->order_number }}</h2>
            
        </div>
        <div class="px-2 py-1 text-sm font-medium text-gray-800 border rounded-full border-gray-800/10"
            style="background-color: {{ $record->status_color }}">
            {{ ucfirst($record->status) }}
        </div>
    </div>

    <div class="p-4 mb-4 rounded-lg bg-gray-50 relative flex">
         <div class="flex flex-col justify-center items-center mr-4">
            <div class="text-gray-400 dark:text-gray-600 flex flex-col items-center gap-0 font-lighter w-8 justify-start border-r border-gray-200 dark:border-gray-800">
                <span>S</span>
                <span>O</span>
                <span>L</span>
                <span>D</span>
                <span class="mb-2"></span>
                <span>T</span>
                <span>O</span>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4 justify-between w-full">
            
            <div>
               
                <p class="font-bold">{{ $record->customer->name }}</p>
                <p>{{ $record->location->full_address }}</p>
                
                @if ($record->customer->phone)
                    <div class="flex items-center gap-2">
                            <x-heroicon-o-phone class="w-4 h-4" />
                        <p>{{ $record->customer->phone}}</p>
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
            <!-- <div>
                <p class="text-sm text-gray-600">Assigned Date</p>
                <p class="font-medium">{{ $record->assigned_delivery_date?->format('M j, Y') }}</p>
            </div> -->
            <!-- <div class="col-span-2">
                <p class="text-sm text-gray-600">Delivery Address</p>
                <p class="font-medium">{{ $record->location->full_address }}</p>
            </div> -->
        </div>
    </div>



    <div class="mb-4">
        <div>
            @if ($record->orderProducts->isEmpty())
                <p class="text-gray-500">No products found for this order</p>
            @else
                <div class="bg-gray-50 p-2 rounded-lg">
                    <!-- header -->

                     <div class="product-item m-0 mt-0 flex items-center justify-between p-2 border-b last:border-b-0 border-gray-200 dark:border-gray-800 w-full">
                            <div class="w-full grid grid-cols-12">
                                <div class="qty col-span-1 w-8 text-sm text-gray-600 flex items-center justify-center text-center">
                                   #
                                </div>
                                <div class="product-description col-span-5 flex flex-col items-start">
                                    <p class="text-sm text-gray-600">Description</p>
                                </div>
                                <!-- location -->
                                <div class="text-sm text-gray-600 items-start col-span-3 flex items-start">Location</div>
                                <div class="product-shipped col-span-1 text-sm text-gray-600">
                                   Shipped
                                </div>
                              
                                <!-- unit price -->
                                <div class="product-price col-span-1 text-center justify-center flex w-full text-sm text-gray-600">
                                    Unit Price
                                </div>
                                <!-- total price -->
                                <div class="product-total-price col-span-1 text-center justify-center flex w-full text-sm text-gray-600">
                                    Amount
                                </div>

                        </div>
                        
                    </div>

                    <!-- end header -->
                    @foreach ($record->orderProducts as $orderProduct)
                        <div class="product-item m-0 mt-0 flex items-center justify-between p-2 border-b last:border-b-0 border-gray-200 dark:border-gray-800 w-full">
                            <div class="w-full grid grid-cols-12">
                                <div class="qty col-span-1 w-8 border-r border-gray-200 dark:border-gray-800 flex items-center justify-center text-center">
                                    @if ($orderProduct->fill_load)
                                        <p class="font-medium">FL</p>
                                    @else
                                        <p class="font-medium">{{ $orderProduct->quantity }}</p>
                                    @endif
                                </div>
                                <div class="product-description col-span-5 flex flex-col items-start">
                                    <p class="font-medium">{{ $orderProduct->product->sku }}</p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $orderProduct->product->name }}</p>

                                    @if ($orderProduct->notes)
                                        <p class="text-sm text-gray-500">{{ $orderProduct->notes }}</p>
                                    @endif
                                </div>
                                <div class="text-sm text-gray-600 items-start col-span-3 flex">{{ $orderProduct->location }}</div>
                                <!-- quantity delivered -->
                                <div class="product-shipped col-span-1 text-center">
                                    {{ $orderProduct->quantity_delivered }}
                                </div>
                                <!-- unit price -->
                                <div class="product-price col-span-1 text-center justify-center flex w-full">
                                    @if ($orderProduct->price > 0)
                                        ${{ number_format($orderProduct->price, 2) }}
                                    @endif
                                </div>
                                <!-- total price -->
                                <div class="product-total-price col-span-1 text-center justify-center flex w-full">
                                    @if ($orderProduct->quantity_delivered  && $orderProduct->price > 0)
                                        ${{ number_format($orderProduct->price * $orderProduct->quantity_delivered, 2) }}
                                    @elseif($orderProduct->quantity && $orderProduct->price > 0)
                                       ${{ number_format($orderProduct->price * $orderProduct->quantity, 2) }}
                                    @endif
                                </div>

                        </div>
                        
                    </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
    <div class="grid grid-cols-4 gap-4">
        <!-- Delivery Info  -->
        <div class="delivery-date flex flex-col items-start">
            <p class="text-sm text-gray-600">Assigned Date</p>
            <p>{{ optional($record->assigned_delivery_date)->format('D m/d/Y') }}</p>
        </div>
        <div class="delivery-time">
            <p class="text-sm text-gray-600">Delivery Time</p>
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
        <div class="p-4 rounded-lg bg-yellow-50">
            <h3 class="mb-2 font-medium">Special Instructions</h3>
            <p class="text-sm">{{ $record->special_instructions }}</p>
        </div>
    @endif
</div>
