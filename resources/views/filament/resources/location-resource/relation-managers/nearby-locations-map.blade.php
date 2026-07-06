@php
    use App\Filament\Resources\LocationResource;

    $ownerHasCoordinates = $owner?->hasCoordinates();
    $mapId = 'nearby-locations-map-' . $owner->getKey();
    $ownerMarker = $ownerHasCoordinates
        ? [
            'name' => $owner->name,
            'address' => $owner->full_address,
            'lat' => (float) $owner->latitude,
            'lng' => (float) $owner->longitude,
            'distance' => 'Current location',
            'url' => LocationResource::getUrl('view', ['record' => $owner]),
        ]
        : null;
    $locationMarkers = $locations
        ->filter(fn ($location) => $location->hasCoordinates())
        ->map(fn ($location) => [
            'name' => $location->name,
            'address' => $location->full_address,
            'lat' => (float) $location->latitude,
            'lng' => (float) $location->longitude,
            'distance' => number_format((float) $location->distance_miles, 1) . ' mi away',
            'url' => LocationResource::getUrl('view', ['record' => $location]),
        ])
        ->values();
@endphp

<style>
    .nearby-locations-map-panel {
        padding: 1rem;
    }

    .nearby-locations-map-shell {
        overflow: hidden;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        background: rgb(248 250 252);
    }

    .dark .nearby-locations-map-shell {
        border-color: rgb(55 65 81);
        background: rgb(17 24 39);
    }

    .nearby-locations-map {
        position: relative;
        z-index: 0;
        height: clamp(240px, 26vw, 360px);
        min-height: 15rem;
        width: 100%;
    }

    .nearby-locations-map-shell .leaflet-container,
    .nearby-locations-map-shell .leaflet-pane,
    .nearby-locations-map-shell .leaflet-map-pane,
    .nearby-locations-map-shell .leaflet-tile-pane,
    .nearby-locations-map-shell .leaflet-overlay-pane,
    .nearby-locations-map-shell .leaflet-marker-pane,
    .nearby-locations-map-shell .leaflet-tooltip-pane,
    .nearby-locations-map-shell .leaflet-popup-pane,
    .nearby-locations-map-shell .leaflet-control-container,
    .nearby-locations-map-shell .leaflet-top,
    .nearby-locations-map-shell .leaflet-bottom {
        z-index: 1 !important;
    }

    .nearby-locations-map-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        border-top: 1px solid rgb(229 231 235);
        padding: 0.75rem 0.9rem;
        color: rgb(75 85 99);
        font-size: 0.82rem;
        font-weight: 650;
    }

    .dark .nearby-locations-map-meta {
        border-color: rgb(55 65 81);
        color: rgb(209 213 219);
    }

    .nearby-locations-map-legend {
        display: inline-flex;
        flex-wrap: wrap;
        gap: 0.85rem;
    }

    .nearby-locations-map-legend-item {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }

    .nearby-locations-map-dot {
        display: inline-block;
        width: 0.65rem;
        height: 0.65rem;
        border-radius: 9999px;
    }

    .nearby-locations-map-dot-current {
        background: rgb(37 99 235);
    }

    .nearby-locations-map-dot-nearby {
        background: rgb(5 150 105);
    }

    .nearby-locations-map-empty {
        display: grid;
        min-height: 12rem;
        place-items: center;
        padding: 1.5rem;
        color: rgb(107 114 128);
        font-size: 0.9rem;
        font-weight: 650;
        text-align: center;
    }

    .dark .nearby-locations-map-empty {
        color: rgb(156 163 175);
    }
</style>

<div class="nearby-locations-map-panel">
    <div class="nearby-locations-map-shell">
        @if ($ownerMarker)
            <div
                wire:ignore
                x-data="{
                    map: null,
                    owner: @js($ownerMarker),
                    locations: @js($locationMarkers),
                }"
                x-init='
                    const initializeNearbyLocationsMap = () => {
                        if (!owner || !$refs.map || typeof L === "undefined") {
                            return;
                        }

                        if (map) {
                            map.remove();
                        }

                        map = L.map($refs.map, {
                            zoomControl: true,
                            dragging: true,
                            scrollWheelZoom: false,
                            attributionControl: true,
                        });

                        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
                            attribution: "&copy; <a href=\"https://www.openstreetmap.org/copyright\">OpenStreetMap</a> contributors",
                            maxZoom: 20,
                            minZoom: 0
                        }).addTo(map);

                        const bounds = L.latLngBounds([]);

                        const popupContent = (location) => {
                            const content = document.createElement("div");
                            const name = document.createElement("strong");
                            name.textContent = location.name;
                            content.appendChild(name);

                            if (location.address) {
                                content.appendChild(document.createElement("br"));
                                content.appendChild(document.createTextNode(location.address));
                            }

                            if (location.distance) {
                                content.appendChild(document.createElement("br"));
                                content.appendChild(document.createTextNode(location.distance));
                            }

                            return content;
                        };

                        const addCircleMarker = (location, options) => {
                            const latLng = [location.lat, location.lng];

                            L.circleMarker(latLng, options)
                                .addTo(map)
                                .bindPopup(popupContent(location));

                            bounds.extend(latLng);
                        };

                        addCircleMarker(owner, {
                            radius: 8,
                            color: "#1d4ed8",
                            weight: 2,
                            fillColor: "#2563eb",
                            fillOpacity: 0.95,
                        });

                        locations.forEach((location) => {
                            addCircleMarker(location, {
                                radius: 6,
                                color: "#047857",
                                weight: 2,
                                fillColor: "#059669",
                                fillOpacity: 0.82,
                            });
                        });

                        const fitMapToMarkers = () => {
                            map.invalidateSize();

                            if (bounds.isValid()) {
                                map.fitBounds(bounds.pad(0.2), {
                                    maxZoom: 12,
                                    animate: false,
                                });
                            }
                        };

                        [100, 350, 800].forEach((delay) => {
                            setTimeout(fitMapToMarkers, delay);
                        });

                        if (typeof ResizeObserver !== "undefined") {
                            const resizeObserver = new ResizeObserver(fitMapToMarkers);
                            resizeObserver.observe($refs.map);
                        }
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
                            script.onload = initializeNearbyLocationsMap;
                            document.head.appendChild(script);
                        } else {
                            script.addEventListener("load", initializeNearbyLocationsMap, { once: true });
                        }
                    } else {
                        initializeNearbyLocationsMap();
                    }
                '
            >
                <div x-ref="map" id="{{ $mapId }}" class="nearby-locations-map"></div>
            </div>

            <div class="nearby-locations-map-meta">
                <div class="nearby-locations-map-legend">
                    <span class="nearby-locations-map-legend-item">
                        <span class="nearby-locations-map-dot nearby-locations-map-dot-current"></span>
                        Current location
                    </span>
                    <span class="nearby-locations-map-legend-item">
                        <span class="nearby-locations-map-dot nearby-locations-map-dot-nearby"></span>
                        Nearby location
                    </span>
                </div>
                <div>
                    Showing {{ $locationMarkers->count() }} closest mapped {{ str('location')->plural($locationMarkers->count()) }}
                </div>
            </div>
        @else
            <div class="nearby-locations-map-empty">
                Geocode this location to show nearby locations on a map.
            </div>
        @endif
    </div>
</div>
