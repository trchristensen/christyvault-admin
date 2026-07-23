<div>
    @if($showModal)
        <div 
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            style="background-color: rgba(0, 0, 0, 0.5);"
            wire:click="closeModal"
        >
            <div 
                wire:click.stop
                class="bg-white dark:bg-gray-900 rounded-lg shadow-xl w-full {{ $showLoadSummary ? 'max-w-7xl' : 'max-w-4xl' }} max-h-[90vh] overflow-hidden relative flex flex-col"
                style="min-width: min(90vw, 800px);"
            >
                <!-- Sticky Header -->
                <div class="sticky top-0 z-20 flex items-center justify-between p-6 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 rounded-t-lg">
                    <div class="flex items-center space-x-4">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                            {{ $showLoadSummary ? 'Load summary' : 'View order' }}
                        </h2>
                        @if($order)
                            <div class="flex items-center space-x-2">
                                @if($showLoadSummary)
                                    <span class="text-sm font-medium text-gray-600 dark:text-gray-300">{{ $order->trip?->trip_number }}</span>
                                    <span class="text-sm text-gray-400 dark:text-gray-500">•</span>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $order->order_number }}</span>
                                @else
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
                                @endif
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
                        @if($showLoadSummary && $loadSummary)
                            <div class="p-6">
                                @include('filament.resources.trip-resource.load-summary', $loadSummary)
                            </div>
                        @else
                            @include('filament.resources.order-resource.custom-view', ['record' => $order])
                        @endif
                    @endif
                </div>

                <!-- Sticky Footer with Actions -->
                <div class="sticky bottom-0 z-20 flex items-center justify-end p-4 bg-gray-50 border-t border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-b-lg">
                    @if($order)
                        @if($showLoadSummary)
                            <button
                                type="button"
                                wire:click="backToOrder"
                                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-gray-700 transition bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-900 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                </svg>
                                Back to order
                            </button>
                        @else
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                <div class="inline-flex overflow-hidden bg-white border border-gray-300 divide-x divide-gray-300 rounded-lg shadow-sm dark:bg-gray-900 dark:border-gray-600 dark:divide-gray-600">
                                    @if($order->trip)
                                        <button
                                            type="button"
                                            wire:click="editDeliveryTrip"
                                            class="p-2.5 text-gray-600 transition hover:bg-gray-50 hover:text-blue-600 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500 dark:text-gray-300 dark:hover:bg-gray-700"
                                            title="Edit delivery"
                                            aria-label="Edit delivery"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 17h8m-9-5h10l2 5H5l2-5zm2 5a2 2 0 11-4 0m14 0a2 2 0 11-4 0M7 12l1.5-5h7L17 12"></path>
                                            </svg>
                                        </button>
                                    @endif
                                    @if($this->canViewLoadSummary())
                                        <button
                                            type="button"
                                            wire:click="openLoadSummary"
                                            class="p-2.5 text-gray-600 transition hover:bg-gray-50 hover:text-blue-600 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500 dark:text-gray-300 dark:hover:bg-gray-700"
                                            title="Load summary"
                                            aria-label="Load summary"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M4 12h16M4 17h10"></path>
                                            </svg>
                                        </button>
                                    @endif
                                    <button
                                        type="button"
                                        wire:click="editOrder"
                                        class="p-2.5 text-gray-600 transition hover:bg-gray-50 hover:text-blue-600 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500 dark:text-gray-300 dark:hover:bg-gray-700"
                                        title="Edit order"
                                        aria-label="Edit order"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="printDeliveryTag"
                                        class="p-2.5 text-gray-600 transition hover:bg-gray-50 hover:text-green-600 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500 dark:text-gray-300 dark:hover:bg-gray-700"
                                        title="Print delivery tag"
                                        aria-label="Print delivery tag"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                                        </svg>
                                    </button>
                                </div>

                                <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
                                    <button
                                        type="button"
                                        @click="open = ! open"
                                        class="p-2.5 text-gray-600 transition bg-white border border-gray-300 rounded-lg shadow-sm hover:bg-gray-50 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-900 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                                        title="More actions"
                                        aria-label="More actions"
                                        :aria-expanded="open"
                                    >
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M12 8a2 2 0 100-4 2 2 0 000 4zm0 2a2 2 0 110 4 2 2 0 010-4zm0 6a2 2 0 110 4 2 2 0 010-4z"></path>
                                        </svg>
                                    </button>

                                    <div
                                        x-cloak
                                        x-show="open"
                                        x-transition.origin.bottom.right
                                        @click.outside="open = false"
                                        class="absolute right-0 z-50 w-56 py-1 mb-2 overflow-hidden bg-white border border-gray-200 rounded-xl shadow-xl bottom-full dark:bg-gray-900 dark:border-gray-700"
                                    >
                                        <button type="button" wire:click="duplicateOrder" @click="open = false" class="flex items-center w-full gap-3 px-4 py-3 text-sm text-left text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800">
                                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2H9a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                            Duplicate order
                                        </button>
                                        <button type="button" wire:click="previewDeliveryTag" @click="open = false" class="flex items-center w-full gap-3 px-4 py-3 text-sm text-left text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800">
                                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zm6 0c-1.5 4-5 7-9 7s-7.5-3-9-7c1.5-4 5-7 9-7s7.5 3 9 7z"></path></svg>
                                            Preview delivery tag
                                        </button>
                                        <div class="my-1 border-t border-gray-200 dark:border-gray-700"></div>
                                        <button type="button" wire:click="deleteOrder" wire:confirm="Are you sure you want to delete this order?" @click="open = false" class="flex items-center w-full gap-3 px-4 py-3 text-sm text-left text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/30">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            Delete order
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endif
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
