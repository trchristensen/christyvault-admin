@props(['photos'])

<div {{ $attributes }} x-data="{
    deliveryPhotoViewerOpen: false,
    deliveryPhotoViewerPhotos: @js($photos),
    deliveryPhotoViewerIndex: 0,
    displayedPhotoUrl: null,
    displayingPreview: false,
    deliveryPhotoLoading: false,
    deliveryPhotoError: null,
    deliveryPhotoRequest: 0,
    preloadedDeliveryPhotos: new Map(),
    currentDeliveryPhoto() {
        return this.deliveryPhotoViewerPhotos[this.deliveryPhotoViewerIndex] || {};
    },
    normalizedDeliveryPhotoIndex(index) {
        const count = this.deliveryPhotoViewerPhotos.length;
        return count ? (index + count) % count : 0;
    },
    deliveryPhotoUrl(index) {
        const photo = this.deliveryPhotoViewerPhotos[this.normalizedDeliveryPhotoIndex(index)] || {};
        return photo.displayUrl || photo.url || null;
    },
    preloadDeliveryPhoto(index) {
        const url = this.deliveryPhotoUrl(index);
        if (!url) return Promise.reject(new Error('This photo is unavailable.'));
        if (this.preloadedDeliveryPhotos.has(url)) return this.preloadedDeliveryPhotos.get(url);

        const promise = new Promise((resolve, reject) => {
            const image = new Image();
            image.decoding = 'async';
            image.onload = async () => {
                try { await image.decode(); } catch (error) {}
                resolve(image);
            };
            image.onerror = () => reject(new Error('The photo could not be loaded.'));
            image.src = url;
        });

        this.preloadedDeliveryPhotos.set(url, promise);
        promise.catch(() => this.preloadedDeliveryPhotos.delete(url));
        return promise;
    },
    preloadAdjacentDeliveryPhotos(index) {
        if (this.deliveryPhotoViewerPhotos.length < 2) return;
        this.preloadDeliveryPhoto(index + 1).catch(() => {});
        this.preloadDeliveryPhoto(index - 1).catch(() => {});
    },
    async showDeliveryPhoto(index) {
        if (!this.deliveryPhotoViewerPhotos.length) return;

        const normalizedIndex = this.normalizedDeliveryPhotoIndex(index);
        const photo = this.deliveryPhotoViewerPhotos[normalizedIndex] || {};
        const targetUrl = photo.displayUrl || photo.url || null;
        const previewUrl = photo.thumbnailUrl || null;
        const request = ++this.deliveryPhotoRequest;

        this.deliveryPhotoViewerIndex = normalizedIndex;
        this.displayedPhotoUrl = previewUrl;
        this.displayingPreview = Boolean(previewUrl && previewUrl !== targetUrl);
        this.deliveryPhotoLoading = true;
        this.deliveryPhotoError = null;
        this.preloadAdjacentDeliveryPhotos(normalizedIndex);

        try {
            await this.preloadDeliveryPhoto(normalizedIndex);
            if (request !== this.deliveryPhotoRequest) return;
            this.displayedPhotoUrl = targetUrl;
            this.displayingPreview = false;
            this.deliveryPhotoLoading = false;
        } catch (error) {
            if (request !== this.deliveryPhotoRequest) return;
            this.deliveryPhotoLoading = false;
            this.deliveryPhotoError = error.message || 'The photo could not be loaded.';
        }
    },
    openDeliveryPhotoViewer(index = 0) {
        this.deliveryPhotoViewerOpen = true;
        document.body.style.overflow = 'hidden';
        this.showDeliveryPhoto(index);
    },
    closeDeliveryPhotoViewer() {
        this.deliveryPhotoViewerOpen = false;
        this.deliveryPhotoRequest++;
        document.body.style.overflow = '';
    },
    nextDeliveryPhoto() {
        this.showDeliveryPhoto(this.deliveryPhotoViewerIndex + 1);
    },
    previousDeliveryPhoto() {
        this.showDeliveryPhoto(this.deliveryPhotoViewerIndex - 1);
    },
    retryDeliveryPhoto() {
        const url = this.deliveryPhotoUrl(this.deliveryPhotoViewerIndex);
        if (url) this.preloadedDeliveryPhotos.delete(url);
        this.showDeliveryPhoto(this.deliveryPhotoViewerIndex);
    },
    destroy() {
        if (this.deliveryPhotoViewerOpen) document.body.style.overflow = '';
    },
}" x-on:keydown.escape.window="deliveryPhotoViewerOpen && closeDeliveryPhotoViewer()"
    x-on:keydown.arrow-right.window="deliveryPhotoViewerOpen && nextDeliveryPhoto()"
    x-on:keydown.arrow-left.window="deliveryPhotoViewerOpen && previousDeliveryPhoto()">
    {{ $slot }}

    <template x-teleport="body">
        <div x-cloak x-show="deliveryPhotoViewerOpen" x-transition.opacity
            class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/80 p-3"
            role="dialog" aria-modal="true" aria-label="Delivery photo viewer"
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

                <div class="relative flex min-h-[50vh] items-center justify-center overflow-hidden bg-gray-950">
                    <img x-show="displayedPhotoUrl" x-bind:src="displayedPhotoUrl"
                        x-bind:alt="currentDeliveryPhoto().title || 'Delivery photo'"
                        class="max-h-[70vh] w-auto max-w-full object-contain transition duration-200"
                        x-bind:class="displayingPreview ? 'scale-[1.02] blur-sm opacity-70' : 'scale-100 blur-0 opacity-100'">

                    <div x-show="deliveryPhotoLoading"
                        class="absolute inset-0 flex items-center justify-center bg-black/20" aria-live="polite">
                        <div class="flex items-center gap-2 rounded-full bg-black/70 px-4 py-2 text-sm font-medium text-white">
                            <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                            </svg>
                            Loading photo…
                        </div>
                    </div>

                    <div x-show="deliveryPhotoError"
                        class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-gray-950/90 p-6 text-center text-white"
                        aria-live="assertive">
                        <div x-text="deliveryPhotoError"></div>
                        <button type="button" class="rounded-lg bg-white px-4 py-2 font-semibold text-gray-950"
                            x-on:click="retryDeliveryPhoto()">Try again</button>
                    </div>

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
    </template>
</div>
