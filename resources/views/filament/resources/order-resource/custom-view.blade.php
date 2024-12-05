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

    <div class="p-4 mb-4 rounded-lg bg-gray-50">
        <h3 class="mb-2 font-bold text-gray-400 dark:text-gray-600">SOLD TO</h3>
        <div class="grid grid-cols-2 gap-4">
            <div class="grid grid-cols-1 gap-2">

                <p class="font-medium">{{ $record->customer->name }}</p>
                <p class="font-medium">{{ $record->location->full_address }}</p>
                
                @if ($record->customer->phone)
                    <div class="flex items-center gap-2">
                            <x-heroicon-o-phone class="w-4 h-4" />
                        <p>{{ $record->customer->phone}}</p>
                    </div>
                @endif
            </div>
            <div>
                <p class="text-sm text-gray-600">Requested Date</p>
                <p class="font-medium">{{ $record->requested_delivery_date?->format('M j, Y') }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-600">Assigned Date</p>
                <p class="font-medium">{{ $record->assigned_delivery_date?->format('M j, Y') }}</p>
            </div>
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
                <h3 class="mb-2 font-medium">Products</h3>
                <div class="space-y-2 bg-gray-50 p-2 rounded-lg">
                    @foreach ($record->orderProducts as $orderProduct)
                        <div class="flex items-center justify-between p-2 border-b nth-child(last) border-gray-200 dark:border-gray-800">
                            <div class="flex items-center gap-2">
                                <div class="qty min-w-6 border-r border-gray-200 dark:border-gray-800 flex items-center justify-center text-center">
                                    @if ($orderProduct->fill_load)
                                        <span class="font-medium">Fill Load</span>
                                    @else
                                        <span class="font-medium">{{ $orderProduct->quantity }}</span>
                                    @endif
                                </div>
                                <span>{{ $orderProduct->product->sku }}</span>
                                <span>{{ $orderProduct->product->name }}</span>
                        </div>
                        @if ($orderProduct->notes)
                            <span class="text-sm text-gray-500">{{ $orderProduct->notes }}</span>
                        @endif
                    </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @if ($record->special_instructions)
        <div class="p-4 rounded-lg bg-yellow-50">
            <h3 class="mb-2 font-medium">Special Instructions</h3>
            <p class="text-sm">{{ $record->special_instructions }}</p>
        </div>
    @endif
</div>
