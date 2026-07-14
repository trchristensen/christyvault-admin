@php
    $deliveryPhotoViewerItems = $deliveryPhotos
        ->map(
            fn($photo) => [
                'url' => $photo->url,
                'thumbnailUrl' => $photo->thumbnail_url,
                'displayUrl' => $photo->display_url,
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
        <x-delivery-photo-viewer :photos="$deliveryPhotoViewerItems"
            class="p-4 mt-4 rounded-lg bg-blue-50 dark:bg-blue-950/20">
            <div class="flex items-center justify-between gap-4 mb-3">
                <h3 class="font-medium text-blue-950 dark:text-blue-100">Delivery Photos</h3>
                <span class="text-xs font-semibold text-blue-700 dark:text-blue-200">
                    {{ $deliveryPhotos->count() }} {{ \Illuminate\Support\Str::plural('photo', $deliveryPhotos->count()) }}
                </span>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                @foreach ($deliveryPhotos as $photo)
                    @php
                        $thumbnailUrl = $deliveryPhotoViewerItems[$loop->index]['thumbnailUrl'] ?? $photo->thumbnail_url;
                    @endphp

                    <div
                        class="relative overflow-hidden rounded-lg border border-blue-100 bg-white shadow-sm dark:border-blue-900 dark:bg-gray-900">
                        <button type="button"
                            class="block w-full text-left transition hover:shadow-md"
                            x-on:click="openDeliveryPhotoViewer({{ $loop->index }})"
                            title="{{ $photo->original_filename ?? 'Delivery photo' }}">
                            <img src="{{ $thumbnailUrl }}" alt="Delivery photo for {{ $order->order_number }}"
                                loading="lazy" decoding="async"
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

        </x-delivery-photo-viewer>
    @endif

    <x-filament-actions::modals />
</div>
