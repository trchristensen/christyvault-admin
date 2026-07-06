@php
    use App\Enums\PlantLocation;
    use App\Filament\Resources\LocationResource;

    $record = $getRecord();
    $hasCoordinates = $record?->hasCoordinates();
    $typeLabel = filled($record->location_type)
        ? str($record->location_type)->replace('_', ' ')->title()->toString()
        : 'Location';
    $deliveryType = $record->default_plant_location instanceof PlantLocation
        ? $record->default_plant_location->getLabel()
        : (PlantLocation::tryFrom((string) $record->default_plant_location)?->getLabel() ?? 'Colma');
    $mapId = 'location-profile-map-' . $record->id;
    $googleMapsUrl = $record->full_address
        ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($record->full_address)
        : null;
    $editUrl = LocationResource::getUrl('edit', ['record' => $record]);
    $driveSummary = $record->plant_drive_distance_miles !== null && $record->plant_drive_duration_minutes !== null
        ? number_format((float) $record->plant_drive_distance_miles, 1) . ' mi • ' . $record->plant_drive_duration_minutes . ' min'
        : 'Not calculated';
    $rateSummary = $record->current_delivery_rate_summary ?? 'Not calculated';
    $lastOrder = $record->last_order_at?->format('M j, Y') ?? 'No orders yet';
    $totalOrders = number_format((int) ($record->total_orders ?? 0));
    $averageFrequency = $record->average_order_frequency_days
        ? "{$record->average_order_frequency_days} days"
        : 'Not enough history';
    $averageOrderValue = $record->average_order_value !== null
        ? '$' . number_format((float) $record->average_order_value, 2)
        : 'Not enough history';
@endphp

<style>
    .location-profile {
        position: relative;
        z-index: 0;
        isolation: isolate;
        overflow: hidden;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        background: rgb(255 255 255);
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }

    .dark .location-profile {
        border-color: rgb(55 65 81);
        background: rgb(31 41 55);
    }

    .location-profile-cover {
        position: relative;
        z-index: 0;
        min-height: 18rem;
        background: rgb(241 245 249);
        isolation: isolate;
    }

    .dark .location-profile-cover {
        background: rgb(17 24 39);
    }

    .location-profile-map {
        position: relative;
        z-index: 0;
        height: clamp(260px, 29vw, 390px);
        min-height: 18rem;
        width: 100%;
    }

    .location-profile-cover .leaflet-container,
    .location-profile-cover .leaflet-pane,
    .location-profile-cover .leaflet-map-pane,
    .location-profile-cover .leaflet-tile-pane,
    .location-profile-cover .leaflet-overlay-pane,
    .location-profile-cover .leaflet-marker-pane,
    .location-profile-cover .leaflet-tooltip-pane,
    .location-profile-cover .leaflet-popup-pane,
    .location-profile-cover .leaflet-control-container,
    .location-profile-cover .leaflet-top,
    .location-profile-cover .leaflet-bottom {
        z-index: 1 !important;
    }

    .location-profile-cover .leaflet-control {
        z-index: 2 !important;
    }

    .location-profile-cover::after {
        position: absolute;
        z-index: 2;
        inset: 0;
        content: "";
        pointer-events: none;
        background:
            linear-gradient(to bottom, rgba(255, 255, 255, 0) 35%, rgba(255, 255, 255, 0.92) 100%),
            linear-gradient(to right, rgba(255, 255, 255, 0.46), rgba(255, 255, 255, 0.08));
    }

    .dark .location-profile-cover::after {
        background:
            linear-gradient(to bottom, rgba(31, 41, 55, 0) 35%, rgba(31, 41, 55, 0.96) 100%),
            linear-gradient(to right, rgba(31, 41, 55, 0.5), rgba(31, 41, 55, 0.08));
    }

    .location-profile-empty-map {
        display: grid;
        min-height: 18rem;
        place-items: center;
        padding: 2rem;
        color: rgb(107 114 128);
        font-size: 0.92rem;
        font-weight: 600;
        text-align: center;
    }

    .location-profile-main {
        position: relative;
        z-index: 3;
        margin-top: -4.4rem;
        padding: 0 1.5rem 1.5rem;
    }

    .location-profile-identity {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: 1rem;
        align-items: end;
    }

    .location-profile-avatar {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 5.25rem;
        height: 5.25rem;
        border: 4px solid rgb(255 255 255);
        border-radius: 9999px;
        background: rgb(30 64 175);
        color: rgb(255 255 255);
        font-size: 1.8rem;
        font-weight: 800;
        box-shadow: 0 14px 32px rgba(15, 23, 42, 0.18);
    }

    .dark .location-profile-avatar {
        border-color: rgb(31 41 55);
    }

    .location-profile-title {
        margin: 0.4rem 0 0;
        color: rgb(17 24 39);
        font-size: clamp(1.55rem, 3vw, 2.35rem);
        line-height: 1.08;
        font-weight: 800;
        letter-spacing: 0;
    }

    .dark .location-profile-title {
        color: rgb(249 250 251);
    }

    .location-profile-address {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        max-width: 100%;
        margin-top: 0.35rem;
        color: rgb(30 64 175);
        font-size: 0.96rem;
        font-weight: 650;
        text-decoration: none;
    }

    .location-profile-address:hover {
        text-decoration: underline;
    }

    .dark .location-profile-address {
        color: rgb(147 197 253);
    }

    .location-profile-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 0.45rem;
    }

    .location-profile-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        border: 1px solid rgb(229 231 235);
        border-radius: 9999px;
        padding: 0.28rem 0.68rem;
        background: rgba(255, 255, 255, 0.88);
        color: rgb(55 65 81);
        font-size: 0.78rem;
        font-weight: 750;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }

    .dark .location-profile-chip {
        border-color: rgb(75 85 99);
        background: rgba(17, 24, 39, 0.9);
        color: rgb(229 231 235);
    }

    .location-profile-map-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        min-height: 2.45rem;
        border: 1px solid rgb(209 213 219);
        border-radius: 0.5rem;
        padding: 0.55rem 0.85rem;
        background: rgb(255 255 255);
        color: rgb(31 41 55);
        font-size: 0.88rem;
        font-weight: 750;
        text-decoration: none;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }

    .location-profile-map-link:hover {
        background: rgb(249 250 251);
    }

    .location-profile-edit-link {
        border-color: rgb(30 64 175);
        background: rgb(30 64 175);
        color: rgb(255 255 255);
    }

    .location-profile-edit-link:hover {
        background: rgb(30 58 138);
    }

    .dark .location-profile-map-link {
        border-color: rgb(75 85 99);
        background: rgb(17 24 39);
        color: rgb(229 231 235);
    }

    .dark .location-profile-edit-link {
        border-color: rgb(59 130 246);
        background: rgb(37 99 235);
        color: rgb(255 255 255);
    }

    .location-profile-metrics {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 0.75rem;
        margin-top: 1.1rem;
    }

    .location-profile-metric,
    .location-profile-panel {
        min-width: 0;
        border: 1px solid rgb(238 242 247);
        border-radius: 0.65rem;
        background: rgb(248 250 252);
    }

    .dark .location-profile-metric,
    .dark .location-profile-panel {
        border-color: rgb(55 65 81);
        background: rgb(17 24 39);
    }

    .location-profile-metric {
        padding: 0.85rem 0.95rem;
    }

    .location-profile-label {
        margin-bottom: 0.22rem;
        color: rgb(107 114 128);
        font-size: 0.68rem;
        font-weight: 800;
        letter-spacing: 0;
        line-height: 1.15;
        text-transform: uppercase;
    }

    .dark .location-profile-label {
        color: rgb(156 163 175);
    }

    .location-profile-value {
        overflow-wrap: anywhere;
        color: rgb(17 24 39);
        font-size: 0.96rem;
        font-weight: 750;
        line-height: 1.35;
    }

    .dark .location-profile-value {
        color: rgb(243 244 246);
    }

    .location-profile-subvalue {
        margin-top: 0.18rem;
        color: rgb(107 114 128);
        font-size: 0.78rem;
        font-weight: 600;
        line-height: 1.35;
    }

    .dark .location-profile-subvalue {
        color: rgb(156 163 175);
    }

    .location-profile-content {
        display: grid;
        grid-template-columns: minmax(0, 1.65fr) minmax(280px, 0.9fr);
        gap: 1rem;
        margin-top: 1rem;
    }

    .location-profile-column {
        display: grid;
        align-content: start;
        gap: 1rem;
    }

    .location-profile-panel {
        padding: 1rem;
    }

    .location-profile-panel-title {
        margin-bottom: 0.85rem;
        color: rgb(17 24 39);
        font-size: 1rem;
        font-weight: 800;
        letter-spacing: 0;
    }

    .dark .location-profile-panel-title {
        color: rgb(249 250 251);
    }

    .location-profile-rows {
        display: grid;
        gap: 0.8rem;
    }

    .location-profile-row {
        display: grid;
        grid-template-columns: minmax(8rem, 0.42fr) minmax(0, 1fr);
        gap: 0.75rem;
        align-items: baseline;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid rgb(229 231 235);
    }

    .location-profile-row:last-child {
        padding-bottom: 0;
        border-bottom: 0;
    }

    .dark .location-profile-row {
        border-color: rgb(55 65 81);
    }

    .location-profile-note {
        border-color: rgb(254 240 138);
        background: rgb(254 252 232);
    }

    .dark .location-profile-note {
        border-color: rgba(113, 63, 18, 0.65);
        background: rgba(113, 63, 18, 0.18);
    }

    .location-profile-note .location-profile-panel-title,
    .location-profile-note-content {
        color: rgb(66 32 6);
    }

    .dark .location-profile-note .location-profile-panel-title,
    .dark .location-profile-note-content {
        color: rgb(254 243 199);
    }

    .location-profile-warning {
        border-color: rgb(254 202 202);
        background: rgb(254 242 242);
    }

    .dark .location-profile-warning {
        border-color: rgba(127, 29, 29, 0.65);
        background: rgba(127, 29, 29, 0.22);
    }

    .location-profile-warning .location-profile-panel-title,
    .location-profile-warning .location-profile-value {
        color: rgb(153 27 27);
    }

    .dark .location-profile-warning .location-profile-panel-title,
    .dark .location-profile-warning .location-profile-value {
        color: rgb(252 165 165);
    }

    @media (max-width: 1180px) {
        .location-profile-metrics {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 920px) {
        .location-profile-content {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 760px) {
        .location-profile-main {
            margin-top: -3.7rem;
            padding: 0 1rem 1rem;
        }

        .location-profile-identity {
            grid-template-columns: auto minmax(0, 1fr);
            align-items: end;
        }

        .location-profile-actions {
            grid-column: 1 / -1;
            margin-left: calc(4.8rem + 1rem);
        }

        .location-profile-avatar {
            width: 4.8rem;
            height: 4.8rem;
            font-size: 1.55rem;
        }

        .location-profile-metrics {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .location-profile-row {
            grid-template-columns: 1fr;
            gap: 0.18rem;
        }
    }

    @media (max-width: 520px) {
        .location-profile-cover,
        .location-profile-map,
        .location-profile-empty-map {
            min-height: 15.5rem;
        }

        .location-profile-metrics {
            grid-template-columns: 1fr;
        }

        .location-profile-actions {
            margin-left: 0;
        }
    }
</style>

<div class="location-profile">
    <div class="location-profile-cover">
        @if ($hasCoordinates)
            <div
                wire:ignore
                x-data="{
                    map: null,
                    latitude: @js((float) $record->latitude),
                    longitude: @js((float) $record->longitude),
                    name: @js($record->name),
                    address: @js($record->full_address),
                }"
                x-init='
                    const initializeLocationProfileMap = () => {
                        if (map) {
                            map.remove();
                        }

                        map = L.map($refs.map, {
                            zoomControl: false,
                            dragging: true,
                            scrollWheelZoom: false,
                            attributionControl: true,
                        }).setView([latitude, longitude], 13);

                        L.tileLayer("https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png", {
                            attribution: "&copy; <a href=\"http://www.openstreetmap.org/copyright\">OpenStreetMap</a>, &copy; <a href=\"https://carto.com/attributions\">CARTO</a>",
                            subdomains: "abcd",
                            maxZoom: 20,
                            minZoom: 0
                        }).addTo(map);

                        L.marker([latitude, longitude])
                            .addTo(map)
                            .bindPopup(`<strong>${name}</strong><br>${address}`);

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
                            script.onload = initializeLocationProfileMap;
                            document.head.appendChild(script);
                        } else {
                            script.addEventListener("load", initializeLocationProfileMap, { once: true });
                        }
                    } else {
                        initializeLocationProfileMap();
                    }
                '
            >
                <div x-ref="map" id="{{ $mapId }}" class="location-profile-map"></div>
            </div>
        @else
            <div class="location-profile-empty-map">
                Geocode this location to show the map and calculate route details.
            </div>
        @endif
    </div>

    <div class="location-profile-main">
        <div class="location-profile-identity">
            <div class="location-profile-avatar">
                {{ str($record->name)->substr(0, 1)->upper() }}
            </div>

            <div style="min-width: 0;">
                <div class="location-profile-chips">
                    <span class="location-profile-chip">{{ $typeLabel }}</span>
                    <span class="location-profile-chip">{{ $deliveryType }}</span>
                    <span class="location-profile-chip">{{ (int) ($record->total_orders ?? 0) > 0 ? $totalOrders . ' orders' : 'No orders' }}</span>
                </div>

                <h2 class="location-profile-title">{{ $record->name }}</h2>

                @if ($record->full_address)
                    <a href="{{ $googleMapsUrl }}" target="_blank" rel="noopener noreferrer" class="location-profile-address">
                        <x-heroicon-o-map-pin style="width: 1rem; height: 1rem; flex: 0 0 auto;" />
                        <span>{{ $record->full_address }}</span>
                    </a>
                @endif
            </div>

            <div class="location-profile-actions">
                <a href="{{ $editUrl }}" class="location-profile-map-link location-profile-edit-link">
                    <x-heroicon-o-pencil-square style="width: 1rem; height: 1rem;" />
                    Edit
                </a>

                @if ($googleMapsUrl)
                    <a href="{{ $googleMapsUrl }}" target="_blank" rel="noopener noreferrer" class="location-profile-map-link">
                        <x-heroicon-o-arrow-top-right-on-square style="width: 1rem; height: 1rem;" />
                        Maps
                    </a>
                @endif
            </div>
        </div>

        <div class="location-profile-metrics">
            <div class="location-profile-metric">
                <div class="location-profile-label">Plant</div>
                <div class="location-profile-value">{{ $record->plantDriveDistanceOrigin?->name ?? 'Not calculated' }}</div>
            </div>

            <div class="location-profile-metric">
                <div class="location-profile-label">Drive</div>
                <div class="location-profile-value">{{ $driveSummary }}</div>
            </div>

            <div class="location-profile-metric">
                <div class="location-profile-label">Rate</div>
                <div class="location-profile-value">{{ $rateSummary }}</div>
            </div>

            <div class="location-profile-metric">
                <div class="location-profile-label">Last Order</div>
                <div class="location-profile-value">{{ $lastOrder }}</div>
            </div>

            <div class="location-profile-metric">
                <div class="location-profile-label">Total Orders</div>
                <div class="location-profile-value">{{ $totalOrders }}</div>
            </div>
        </div>

        <div class="location-profile-content">
            <div class="location-profile-column">
                <div class="location-profile-panel">
                    <div class="location-profile-panel-title">Delivery</div>
                    <div class="location-profile-rows">
                        <div class="location-profile-row">
                            <div class="location-profile-label">Default Type</div>
                            <div>
                                <div class="location-profile-value">{{ $deliveryType }}</div>
                                <div class="location-profile-subvalue">Used when new orders are created for this location.</div>
                            </div>
                        </div>

                        <div class="location-profile-row">
                            <div class="location-profile-label">Route From Plant</div>
                            <div>
                                <div class="location-profile-value">{{ $driveSummary }}</div>
                                <div class="location-profile-subvalue">{{ $record->plantDriveDistanceOrigin?->name ?? 'Plant has not been matched yet.' }}</div>
                            </div>
                        </div>

                        <div class="location-profile-row">
                            <div class="location-profile-label">Delivery Rate</div>
                            <div>
                                <div class="location-profile-value">{{ $rateSummary }}</div>
                                <div class="location-profile-subvalue">
                                    {{ $record->plant_drive_distance_calculated_at ? 'Distance updated ' . $record->plant_drive_distance_calculated_at->format('M j, Y g:i A') : 'Distance has not been calculated yet.' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="location-profile-panel">
                    <div class="location-profile-panel-title">Contact</div>
                    <div class="location-profile-rows">
                        <div class="location-profile-row">
                            <div class="location-profile-label">Preferred</div>
                            <div class="location-profile-value">{{ $record->preferredDeliveryContact?->name ?? 'No preferred contact' }}</div>
                        </div>

                        <div class="location-profile-row">
                            <div class="location-profile-label">Phone</div>
                            <div class="location-profile-value">{{ $record->formatted_preferred_phone ?: 'No delivery phone' }}</div>
                        </div>

                        <div class="location-profile-row">
                            <div class="location-profile-label">Email</div>
                            <div class="location-profile-value">{{ $record->email ?: 'No email on file' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="location-profile-column">
                @if ($record->notes)
                    <div class="location-profile-panel location-profile-note">
                        <div class="location-profile-panel-title">Notes</div>
                        <div class="location-profile-note-content">
                            {!! str($record->notes)->markdown()->sanitizeHtml() !!}
                        </div>
                    </div>
                @endif

                <div class="location-profile-panel">
                    <div class="location-profile-panel-title">History</div>
                    <div class="location-profile-rows">
                        <div class="location-profile-row">
                            <div class="location-profile-label">Last Order</div>
                            <div class="location-profile-value">{{ $lastOrder }}</div>
                        </div>

                        <div class="location-profile-row">
                            <div class="location-profile-label">Frequency</div>
                            <div class="location-profile-value">{{ $averageFrequency }}</div>
                        </div>

                        <div class="location-profile-row">
                            <div class="location-profile-label">Average Value</div>
                            <div class="location-profile-value">{{ $averageOrderValue }}</div>
                        </div>
                    </div>
                </div>

                @if ($record->geocoding_failure_reason)
                    <div class="location-profile-panel location-profile-warning">
                        <div class="location-profile-panel-title">Address Lookup Issue</div>
                        <div class="location-profile-value">{{ $record->geocoding_failure_reason }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
