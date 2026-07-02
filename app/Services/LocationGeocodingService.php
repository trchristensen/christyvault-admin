<?php

namespace App\Services;

use App\Models\Location;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class LocationGeocodingService
{
    public function geocodeLocation(Location $location): ?array
    {
        if (! $this->hasEnoughAddress($location)) {
            return null;
        }

        return $this->geocodeStructuredAddress($location)
            ?? $this->geocodeOneLineAddress($location);
    }

    protected function geocodeStructuredAddress(Location $location): ?array
    {
        $street = collect([
            $location->address_line1,
            $location->address_line2,
        ])->filter()->join(' ');

        $response = $this->request('locations/address', [
            'street' => $street,
            'city' => $location->city,
            'state' => $location->state,
            'zip' => $location->postal_code,
        ]);

        return $this->resultFromResponse($response);
    }

    protected function geocodeOneLineAddress(Location $location): ?array
    {
        $response = $this->request('locations/onelineaddress', [
            'address' => $location->full_address,
        ]);

        return $this->resultFromResponse($response);
    }

    protected function request(string $path, array $query): array
    {
        $baseUrl = rtrim((string) config('services.census_geocoder.base_url', 'https://geocoding.geo.census.gov/geocoder'), '/');
        $benchmark = config('services.census_geocoder.benchmark', 'Public_AR_Current');

        $response = Http::withHeaders([
            'User-Agent' => config('services.census_geocoder.user_agent', config('app.name', 'Christy Vault Admin') . ' location geocoder'),
        ])
            ->timeout(10)
            ->retry(2, 500)
            ->get("{$baseUrl}/{$path}", array_merge($query, [
                'benchmark' => $benchmark,
                'format' => 'json',
            ]));

        if (! $response->successful()) {
            throw new RuntimeException("Census geocoder request failed with HTTP {$response->status()}.");
        }

        return $response->json();
    }

    protected function resultFromResponse(array $response): ?array
    {
        $match = $response['result']['addressMatches'][0] ?? null;

        if (! is_array($match)) {
            return null;
        }

        $coordinates = $match['coordinates'] ?? null;

        if (! is_array($coordinates) || ! isset($coordinates['x'], $coordinates['y'])) {
            return null;
        }

        return [
            'latitude' => (float) $coordinates['y'],
            'longitude' => (float) $coordinates['x'],
            'matched_address' => $match['matchedAddress'] ?? null,
            'provider' => 'census',
        ];
    }

    protected function hasEnoughAddress(Location $location): bool
    {
        return filled($location->address_line1)
            && filled($location->city)
            && filled($location->state);
    }
}
