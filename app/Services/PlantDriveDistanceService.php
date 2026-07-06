<?php

namespace App\Services;

use App\Enums\PlantLocation;
use App\Models\Location;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PlantDriveDistanceService
{
    public function isConfigured(): bool
    {
        return filled(config('services.openrouteservice.api_key'));
    }

    public function distanceFromDefaultPlant(Location $location): ?array
    {
        if (! $location->hasCoordinates()) {
            return null;
        }

        $origin = $this->originFor($location);

        if (! $origin?->hasCoordinates()) {
            return null;
        }

        if ($origin->is($location)) {
            return [
                'origin_location_id' => $origin->id,
                'distance_miles' => 0.0,
                'duration_minutes' => 0,
                'provider' => 'local',
                'route_geometry' => [
                    [(float) $location->latitude, (float) $location->longitude],
                ],
            ];
        }

        $route = $this->route(
            (float) $origin->latitude,
            (float) $origin->longitude,
            (float) $location->latitude,
            (float) $location->longitude,
        );

        return [
            'origin_location_id' => $origin->id,
            'distance_miles' => round($route['distance_meters'] / 1609.344, 2),
            'duration_minutes' => (int) ceil($route['duration_seconds'] / 60),
            'provider' => 'openrouteservice',
            'route_geometry' => $route['route_geometry'],
        ];
    }

    public function originFor(Location $location): ?Location
    {
        if ($this->isPlantLocation($location)) {
            return $location;
        }

        $plantLocation = $location->default_plant_location;

        if (! $plantLocation instanceof PlantLocation) {
            $plantLocation = PlantLocation::tryFrom((string) $plantLocation) ?? PlantLocation::COLMA_MAIN;
        }

        return match ($plantLocation) {
            PlantLocation::TULARE_PLANT => $this->tularePlant(),
            PlantLocation::COLMA_MAIN, PlantLocation::COLMA_LOCALS => $this->colmaPlant(),
        };
    }

    protected function colmaPlant(): ?Location
    {
        return $this->configuredPlant('colma_location_id')
            ?? $this->plantByName('colma');
    }

    protected function tularePlant(): ?Location
    {
        return $this->configuredPlant('tulare_location_id')
            ?? $this->plantByName('tulare');
    }

    protected function configuredPlant(string $configKey): ?Location
    {
        $id = config("services.plant_locations.{$configKey}");

        if (! $id) {
            return null;
        }

        return Location::query()->find($id);
    }

    protected function plantByName(string $name): ?Location
    {
        return Location::query()
            ->where('location_type', 'christy_vault')
            ->whereRaw('LOWER(name) LIKE ?', ["%{$name}%"])
            ->first();
    }

    protected function isPlantLocation(Location $location): bool
    {
        if ($location->location_type !== 'christy_vault') {
            return false;
        }

        $name = strtolower($location->name);

        return str_contains($name, 'colma') || str_contains($name, 'tulare');
    }

    protected function route(float $originLatitude, float $originLongitude, float $destinationLatitude, float $destinationLongitude): array
    {
        $apiKey = config('services.openrouteservice.api_key');

        if (! $apiKey) {
            throw new RuntimeException('OPENROUTESERVICE_API_KEY is not configured.');
        }

        $baseUrl = rtrim((string) config('services.openrouteservice.base_url', 'https://api.openrouteservice.org'), '/');

        $response = Http::withHeaders([
            'Authorization' => $apiKey,
            'Content-Type' => 'application/json',
        ])
            ->timeout(20)
            ->retry(2, 500)
            ->post("{$baseUrl}/v2/directions/driving-car/geojson", [
                'coordinates' => [
                    [$originLongitude, $originLatitude],
                    [$destinationLongitude, $destinationLatitude],
                ],
                'radiuses' => [2500, 2500],
                'instructions' => false,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("openrouteservice route request failed with HTTP {$response->status()}.");
        }

        $summary = $response->json('features.0.properties.summary');

        if (! is_array($summary) || ! isset($summary['distance'], $summary['duration'])) {
            throw new RuntimeException('openrouteservice did not return a route summary.');
        }

        $coordinates = $response->json('features.0.geometry.coordinates');

        return [
            'distance_meters' => (float) $summary['distance'],
            'duration_seconds' => (float) $summary['duration'],
            'route_geometry' => collect(is_array($coordinates) ? $coordinates : [])
                ->filter(fn($coordinate): bool => is_array($coordinate) && isset($coordinate[0], $coordinate[1]))
                ->map(fn(array $coordinate): array => [
                    (float) $coordinate[1],
                    (float) $coordinate[0],
                ])
                ->values()
                ->all(),
        ];
    }
}
