<div>
    <x-filament-panels::page>
        <div class="space-y-4">
            {{ $this->form }}
            <div class="p-4 bg-white rounded-lg shadow">
                <div wire:ignore x-data="{
                    map: null,
                    markers: [],
                    circles: [],
                    mapData: @js($this->mapData)
                }"
                    x-init='if (!document.getElementById("leaflet-css")) {
                    const link = document.createElement("link");
                    link.id = "leaflet-css";
                    link.rel = "stylesheet";
                    link.href = "https://unpkg.com/leaflet@1.9.4/dist/leaflet.css";
                    document.head.appendChild(link);
                }
                if (typeof L === "undefined") {
                    const script = document.createElement("script");
                    script.src = "https://unpkg.com/leaflet@1.9.4/dist/leaflet.js";
                    script.onload = () => {
                        map = L.map($refs.map).setView([37.9577, -121.2908], 8);
                        L.tileLayer("https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}.png", {
                            attribution: "&copy; <a href=\"http://www.openstreetmap.org/copyright\">OpenStreetMap</a>, &copy; <a href=\"https://carto.com/attributions\">CARTO</a>",
                            subdomains: "abcd",
                            maxZoom: 20,
                            minZoom: 0
                        }).addTo(map);
                        mapData.forEach(location => {
                            const circle = L.circle([location.lat, location.lng], {
                                color: "#E63946",
                                fillColor: "#E63946",
                                fillOpacity: 0.8,
                                radius: 750 + Math.sqrt(location.total_products) * 150,
                                weight: 2
                            }).addTo(map);
                            circle.bindPopup(`
                                <div class="text-sm">
                                    <div class="mb-1 font-bold">${location.name}</div>
                                    <div>Total Products: ${location.total_products.toLocaleString()}</div>
                                </div>
                            `);
                            circles.push(circle);
                        });
                        if (mapData.length > 0) {
                            const bounds = L.latLngBounds(mapData.map(loc => [loc.lat, loc.lng]));
                            map.fitBounds(bounds, { maxZoom: 8, padding: [50, 50] });
                        }
                    };
                    document.head.appendChild(script);
                }'
                    class="relative">
                    <div x-ref="map" style="height: 600px;"></div>
                </div>
            </div>
        </div>
    </x-filament-panels::page>
</div>
