@php
    $deliveryPhotoViewerItems = $deliveryPhotos
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

<div>
    <style>
        [x-cloak] {
            display: none !important;
        }

        .delivery-photo-delete-button {
            position: absolute;
            top: .5rem;
            right: .5rem;
            z-index: 10;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.75rem;
            height: 1.75rem;
            border-radius: 9999px;
            color: #fff;
            background: rgba(220, 38, 38, .95);
            box-shadow: 0 8px 20px rgba(0, 0, 0, .18);
            transition: background .15s ease, transform .15s ease;
        }

        .delivery-photo-delete-button:hover,
        .delivery-photo-delete-button:focus-visible {
            background: #b91c1c;
            transform: scale(1.04);
        }

        .delivery-photo-delete-button svg {
            width: 1rem;
            height: 1rem;
        }
    </style>

    @if ($deliveryPhotos->isNotEmpty())
        <div class="p-4 mt-4 rounded-lg bg-blue-50 dark:bg-blue-950/20" x-data="{
            deliveryPhotoViewerOpen: false,
            deliveryPhotoViewerPhotos: @js($deliveryPhotoViewerItems),
            deliveryPhotoViewerIndex: 0,
            openDeliveryPhotoViewer(index = 0) {
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
            <div class="flex items-center justify-between gap-4 mb-3">
                <h3 class="font-medium text-blue-950 dark:text-blue-100">Delivery Photos</h3>
                <span class="text-xs font-semibold text-blue-700 dark:text-blue-200">
                    {{ $deliveryPhotos->count() }} {{ \Illuminate\Support\Str::plural('photo', $deliveryPhotos->count()) }}
                </span>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                @foreach ($deliveryPhotos as $photo)
                    @php
                        $thumbnailUrl = $deliveryPhotoViewerItems[$loop->index]['url'] ?? $photo->url;
                    @endphp

                    <div
                        class="relative overflow-hidden rounded-lg border border-blue-100 bg-white shadow-sm dark:border-blue-900 dark:bg-gray-900">
                        <button type="button"
                            class="block w-full text-left transition hover:shadow-md"
                            x-on:click="openDeliveryPhotoViewer({{ $loop->index }})"
                            title="{{ $photo->original_filename ?? 'Delivery photo' }}">
                            <img src="{{ $thumbnailUrl }}" alt="Delivery photo for {{ $order->order_number }}"
                                class="h-32 w-full object-cover">
                            <div class="space-y-1 p-2 text-xs text-gray-600 dark:text-gray-300">
                                <div class="font-semibold text-gray-800 dark:text-gray-100">
                                    {{ $photo->uploadedBy?->name ?? 'Unknown uploader' }}
                                </div>
                                <div>{{ $photo->created_at?->format('M j, Y g:i A') }}</div>
                                @if ($photo->notes)
                                    <div class="text-gray-500 dark:text-gray-400">{{ $photo->notes }}</div>
                                @endif
                            </div>
                        </button>

                        @if ($canDeletePhotos)
                            <button type="button" class="delivery-photo-delete-button"
                                x-on:click.stop="$wire.mountAction('deleteDeliveryPhoto', { photo: @js($photo->getKey()) })"
                                title="Delete delivery photo" aria-label="Delete delivery photo">
                                <x-heroicon-o-x-mark />
                            </button>
                        @endif
                    </div>
                @endforeach
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
    @endif

    <x-filament-actions::modals />
</div>
