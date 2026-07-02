@php
    $record = $getRecord();
    $hasCoordinates = $record?->hasCoordinates();
    $mapId = 'location-map-' . ($record?->id ?? 'new');
@endphp

@if ($hasCoordinates)
    <div
        wire:ignore
        class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700"
        x-data="{
            map: null,
            latitude: @js((float) $record->latitude),
            longitude: @js((float) $record->longitude),
            name: @js($record->name),
            address: @js($record->full_address),
        }"
        x-init='
            const initializeLocationMap = () => {
                if (map) {
                    map.remove();
                }

                map = L.map($refs.map).setView([latitude, longitude], 13);

                L.tileLayer("https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png", {
                    attribution: "&copy; <a href=\"http://www.openstreetmap.org/copyright\">OpenStreetMap</a>, &copy; <a href=\"https://carto.com/attributions\">CARTO</a>",
                    subdomains: "abcd",
                    maxZoom: 20,
                    minZoom: 0
                }).addTo(map);

                L.marker([latitude, longitude])
                    .addTo(map)
                    .bindPopup(`
                        <div class="text-sm">
                            <div class="mb-1 font-bold">${name}</div>
                            <div>${address}</div>
                        </div>
                    `);

                setTimeout(() => map.invalidateSize(), 100);
            };

            if (!document.getElementById("leaflet-css")) {
                const link = document.createElement("link");
                link.id = "leaflet-css";
                link.rel = "stylesheet";
                link.href = "https://unpkg.com/leaflet@1.9.4/dist/leaflet.css";
                document.head.appendChild(link);
            }

            if (typeof L === "undefined") {
                let script = document.getElementById("leaflet-js");

                if (!script) {
                    script = document.createElement("script");
                    script.id = "leaflet-js";
                    script.src = "https://unpkg.com/leaflet@1.9.4/dist/leaflet.js";
                    script.onload = initializeLocationMap;
                    document.head.appendChild(script);
                } else {
                    script.addEventListener("load", initializeLocationMap, { once: true });
                }
            } else {
                initializeLocationMap();
            }
        '
    >
        <div x-ref="map" id="{{ $mapId }}" style="height: 360px; width: 100%;"></div>
    </div>

    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
        <span>{{ $record->latitude }}, {{ $record->longitude }}</span>
        <a
            href="https://www.google.com/maps/search/?api=1&query={{ urlencode($record->full_address) }}"
            target="_blank"
            rel="noopener noreferrer"
            class="text-primary-600 hover:underline dark:text-primary-400"
        >
            Open in Google Maps
        </a>
    </div>
@else
    <div class="rounded-lg border border-dashed border-gray-300 p-6 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
        This location does not have coordinates yet. Use the Geocode action or enter coordinates manually.
    </div>
@endif
