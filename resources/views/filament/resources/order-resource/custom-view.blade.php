<div class="p-4 order-container">
    <div class="flex items-start justify-between mb-4">
        <div>
            <h2 class="text-xl font-bold">{{ $record->order_number }}</h2>
            <p class="text-gray-600">{{ $record->customer->name }}</p>
        </div>
        <div class="px-2 py-1 text-sm rounded-full" style="background-color: {{ $record->status_color }}">
            {{ ucfirst($record->status) }}
        </div>
    </div>

    <div class="p-4 mb-4 rounded-lg bg-gray-50">
        <h3 class="mb-2 font-medium">Delivery Information</h3>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <p class="text-sm text-gray-600">Requested Date</p>
                <p class="font-medium">{{ $record->requested_delivery_date?->format('M j, Y') }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-600">Assigned Date</p>
                <p class="font-medium">{{ $record->assigned_delivery_date?->format('M j, Y') }}</p>
            </div>
            <div class="col-span-2">
                <p class="text-sm text-gray-600">Delivery Address</p>
                <p class="font-medium">{{ $record->location->full_address }}</p>
            </div>
        </div>
    </div>

    <div class="mb-4">
        <div class="space-y-2">
            @if ($record->orderProducts->isEmpty())
                <p class="text-gray-500">No products found for this order</p>
            @else
                <h3 class="mb-2 font-medium">Products</h3>
                @foreach ($record->orderProducts as $orderProduct)
                    <div class="flex items-center justify-between p-2 bg-gray-50">
                        <div>
                            @if ($orderProduct->fill_load)
                                <span class="font-medium">Fill Load</span>
                            @else
                                <span class="font-medium">{{ $orderProduct->quantity }}x</span>
                            @endif
                            <span class="text-gray-500">{{ $orderProduct->product->sku }}</span>
                            <span>{{ $orderProduct->product->name }}</span>
                        </div>
                        @if ($orderProduct->notes)
                            <span class="text-sm text-gray-500">{{ $orderProduct->notes }}</span>
                        @endif
                    </div>
                @endforeach
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
