@php
    use Illuminate\Support\Facades\Storage;

    $attachments = collect($getState() ?? [])
        ->filter(fn ($path) => filled($path))
        ->values()
        ->map(function (string $path): array {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $imageExtensions = ['avif', 'bmp', 'gif', 'jpeg', 'jpg', 'png', 'svg', 'webp'];

            return [
                'extension' => $extension,
                'is_image' => in_array($extension, $imageExtensions, true),
                'is_pdf' => $extension === 'pdf',
                'name' => basename($path),
                'url' => Storage::disk('public')->url($path),
            ];
        });

    $photos = $attachments
        ->where('is_image', true)
        ->values()
        ->map(fn (array $photo, int $index): array => [
            ...$photo,
            'label' => 'Photo '.($index + 1),
        ]);
    $documents = $attachments
        ->where('is_image', false)
        ->values()
        ->map(fn (array $document, int $index): array => [
            ...$document,
            'label' => ($document['is_pdf'] ? 'PDF document ' : 'Document ').($index + 1),
        ]);
@endphp

<div class="space-y-8">
    @if ($photos->isNotEmpty())
        <div>
            <h3 class="mb-3 text-sm font-semibold text-gray-950 dark:text-white">Photos</h3>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($photos as $photo)
                    <article class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                        <a
                            href="{{ $photo['url'] }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="group block bg-gray-100 dark:bg-gray-950"
                            title="Open {{ $photo['label'] }} full size"
                        >
                            <img
                                src="{{ $photo['url'] }}"
                                alt="{{ $photo['label'] }}"
                                loading="lazy"
                                class="h-72 w-full object-contain transition duration-200 group-hover:scale-[1.02]"
                            >
                        </a>

                        <div class="flex items-center justify-between gap-3 p-3">
                            <div class="min-w-0">
                                <p class="font-medium text-gray-950 dark:text-white">{{ $photo['label'] }}</p>
                                <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $photo['name'] }}</p>
                            </div>

                            <div class="flex shrink-0 gap-2">
                                <x-filament::button
                                    tag="a"
                                    :href="$photo['url']"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    size="sm"
                                    color="gray"
                                    icon="heroicon-o-arrow-top-right-on-square"
                                >
                                    Open
                                </x-filament::button>
                                <x-filament::button
                                    tag="a"
                                    :href="$photo['url']"
                                    download
                                    size="sm"
                                    color="gray"
                                    icon="heroicon-o-arrow-down-tray"
                                >
                                    Download
                                </x-filament::button>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    @endif

    @if ($documents->isNotEmpty())
        <div class="space-y-5">
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Documents</h3>

            @foreach ($documents as $document)
                <article class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <div class="flex flex-wrap items-center justify-between gap-3 p-4">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="rounded-lg bg-primary-50 p-2 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                                <x-filament::icon icon="heroicon-o-document-text" class="h-6 w-6" />
                            </div>
                            <div class="min-w-0">
                                <p class="font-medium text-gray-950 dark:text-white">{{ $document['label'] }}</p>
                                <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $document['name'] }}</p>
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <x-filament::button
                                tag="a"
                                :href="$document['url']"
                                target="_blank"
                                rel="noopener noreferrer"
                                size="sm"
                                color="gray"
                                icon="heroicon-o-arrow-top-right-on-square"
                            >
                                Open
                            </x-filament::button>
                            <x-filament::button
                                tag="a"
                                :href="$document['url']"
                                download
                                size="sm"
                                color="gray"
                                icon="heroicon-o-arrow-down-tray"
                            >
                                Download
                            </x-filament::button>
                        </div>
                    </div>

                    @if ($document['is_pdf'])
                        <iframe
                            src="{{ $document['url'] }}#toolbar=1&navpanes=0"
                            title="Preview of {{ $document['label'] }}"
                            loading="lazy"
                            class="h-[70vh] min-h-[32rem] w-full border-0 border-t border-gray-200 dark:border-white/10"
                        ></iframe>
                    @endif
                </article>
            @endforeach
        </div>
    @endif

    @if ($attachments->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 px-6 py-10 text-center text-sm text-gray-500 dark:border-white/15 dark:text-gray-400">
            No photos or documents have been attached to this work order.
        </div>
    @endif
</div>
