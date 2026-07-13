@php
    use Propaganistas\LaravelPhone\PhoneNumber;
    use Illuminate\Support\Facades\Storage;
@endphp
<!-- add css -->
<style>
    .order-container {
        box-sizing: border-box;
        color: #111827;
        background: #fff;
        font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        font-size: 1rem;
        line-height: 1.5;
        -webkit-text-size-adjust: 100%;
    }

    .order-container *,
    .order-container *::before,
    .order-container *::after {
        box-sizing: border-box;
    }

    .order-container :where(h1, h2, h3, h4, h5, h6, p, figure, blockquote, dl, dd) {
        margin: 0;
    }

    .order-container :where(h1, h2, h3, h4, h5, h6) {
        font-size: inherit;
        font-weight: inherit;
    }

    .order-container :where(a) {
        color: inherit;
        text-decoration: inherit;
    }

    .order-container :where(button, input, optgroup, select, textarea) {
        margin: 0;
        padding: 0;
        font: inherit;
        color: inherit;
        background: transparent;
        border: 0;
    }

    .order-container :where(button, [role="button"]) {
        cursor: pointer;
    }

    .order-container :where(img, svg, video, canvas, audio, iframe, embed, object) {
        display: block;
        vertical-align: middle;
    }

    .order-container :where(img, video) {
        max-width: 100%;
        height: auto;
    }

    .order-container :where(svg) {
        flex-shrink: 0;
    }

    .order-container .block {
        display: block;
    }

    .order-container .inline-block {
        display: inline-block;
    }

    .order-container .flex {
        display: flex;
    }

    .order-container .inline-flex {
        display: inline-flex;
    }

    .order-container .grid {
        display: grid;
    }

    .order-container .hidden {
        display: none;
    }

    .order-container .relative {
        position: relative;
    }

    .order-container .absolute {
        position: absolute;
    }

    .order-container .fixed {
        position: fixed;
    }

    .order-container .inset-0 {
        inset: 0;
    }

    .order-container .top-1\/2 {
        top: 50%;
    }

    .order-container .left-3 {
        left: .75rem;
    }

    .order-container .right-3 {
        right: .75rem;
    }

    .order-container .z-\[9999\] {
        z-index: 9999;
    }

    .order-container .w-full {
        width: 100%;
    }

    .order-container .w-auto {
        width: auto;
    }

    .order-container .w-3,
    .order-container svg.w-3 {
        width: .75rem;
    }

    .order-container .w-4,
    .order-container svg.w-4 {
        width: 1rem;
    }

    .order-container .w-6,
    .order-container svg.w-6 {
        width: 1.5rem;
    }

    .order-container .w-8 {
        width: 2rem;
    }

    .order-container .w-12,
    .order-container svg.w-12 {
        width: 3rem;
    }

    .order-container .max-w-full {
        max-width: 100%;
    }

    .order-container .max-w-5xl {
        max-width: 64rem;
    }

    .order-container .h-auto {
        height: auto;
    }

    .order-container .h-3,
    .order-container svg.h-3 {
        height: .75rem;
    }

    .order-container .h-4,
    .order-container svg.h-4 {
        height: 1rem;
    }

    .order-container .h-6,
    .order-container svg.h-6 {
        height: 1.5rem;
    }

    .order-container .h-12,
    .order-container svg.h-12 {
        height: 3rem;
    }

    .order-container .h-32 {
        height: 8rem;
    }

    .order-container .h-96 {
        height: 24rem;
    }

    .order-container .max-h-96 {
        max-height: 24rem;
    }

    .order-container .max-h-full {
        max-height: 100%;
    }

    .order-container .max-h-\[70vh\] {
        max-height: 70vh;
    }

    .order-container .min-h-\[50vh\] {
        min-height: 50vh;
    }

    .order-container .flex-col {
        flex-direction: column;
    }

    .order-container .items-start {
        align-items: flex-start;
    }

    .order-container .items-center {
        align-items: center;
    }

    .order-container .justify-start {
        justify-content: flex-start;
    }

    .order-container .justify-center {
        justify-content: center;
    }

    .order-container .justify-between {
        justify-content: space-between;
    }

    .order-container .gap-0 {
        gap: 0;
    }

    .order-container .gap-1 {
        gap: .25rem;
    }

    .order-container .gap-2 {
        gap: .5rem;
    }

    .order-container .gap-3 {
        gap: .75rem;
    }

    .order-container .gap-4 {
        gap: 1rem;
    }

    .order-container .space-y-1 > :not([hidden]) ~ :not([hidden]) {
        margin-top: .25rem;
    }

    .order-container .p-2 {
        padding: .5rem;
    }

    .order-container .p-3 {
        padding: .75rem;
    }

    .order-container .p-4 {
        padding: 1rem;
    }

    .order-container .p-8 {
        padding: 2rem;
    }

    .order-container .px-2 {
        padding-left: .5rem;
        padding-right: .5rem;
    }

    .order-container .px-3 {
        padding-left: .75rem;
        padding-right: .75rem;
    }

    .order-container .py-1 {
        padding-top: .25rem;
        padding-bottom: .25rem;
    }

    .order-container .mb-2 {
        margin-bottom: .5rem;
    }

    .order-container .mb-3 {
        margin-bottom: .75rem;
    }

    .order-container .mb-4 {
        margin-bottom: 1rem;
    }

    .order-container .mr-4 {
        margin-right: 1rem;
    }

    .order-container .mt-1 {
        margin-top: .25rem;
    }

    .order-container .mt-2 {
        margin-top: .5rem;
    }

    .order-container .mt-4 {
        margin-top: 1rem;
    }

    .order-container .mx-auto {
        margin-left: auto;
        margin-right: auto;
    }

    .order-container .-ml-6 {
        margin-left: -1.5rem;
    }

    .order-container .-translate-y-1\/2 {
        transform: translateY(-50%);
    }

    .order-container .overflow-hidden {
        overflow: hidden;
    }

    .order-container .overflow-x-auto {
        overflow-x: auto;
    }

    .order-container .rounded {
        border-radius: .25rem;
    }

    .order-container .rounded-lg {
        border-radius: .5rem;
    }

    .order-container .rounded-xl {
        border-radius: .75rem;
    }

    .order-container .rounded-full {
        border-radius: 9999px;
    }

    .order-container .border {
        border: 1px solid #e5e7eb;
    }

    .order-container .border-2 {
        border-width: 2px;
    }

    .order-container .border-b {
        border-bottom: 1px solid #e5e7eb;
    }

    .order-container .border-r {
        border-right: 1px solid #e5e7eb;
    }

    .order-container .border-dashed {
        border-style: dashed;
    }

    .order-container .shadow-sm {
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / .05);
    }

    .order-container .shadow-2xl {
        box-shadow: 0 25px 50px -12px rgb(0 0 0 / .25);
    }

    .order-container .text-left {
        text-align: left;
    }

    .order-container .text-center {
        text-align: center;
    }

    .order-container .text-xs {
        font-size: .75rem;
        line-height: 1rem;
    }

    .order-container .text-sm {
        font-size: .875rem;
        line-height: 1.25rem;
    }

    .order-container .font-medium {
        font-weight: 500;
    }

    .order-container .font-semibold {
        font-weight: 600;
    }

    .order-container .font-bold {
        font-weight: 700;
    }

    .order-container .underline {
        text-decoration: underline;
    }

    .order-container .object-cover {
        object-fit: cover;
    }

    .order-container .object-contain {
        object-fit: contain;
    }

    .order-container .cursor-pointer {
        cursor: pointer;
    }

    .order-container .text-black {
        color: #000;
    }

    .order-container .text-white {
        color: #fff;
    }

    .order-container .text-gray-400 {
        color: #9ca3af;
    }

    .order-container .text-gray-500 {
        color: #6b7280;
    }

    .order-container .text-gray-600 {
        color: #4b5563;
    }

    .order-container .text-gray-700 {
        color: #374151;
    }

    .order-container .text-gray-800 {
        color: #1f2937;
    }

    .order-container .text-gray-900,
    .order-container .text-gray-950 {
        color: #111827;
    }

    .order-container .text-blue-500 {
        color: #3b82f6;
    }

    .order-container .text-blue-600 {
        color: #2563eb;
    }

    .order-container .text-blue-700,
    .order-container .text-blue-800,
    .order-container .text-blue-900,
    .order-container .text-blue-950 {
        color: #1e3a8a;
    }

    .order-container .text-green-800 {
        color: #166534;
    }

    .order-container .text-red-600 {
        color: #dc2626;
    }

    .order-container .bg-white {
        background: #fff;
    }

    .order-container .bg-gray-50 {
        background: #f9fafb;
    }

    .order-container .bg-gray-100 {
        background: #f3f4f6;
    }

    .order-container .bg-gray-950 {
        background: #030712;
    }

    .order-container .bg-blue-50 {
        background: #eff6ff;
    }

    .order-container .bg-blue-100 {
        background: #dbeafe;
    }

    .order-container .bg-blue-200 {
        background: #bfdbfe;
    }

    .order-container .bg-green-100 {
        background: #dcfce7;
    }

    .order-container .bg-yellow-50 {
        background: #fefce8;
    }

    .order-container .bg-black\/80 {
        background: rgb(0 0 0 / .8);
    }

    .order-container .bg-white\/90 {
        background: rgb(255 255 255 / .9);
    }

    .order-container .border-gray-200 {
        border-color: #e5e7eb;
    }

    .order-container .border-blue-100 {
        border-color: #dbeafe;
    }

    .order-container .border-blue-200 {
        border-color: #bfdbfe;
    }

    .order-container .border-blue-300 {
        border-color: #93c5fd;
    }

    .order-container .border-green-200 {
        border-color: #bbf7d0;
    }

    .order-container .transition,
    .order-container .transition-colors,
    .order-container .transition-shadow {
        transition: all .15s ease;
    }

    .order-container .grid-cols-1 {
        grid-template-columns: minmax(0, 1fr);
    }

    .grid-cols-2 {
        grid-template-columns: 1fr 1fr;
    }

    .grid-cols-4 {
        grid-template-columns: 1fr 1fr 1fr 1fr;
    }

    .grid-cols-12 {
        grid-template-columns: 1fr 1fr 1fr 1fr 1fr 1fr 1fr 1fr 1fr 1fr 1fr 1fr;
    }

    .col-span-1 {
        grid-column: span 1;
    }

    .col-span-5 {
        grid-column: span 5;
    }

    .col-span-3 {
        grid-column: span 3;
    }

    .col-span-12 {
        grid-column: span 12;
    }

    /* last product row border none */
    .product-item:last-of-type {
        border-bottom: none;
    }

    .products-table-container:active {
        cursor: grabbing;
    }

    .products-table-content {
        position: relative;
        min-width: 848px;
        /* width: max-content; */
        width: 100%;
    }

    /* Modal styles */
    .fi-modal-window {
        max-width: calc(100vw - 2rem) !important;
        width: calc(100vw - 2rem) !important;
        background: #fff;
    }

    @media (min-width: 640px) {
        .fi-modal-window {
            max-width: min(90vw, 1200px) !important;
            width: auto !important;
        }
    }

    .fi-modal-content {
        max-width: 100%;
        padding: 1rem;
    }

    @media (max-width: 768px) {
        .grid-cols-2 {
            grid-template-columns: 1fr;
        }

        .grid-cols-4 {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (min-width: 768px) {
        .order-container .md\:grid-cols-4 {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
    }

    .products-table-container {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        max-width: calc(100vw - 4rem);
        cursor: grab;
        touch-action: pan-x;
    }

    /* Table styles */
    .products-table-content {
        background: #fff;
        border: 1px solid #e5e7eb;
        min-width: 848px;
        /* width: max-content; */
        width: 100%;
    }

    /* Table header and content */
    .products-table-content .product-item {
        background: #fff;
    }

    /* Grid sections */
    .grid-cols-4>div {
        background: #fff;
        padding: 1rem;
        border-radius: .5rem;
    }
</style>
<div class="p-4 order-container">
    <div class="flex items-start justify-between mb-4">
        <div>
            <h2 class="font-bold text-gray-400 dark:text-gray-600 dark:text-white">{{ $record->order_number }}</h2>

        </div>
        <div class="flex items-center gap-4">
            {{-- get the label, not the value --}}
            <p class="text-sm font-bold text-gray-600 dark:text-gray-400">
                {{ App\Enums\PlantLocation::from($record->plant_location)->getLabel() }}</p>

            {{-- Delivery Tag Status --}}
            @if ($record->is_printed)
                <span
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-green-800 bg-green-100 rounded-full dark:bg-green-900 dark:text-green-200">
                    <x-heroicon-s-check-circle class="w-3 h-3" />
                    Printed
                </span>
            @else
                <span
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-gray-800 bg-gray-100 rounded-full dark:bg-gray-700 dark:text-gray-200">
                    <x-heroicon-o-printer class="w-3 h-3" />
                    Not Printed
                </span>
            @endif

            {{-- Delivery Tag Attachment Status --}}
            @if ($record->delivery_tag_url)
                <button @click="$dispatch('toggle-delivery-tag')"
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-blue-800 bg-blue-100 rounded-full hover:bg-blue-200 transition-colors cursor-pointer dark:bg-blue-900 dark:text-blue-200 dark:hover:bg-blue-800"
                    title="Click to view delivery tag" x-data="{ showTag: false }"
                    x-on:toggle-delivery-tag.window="showTag = !showTag">
                    <x-heroicon-s-document-text class="w-3 h-3" />
                    <span x-text="showTag ? 'Hide Tag' : 'View Tag'"></span>
                </button>
            @endif

            <div class="px-2 py-1 text-sm font-medium text-gray-800 border rounded-full"
                style="background-color: {{ $record->status_color['background'] }}; color: {{ $record->status_color['text'] }}; border-color: {{ $record->status_color['border'] }}">
                {{-- get the status label (it's an enum) --}}
                {{ App\Enums\OrderStatus::from($record->status)->label() }}
            </div>
        </div>
    </div>

    {{-- Expandable Delivery Tag Preview --}}
    @if ($record->delivery_tag_url)
        <div x-data="{ showTag: false }" x-on:toggle-delivery-tag.window="showTag = !showTag" class="mb-4">
            <div x-show="showTag" x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 transform -translate-y-2"
                x-transition:enter-end="opacity-100 transform translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 transform translate-y-0"
                x-transition:leave-end="opacity-0 transform -translate-y-2"
                class="p-4 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800"
                style="display: none;">
                <div class="flex items-start justify-between mb-3">
                    <h3 class="font-medium text-blue-900 dark:text-blue-100">Delivery Tag Attachment</h3>
                    <div class="flex items-center gap-2">
                        <a href="{{ Storage::disk('r2')->url($record->delivery_tag_url) }}" target="_blank"
                            class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-blue-800 bg-blue-200 rounded hover:bg-blue-300 transition-colors dark:bg-blue-800 dark:text-blue-200 dark:hover:bg-blue-700">
                            <x-heroicon-o-arrow-top-right-on-square class="w-3 h-3" />
                            Open Full Size
                        </a>
                        <button @click="showTag = false"
                            class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200">
                            <x-heroicon-o-x-mark class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                @php
                    $fileExtension = strtolower(pathinfo($record->delivery_tag_url, PATHINFO_EXTENSION));
                    $isImage = in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                @endphp

                @if ($isImage)
                    <div class="flex justify-center">
                        <img src="{{ Storage::disk('r2')->url($record->delivery_tag_url) }}" alt="Delivery Tag"
                            class="max-w-full h-auto max-h-96 rounded-lg shadow-sm border border-blue-200 dark:border-blue-700 cursor-pointer hover:shadow-md transition-shadow"
                            onclick="window.open('{{ Storage::disk('r2')->url($record->delivery_tag_url) }}', '_blank')" />
                    </div>
                @elseif($fileExtension === 'pdf')
                    <div class="w-full">
                        <iframe
                            src="{{ Storage::disk('r2')->url($record->delivery_tag_url) }}#toolbar=0&navpanes=0&scrollbar=0&view=FitH"
                            class="w-full h-96 rounded-lg border border-blue-200 dark:border-blue-700"
                            title="Delivery Tag PDF Preview">
                        </iframe>
                        <p class="text-xs text-blue-600 dark:text-blue-400 mt-2 text-center">
                            PDF preview - <a href="{{ Storage::disk('r2')->url($record->delivery_tag_url) }}"
                                target="_blank" class="underline hover:no-underline">open in new tab</a> if not
                            displaying properly
                        </p>
                    </div>
                @else
                    <div
                        class="flex items-center justify-center p-8 bg-white dark:bg-gray-800 rounded-lg border-2 border-dashed border-blue-300 dark:border-blue-600">
                        <div class="text-center">
                            <x-heroicon-o-document-text class="w-12 h-12 mx-auto text-blue-500 mb-2" />
                            <p class="text-sm text-blue-700 dark:text-blue-300 mb-2">
                                {{ strtoupper($fileExtension) }} Document
                            </p>
                            <a href="{{ Storage::disk('r2')->url($record->delivery_tag_url) }}" target="_blank"
                                class="inline-flex items-center gap-1 px-3 py-1 text-sm font-medium text-blue-800 bg-blue-200 rounded hover:bg-blue-300 transition-colors dark:bg-blue-800 dark:text-blue-200 dark:hover:bg-blue-700">
                                <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                                Download/View
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <div class="relative flex p-4 mb-4 rounded-lg bg-gray-50 dark:bg-gray-800">
        <div class="flex flex-col items-center justify-center mr-4">
            <div
                class="flex flex-col items-center justify-start w-8 gap-0 text-gray-400 border-r border-gray-200 dark:text-gray-600 dark:text-gray-400 font-lighter dark:border-gray-600">
                <span>S</span>
                <span>O</span>
                <span>L</span>
                <span>D</span>
                <span class="mb-2"></span>
                <span>T</span>
                <span>O</span>
            </div>
        </div>
        <div class="grid justify-between w-full grid-cols-2 gap-4">



            <div class="flex flex-col items-start gap-1">
                <div class="group inline-block">

                    <p class="font-bold">
                        {{ optional($record->location)->name }}
                        @if ($record->location)
                            <a target="_blank"
                                href="{{ \App\Filament\Resources\LocationResource::getUrl('view', ['record' => $record->location]) }}"
                                class="ml opacity-0 group-hover:opacity-100 transition-opacity duration-500 ease-in-out text-xs text-blue-500 underline">
                                View
                            </a>
                        @endif
                    </p>
                </div>



                @if (optional($record->location)->full_address)
                    <div class="flex items-center gap-1">
                        <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($record->location->full_address) }}"
                            target="_blank" rel="noopener noreferrer"
                            class="text-primary-600 dark:text-primary-400 hover:underline">
                            {{ $record->location->full_address }}
                        </a>
                        <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($record->location->full_address) }}"
                            target="_blank" rel="noopener noreferrer"
                            class="text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300">
                            <x-heroicon-o-map-pin class="w-4 h-4" />
                        </a>
                    </div>
                @endif

                @if ($record->location?->plant_drive_distance_summary)
                    <div class="text-xs text-gray-400 dark:text-gray-500">
                        {{ $record->location->plant_drive_distance_summary }}
                    </div>
                @endif

                @if ($record->location?->current_delivery_rate_summary)
                    <div class="text-xs text-gray-400 dark:text-gray-500">
                        {{ $record->location->current_delivery_rate_summary }}
                    </div>
                @endif

                @if ($record->location?->formatted_preferred_contact_phone)
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-phone class="w-4 h-4" />
                        <p>{{ $record->location->formatted_preferred_contact_phone }}</p>
                    </div>
                @endif


                @if ($record->location && $record->location->phone)
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-phone class="w-4 h-4" />
                        @php
                            try {
                                $locationPhoneObj = new PhoneNumber($record->location->phone, 'US');
                                $locationFormattedPhone = $locationPhoneObj->formatNational();
                            } catch (\Throwable $e) {
                                $locationFormattedPhone = $record->location->phone;
                            }
                        @endphp

                        <p>Location: {{ $locationFormattedPhone }}
                            @if ($record->location->phone_extension)
                                x{{ $record->location->phone_extension }}
                            @endif
                        </p>
                    </div>
                @endif


            </div>


            <div class="grid grid-cols-1 gap-4">
                @if ($record->customer_order_number)
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Customer Order #</p>
                        <p class="font-medium">{{ $record->customer_order_number }}</p>
                    </div>
                @endif
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Requested Date</p>
                    <p class="font-medium">{{ $record->requested_delivery_date?->format('M j, Y') }}</p>
                </div>
                <div>
                    <!-- date of order -->
                    <p class="text-sm text-gray-600 dark:text-gray-400">Date of Order</p>
                    <p class="font-medium">{{ $record->order_date?->format('M j, Y') }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Ordered By</p>
                    <p class="font-medium">{{ $record->ordered_by }}</p>
                </div>
            </div>

        </div>
    </div>

    @if ($record->location?->notes)
        <div class="mb-4">
            <div class="relative flex p-4 mb-4 rounded-lg bg-gray-50 dark:bg-gray-800">
                <div class="grid w-full">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Location Notes</p>
                    {!! str($record->location?->notes)->markdown()->sanitizeHtml() !!}
                </div>
            </div>
        </div>
    @endif



    <div class="mb-4">
        <div>
            @if ($record->orderProducts->isEmpty())
                <p class="text-gray-500">No products found for this order</p>
            @else
                <div class="products-table-container" x-data="{
                    isDown: false,
                    startX: null,
                    scrollLeft: null,
                    handleMouseDown(e) {
                        this.isDown = true;
                        this.$el.style.cursor = 'grabbing';
                        this.startX = e.pageX - this.$el.offsetLeft;
                        this.scrollLeft = this.$el.scrollLeft;
                    },
                    handleMouseLeave() {
                        this.isDown = false;
                        this.$el.style.cursor = 'grab';
                    },
                    handleMouseUp() {
                        this.isDown = false;
                        this.$el.style.cursor = 'grab';
                    },
                    handleMouseMove(e) {
                        if (!this.isDown) return;
                        e.preventDefault();
                        const x = e.pageX - this.$el.offsetLeft;
                        const walk = (x - this.startX) * 2;
                        this.$el.scrollLeft = this.scrollLeft - walk;
                    }
                }" @mousedown="handleMouseDown"
                    @mouseleave="handleMouseLeave" @mouseup="handleMouseUp" @mousemove="handleMouseMove">
                    <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-800 products-table-content">
                        <!-- header -->

                        <div
                            class="flex items-center justify-between w-full p-2 m-0 mt-0 border-b border-gray-200 product-item last:border-b-0 dark:border-gray-600">
                            <div class="grid w-full grid-cols-12">
                                <div
                                    class="flex items-center justify-center w-8 col-span-1 text-sm text-center text-gray-600 dark:text-gray-400 qty">
                                    #
                                </div>
                                <div class="flex flex-col items-start col-span-5 -ml-6 product-description">
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Description</p>
                                </div>
                                <!-- location -->
                                <div class="flex items-start col-span-3 text-sm text-gray-600 dark:text-gray-400">
                                    Location</div>
                                <div class="col-span-1 text-sm text-gray-600 dark:text-gray-400 product-shipped">
                                    Shipped
                                </div>

                                <!-- unit price -->
                                <div
                                    class="flex justify-center w-full col-span-1 text-sm text-center text-gray-600 dark:text-gray-400 product-price">
                                    Unit Price
                                </div>
                                <!-- total price -->
                                <div
                                    class="flex justify-center w-full col-span-1 text-sm text-center text-gray-600 dark:text-gray-400 product-total-price">
                                    Amount
                                </div>

                            </div>

                        </div>

                        <!-- end header -->
                        @foreach ($record->orderProducts as $orderProduct)
                            <div
                                class="flex items-center justify-between w-full p-2 m-0 mt-0 border-b border-gray-200 product-item last:border-b-0 dark:border-gray-600">
                                <div class="grid w-full grid-cols-12">
                                    <div
                                        class="flex items-center justify-center w-8 col-span-1 text-center text-black border-r border-gray-200 dark:text-gray-200 qty dark:border-gray-600">
                                        @if ($orderProduct->fill_load)
                                            <p class="font-medium">FL</p>
                                        @else
                                            <p class="font-medium">{{ $orderProduct->quantity }}</p>
                                        @endif
                                    </div>
                                    <div class="flex flex-col items-start col-span-5 -ml-6 product-description">
                                        @if ($orderProduct->is_custom_product)
                                            <p class="font-medium text-black dark:text-gray-200">
                                                {{ $orderProduct->custom_description }}</p>
                                        @elseif ($orderProduct->product)
                                            <p class="font-medium text-black dark:text-gray-200">
                                                {{ $orderProduct->product->sku }}</p>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                {{ $orderProduct->product->name }}</p>
                                        @else
                                            <p class="font-medium text-red-600 dark:text-red-400">
                                                Product not found
                                            </p>
                                        @endif

                                        @if ($orderProduct->notes)
                                            <p class="text-sm text-gray-500">{{ $orderProduct->notes }}</p>
                                        @endif
                                    </div>
                                    <div class="flex items-start col-span-3 text-sm text-gray-600 dark:text-gray-400">
                                        {{ $orderProduct->location }}</div>
                                    <!-- quantity delivered -->
                                    <div class="col-span-1 text-center product-shipped">
                                        {{ $orderProduct->quantity_delivered }}
                                    </div>
                                    <!-- unit price -->
                                    <div class="flex justify-center w-full col-span-1 text-center product-price">
                                        @if ($orderProduct->price > 0)
                                            ${{ number_format($orderProduct->price, 2) }}
                                        @endif
                                    </div>
                                    <!-- total price -->
                                    <div class="flex justify-center w-full col-span-1 text-center product-total-price">
                                        @if ($orderProduct->quantity_delivered && $orderProduct->price > 0)
                                            ${{ number_format($orderProduct->price * $orderProduct->quantity_delivered, 2) }}
                                        @elseif($orderProduct->quantity && $orderProduct->price > 0)
                                            ${{ number_format($orderProduct->price * $orderProduct->quantity, 2) }}
                                        @endif
                                    </div>

                                </div>

                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
    <div class="grid grid-cols-4 gap-4 px-4">
        <!-- Delivery Info  -->
        <div class="flex flex-col items-start delivery-date">
            <p class="text-sm text-gray-600 dark:text-gray-400">Assigned Date</p>
            <p>{{ optional($record->assigned_delivery_date)->format('D m/d/Y') }}</p>
        </div>
        <div class="delivery-time">
            <p class="text-sm text-gray-600 dark:text-gray-400">Requested Delivery Time</p>
            <p>{{ optional($record->delivery_time)->format('g:i A') }}</p>
        </div>
        <div class="service-date">
            <p class="text-sm text-gray-600 dark:text-gray-400">Service Date</p>
            <p>{{ optional($record->service_date)->format('D m/d/Y') }}</p>
        </div>
        <div class="service-time">
            <p class="text-sm text-gray-600 dark:text-gray-400">Service Time</p>
            <p>{{ optional($record->service_time)->format('g:i A') }}</p>
        </div>
    </div>

    @if ($record->special_instructions)
        <div class="p-4 mt-4 rounded-lg bg-yellow-50">
            <h3 class="mb-2 font-medium">Notes</h3>
            <p class="text-sm">{{ $record->special_instructions }}</p>
        </div>
    @endif

    @livewire('order-delivery-photos', ['order' => $record], key('order-delivery-photos-' . $record->getKey()))


    @if (
        $record->status == 'delivered' ||
            $record->status == 'completed' ||
            $record->status == 'picked_up' ||
            $record->status == 'invoiced')
        <div class="grid grid-cols-2 gap-4 bg-gray-50 p-2 rounded-lg">

            @if ($record->delivered_at)
                <div class="p-4 mt-4 rounded-lg bg-yellow-50">
                    <h3 class="mb-2 font-medium">Delivered At</h3>
                    <p class="text-sm">{{ $record->delivered_at?->format('D m/d/Y g:i A') }}</p>
                </div>
            @endif

            @if ($record->signature_path)
                <div class="p-4 mt-4 rounded-lg bg-yellow-50">
                    <h3 class="mb-2 font-medium">Signature</h3>
                    <img src="{{ Storage::disk('r2')->url($record->signature_path) }}" alt="Signature"
                        class="w-full h-auto">
                </div>
            @endif

            @if ($record->delivery_notes)
                <div class="p-4 mt-4 rounded-lg bg-yellow-50">
                    <h3 class="mb-2 font-medium">Delivery Notes</h3>
                    <p class="text-sm">{{ $record->delivery_notes }}</p>
                </div>
            @endif

        </div>
    @endif
</div>
