<div class="space-y-6">
    {{-- Order Details Section --}}
    <div class="bg-white dark:bg-gray-900">
        <div>
            <h3 class="text-lg font-medium">Order Details</h3>
            <div class="mt-4 grid grid-cols-3 gap-4">
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Supplier</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $record->supplier->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                    <dd class="mt-1">
                        <x-filament::badge
                            :color="match($record->status) {
                                'draft' => 'warning',
                                'submitted' => 'primary',
                                'received' => 'success',
                                'awaiting_invoice' => 'info',
                                'cancelled' => 'danger',
                                'completed' => 'success',
                                default => 'gray',
                            }"
                        >
                            {{ ucfirst($record->status) }}
                        </x-filament::badge>
                    </dd>
                </div>
                @if($record->supplier->name === 'Wilbert')
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Liner Load</dt>
                        <dd class="mt-1">
                            <x-filament::icon
                                :icon="$record->is_liner_load ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle'"
                                :class="$record->is_liner_load ? 'text-success-500' : 'text-danger-500'"
                            />
                        </dd>
                    </div>
                @endif
            </div>
            <div class="mt-4 grid grid-cols-3 gap-4">
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Order Date</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $record->order_date?->format('M j, Y') }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Expected Delivery</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $record->expected_delivery_date?->format('M j, Y') }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Received Date</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $record->received_date?->format('M j, Y') }}</dd>
                </div>
            </div>
            <div class="mt-4">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Amount</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">${{ number_format($record->total_amount, 2) }}</dd>
            </div>
            @if($record->notes)
                <div class="mt-4">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Notes</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white whitespace-pre-wrap">{{ $record->notes }}</dd>
                </div>
            @endif
        </div>
    </div>

    {{-- Items Section --}}
    <div class="bg-white dark:bg-gray-900">
        <div>
            <h3 class="text-lg font-medium">Items</h3>
            <div class="mt-4 divide-y divide-gray-200 dark:divide-gray-800">
                @foreach($record->items as $item)
                    <div class="py-4">
                        <div class="grid grid-cols-4 gap-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Item</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $item->name }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Quantity</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $item->pivot->quantity }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Unit Price</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-white">${{ number_format($item->pivot->unit_price, 2) }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Price</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-white">${{ number_format($item->pivot->total_price, 2) }}</dd>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Additional Information Section --}}
    <div class="bg-white dark:bg-gray-900">
        <div>
            <h3 class="text-lg font-medium">Additional Information</h3>
            <div class="mt-4 grid grid-cols-2 gap-4">
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created By</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $record->createdBy?->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created At</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $record->created_at?->format('M j, Y g:i A') }}</dd>
                </div>
            </div>
        </div>
    </div>
</div> 