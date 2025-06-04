<div>
    @if($showModal)
        <div 
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            style="background-color: rgba(0, 0, 0, 0.5);"
            wire:click="closeModal"
        >
            <div 
                wire:click.stop
                class="bg-white dark:bg-gray-900 rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-hidden relative flex flex-col"
                style="min-width: min(90vw, 800px);"
            >
                <!-- Sticky Header -->
                <div class="sticky top-0 z-20 flex items-center justify-between p-6 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 rounded-t-lg">
                    <div class="flex items-center space-x-4">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                            View order
                        </h2>
                        @if($order)
                            <div class="flex items-center space-x-2">
                                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $order->order_number }}</span>
                                <span class="text-sm text-gray-400 dark:text-gray-500">•</span>
                                <span class="text-sm font-medium text-gray-600 dark:text-gray-300">
                                    {{ $order->location->city ?? 'Unknown' }}, {{ $order->location->state ?? '' }}
                                </span>
                                <div class="px-2 py-1 text-xs font-medium rounded-full border"
                                     style="background-color: {{ $order->status_color['background'] ?? '#f3f4f6' }}; 
                                            color: {{ $order->status_color['text'] ?? '#374151' }}; 
                                            border-color: {{ $order->status_color['border'] ?? '#d1d5db' }}">
                                    {{ ucfirst($order->status) }}
                                </div>
                            </div>
                        @endif
                    </div>
                    <button 
                        wire:click="closeModal"
                        class="p-2 text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 transition-colors"
                        title="Close"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Scrollable Content -->
                <div class="flex-1 overflow-y-auto">
                    @if($order)
                        @include('filament.resources.order-resource.custom-view', ['record' => $order])
                    @endif
                </div>

                <!-- Sticky Footer with Actions -->
                <div class="sticky bottom-0 z-20 flex items-center justify-end space-x-2 p-4 bg-gray-50 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 rounded-b-lg">
                    @if($order)
                        <!-- Edit Button -->
                        <button 
                            wire:click="editOrder"
                            class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:outline-none focus:border-blue-700 focus:ring focus:ring-blue-200 active:bg-blue-600 disabled:opacity-25 transition"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            Edit
                        </button>

                        <!-- Duplicate Button -->
                        <button 
                            wire:click="duplicateOrder"
                            class="inline-flex items-center px-4 py-2 bg-yellow-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-500 focus:outline-none focus:border-yellow-700 focus:ring focus:ring-yellow-200 active:bg-yellow-600 disabled:opacity-25 transition"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2H9a2 2 0 00-2 2v8a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                            </svg>
                            Duplicate Order
                        </button>

                        <!-- Print Delivery Tag Button -->
                        <button 
                            wire:click="printDeliveryTag"
                            class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-500 focus:outline-none focus:border-green-700 focus:ring focus:ring-green-200 active:bg-green-600 disabled:opacity-25 transition"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                            </svg>
                            Print Delivery Tag
                        </button>

                        <!-- Preview Delivery Tag Button -->
                        <button 
                            wire:click="previewDeliveryTag"
                            class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500 focus:outline-none focus:border-gray-700 focus:ring focus:ring-gray-200 active:bg-gray-600 disabled:opacity-25 transition"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                            Preview Delivery Tag
                        </button>

                        <!-- Delete Button -->
                        <button 
                            wire:click="deleteOrder"
                            wire:confirm="Are you sure you want to delete this order?"
                            class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 focus:outline-none focus:border-red-700 focus:ring focus:ring-red-200 active:bg-red-600 disabled:opacity-25 transition"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            Delete
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- JavaScript to handle opening URLs in new tabs -->
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('open-url', (data) => {
                window.open(data.url, '_blank');
            });
        });
    </script>
</div>