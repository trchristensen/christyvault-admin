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
    $plantLocation = $record->plantDriveDistanceOrigin;
    $hasPlantCoordinates = $plantLocation?->hasCoordinates() && ! $plantLocation->is($record);
    $routeGeometry = collect($record->plant_drive_route_geometry ?? [])
        ->filter(fn($point): bool => is_array($point) && isset($point[0], $point[1]))
        ->map(fn(array $point): array => [(float) $point[0], (float) $point[1]])
        ->values()
        ->all();
    $locationTypeClass = match ((string) $record->location_type) {
        'cemetery' => 'cemetery',
        'funeral_home' => 'funeral-home',
        'business' => 'business',
        'residential' => 'residential',
        default => 'other',
    };
    $mapData = [
        'latitude' => (float) $record->latitude,
        'longitude' => (float) $record->longitude,
        'originLatitude' => $hasPlantCoordinates ? (float) $plantLocation->latitude : null,
        'originLongitude' => $hasPlantCoordinates ? (float) $plantLocation->longitude : null,
        'originName' => $hasPlantCoordinates ? $plantLocation->name : null,
        'name' => $record->name,
        'address' => $record->full_address,
        'typeLabel' => $typeLabel,
        'typeClass' => $locationTypeClass,
        'routeGeometry' => $routeGeometry,
    ];
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

    .location-profile-cover .leaflet-tooltip.location-profile-map-label {
        border: 2px solid rgb(255 255 255);
        border-radius: 9999px;
        padding: 0.32rem 0.62rem;
        color: rgb(255 255 255);
        font-size: 0.72rem;
        font-weight: 800;
        line-height: 1;
        white-space: nowrap;
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.28);
    }

    .location-profile-cover .leaflet-tooltip.location-profile-map-label::before {
        display: none;
    }

    .location-profile-map-label-plant {
        background: rgb(30 64 175);
    }

    .location-profile-map-label-cemetery {
        background: rgb(22 101 52);
    }

    .location-profile-map-label-funeral-home {
        background: rgb(126 34 206);
    }

    .location-profile-map-label-business {
        background: rgb(14 116 144);
    }

    .location-profile-map-label-residential {
        background: rgb(194 65 12);
    }

    .location-profile-map-label-other {
        background: rgb(75 85 99);
    }

    .location-profile-map-error {
        display: grid;
        height: 100%;
        min-height: inherit;
        place-items: center;
        padding: 2rem;
        color: rgb(107 114 128);
        font-size: 0.92rem;
        font-weight: 650;
        text-align: center;
    }

    .location-profile-map-pin {
        width: 1rem;
        height: 1rem;
        border: 2px solid rgb(255 255 255);
        border-radius: 9999px;
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.28);
    }

    .location-profile-map-pin-plant {
        background: rgb(30 64 175);
    }

    .location-profile-map-pin-cemetery {
        background: rgb(22 101 52);
    }

    .location-profile-map-pin-funeral-home {
        background: rgb(126 34 206);
    }

    .location-profile-map-pin-business {
        background: rgb(14 116 144);
    }

    .location-profile-map-pin-residential {
        background: rgb(194 65 12);
    }

    .location-profile-map-pin-other {
        background: rgb(75 85 99);
    }

    .location-profile-cover::after {
        position: absolute;
        z-index: 2;
        inset: 0;
        content: "";
        pointer-events: none;
        background:
            linear-gradient(to bottom, rgba(255, 255, 255, 0) 48%, rgba(255, 255, 255, 0.74) 100%),
            linear-gradient(to right, rgba(255, 255, 255, 0.18), rgba(255, 255, 255, 0));
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

<script>
    window.locationProfileMapInstances = window.locationProfileMapInstances || {};

    window.initLocationProfileMap = function (mapId) {
        const container = document.getElementById(mapId);
        const dataElement = document.getElementById(`${mapId}-data`);

        if (!container || !dataElement) {
            return;
        }

        const showMapError = () => {
            container.innerHTML = '<div class="location-profile-map-error">Map could not load.</div>';
        };

        const loadLeaflet = (callback) => {
            if (!document.getElementById('leaflet-css')) {
                const link = document.createElement('link');
                link.id = 'leaflet-css';
                link.rel = 'stylesheet';
                link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                document.head.appendChild(link);
            }

            if (window.L) {
                callback();
                return;
            }

            let script = document.getElementById('leaflet-js');

            if (!script) {
                script = document.createElement('script');
                script.id = 'leaflet-js';
                script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                script.onload = callback;
                script.onerror = showMapError;
                document.head.appendChild(script);

                return;
            }

            script.addEventListener('load', callback, { once: true });
            script.addEventListener('error', showMapError, { once: true });
        };

        const escapeHtml = (value) => String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll(String.fromCharCode(39), '&#039;');

        const init = () => {
            let data;

            try {
                data = JSON.parse(dataElement.textContent || '{}');
            } catch (error) {
                showMapError();
                return;
            }

            const destination = [Number(data.latitude), Number(data.longitude)];

            if (!Number.isFinite(destination[0]) || !Number.isFinite(destination[1])) {
                showMapError();
                return;
            }

            if (window.locationProfileMapInstances[mapId]) {
                window.locationProfileMapInstances[mapId].remove();
            }

            container.innerHTML = '';

            const map = L.map(container, {
                zoomControl: false,
                dragging: true,
                scrollWheelZoom: false,
                attributionControl: true,
            }).setView(destination, 13);

            window.locationProfileMapInstances[mapId] = map;

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 20,
                minZoom: 0,
            }).addTo(map);

            const colors = {
                plant: '#1e40af',
                cemetery: '#166534',
                'funeral-home': '#7e22ce',
                business: '#0e7490',
                residential: '#c2410c',
                other: '#4b5563',
            };

            const addPoint = (coordinates, label, className, color) => {
                L.circleMarker(coordinates, {
                    radius: 8,
                    color: '#ffffff',
                    weight: 2,
                    fillColor: color,
                    fillOpacity: 1,
                })
                    .addTo(map)
                    .bindTooltip(escapeHtml(label), {
                        permanent: true,
                        direction: 'top',
                        offset: [0, -10],
                        className: `location-profile-map-label ${className}`,
                    });
            };

            const bounds = L.latLngBounds([destination]);
            const origin = Number.isFinite(Number(data.originLatitude)) && Number.isFinite(Number(data.originLongitude))
                ? [Number(data.originLatitude), Number(data.originLongitude)]
                : null;

            if (origin) {
                addPoint(origin, 'Plant', 'location-profile-map-label-plant', colors.plant);
                bounds.extend(origin);

                const routeGeometry = Array.isArray(data.routeGeometry)
                    ? data.routeGeometry
                        .map((point) => [Number(point[0]), Number(point[1])])
                        .filter((point) => Number.isFinite(point[0]) && Number.isFinite(point[1]))
                    : [];

                if (routeGeometry.length > 1) {
                    const route = L.polyline(routeGeometry, {
                        color: '#1d4ed8',
                        weight: 5,
                        opacity: 0.8,
                        lineCap: 'round',
                        lineJoin: 'round',
                    }).addTo(map);

                    bounds.extend(route.getBounds());
                } else {
                    L.polyline([origin, destination], {
                        color: '#1d4ed8',
                        weight: 4,
                        opacity: 0.7,
                        dashArray: '8 8',
                    }).addTo(map);
                }
            }

            const typeClass = data.typeClass || 'other';
            const destinationClass = `location-profile-map-label-${typeClass}`;
            const destinationColor = colors[typeClass] || colors.other;

            addPoint(destination, data.typeLabel || 'Location', destinationClass, destinationColor);

            L.popup()
                .setLatLng(destination)
                .setContent(`<strong>${escapeHtml(data.name)}</strong><br>${escapeHtml(data.address)}`);

            setTimeout(() => {
                map.invalidateSize();

                if (origin && bounds.isValid()) {
                    map.fitBounds(bounds.pad(0.2), {
                        maxZoom: 13,
                        animate: false,
                    });
                } else {
                    map.setView(destination, 13);
                }
            }, 100);
        };

        loadLeaflet(init);
    };
</script>

<div class="location-profile">
    <div class="location-profile-cover">
        @if ($hasCoordinates)
            <div
                wire:ignore
                x-data="{}"
                x-init="$nextTick(() => window.initLocationProfileMap($el.dataset.mapId))"
                data-map-id="{{ $mapId }}"
            >
                <script type="application/json" id="{{ $mapId }}-data">{!! json_encode($mapData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
                <div id="{{ $mapId }}" class="location-profile-map"></div>
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
