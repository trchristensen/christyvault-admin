<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Enums\PlantLocation;
use App\Models\Location;
use App\Models\Order;
use App\Services\WeatherForecast;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class DeliveryWeatherDashboard
{
    public function __construct(private readonly WeatherForecast $forecast) {}

    /** @return array{days: array<int, array<string, mixed>>, updated_at: ?Carbon} */
    public function week(): array
    {
        $start = today();
        $end = $start->copy()->addDays(6);
        $destinations = $this->plantDestinations();
        $scheduledStops = [];

        $orders = Order::query()
            ->with('location')
            ->whereBetween('assigned_delivery_date', [$start, $end])
            ->whereIn('status', OrderStatus::activeDeliveryValues())
            ->where(fn ($query) => $query
                ->whereNull('plant_location')
                ->orWhere('plant_location', '!=', PlantLocation::COLMA_LOCALS->value))
            ->get()
            ->reject(fn (Order $order): bool => DeliveryArea::isLocalOrder($order));

        foreach ($orders as $order) {
            $location = $order->location;

            if (! $location || blank($location->city) || blank($location->state)) {
                continue;
            }

            $key = $this->cityKey($location->city, $location->state);
            $date = $order->assigned_delivery_date->toDateString();

            if (! isset($destinations[$key]) || (! $destinations[$key]['has_coordinates'] && $location->hasCoordinates())) {
                $destinations[$key] = $this->destination($location, false);
            }

            $scheduledStops[$date][$key][(string) $location->getKey()] = true;
        }

        $requests = collect($destinations)
            ->filter(fn (array $destination): bool => $destination['has_coordinates'])
            ->map(fn (array $destination): array => [
                'latitude' => $destination['latitude'],
                'longitude' => $destination['longitude'],
            ])
            ->all();
        $forecasts = $this->forecast->dailyFor($requests);
        $updatedAt = null;

        $days = collect(range(0, 6))->map(function (int $offset) use ($start, $destinations, $scheduledStops, $forecasts, &$updatedAt): array {
            $date = $start->copy()->addDays($offset);
            $dateKey = $date->toDateString();
            $keys = collect($destinations)
                ->filter(fn (array $destination): bool => $destination['is_plant'])
                ->keys()
                ->merge(array_keys($scheduledStops[$dateKey] ?? []))
                ->unique()
                ->sortBy(fn (string $key): string => sprintf(
                    '%d-%s',
                    $destinations[$key]['plant_sort'],
                    $destinations[$key]['city'],
                ));

            $dayDestinations = $keys->map(function (string $key) use ($dateKey, $destinations, $scheduledStops, $forecasts, &$updatedAt): array {
                $weather = $forecasts[$key][$dateKey] ?? null;

                if ($weather && ($updatedAt === null || $weather['updated_at']->isAfter($updatedAt))) {
                    $updatedAt = $weather['updated_at'];
                }

                return [
                    ...$destinations[$key],
                    'stop_count' => count($scheduledStops[$dateKey][$key] ?? []),
                    'weather' => $weather,
                ];
            })->values()->all();

            return [
                'date' => $date,
                'label' => $offset === 0 ? 'Today' : $date->format('D'),
                'destinations' => $dayDestinations,
            ];
        })->all();

        return ['days' => $days, 'updated_at' => $updatedAt];
    }

    /** @return array<string, array<string, mixed>> */
    private function plantDestinations(): array
    {
        $plants = Location::query()
            ->christyVault()
            ->get()
            ->mapWithKeys(fn (Location $location): array => [
                $location->physicalPlantLocation()?->value ?? 'unknown-'.$location->getKey() => $location,
            ]);

        return collect([
            PlantLocation::COLMA_MAIN->value => ['city' => 'Colma', 'state' => 'CA', 'sort' => 0],
            PlantLocation::TULARE_PLANT->value => ['city' => 'Tulare', 'state' => 'CA', 'sort' => 1],
        ])->mapWithKeys(function (array $fallback, string $plant) use ($plants): array {
            $location = $plants->get($plant);
            $key = $this->cityKey($fallback['city'], $fallback['state']);

            return [$key => $location
                ? [...$this->destination($location, true), 'plant_sort' => $fallback['sort']]
                : [
                    'key' => $key,
                    'city' => $fallback['city'],
                    'state' => $fallback['state'],
                    'latitude' => null,
                    'longitude' => null,
                    'has_coordinates' => false,
                    'is_plant' => true,
                    'plant_sort' => $fallback['sort'],
                ]];
        })->all();
    }

    /** @return array<string, mixed> */
    private function destination(Location $location, bool $isPlant): array
    {
        return [
            'key' => $this->cityKey($location->city, $location->state),
            'city' => Str::squish($location->city),
            'state' => Str::upper(trim($location->state)),
            'latitude' => $location->hasCoordinates() ? (float) $location->latitude : null,
            'longitude' => $location->hasCoordinates() ? (float) $location->longitude : null,
            'has_coordinates' => $location->hasCoordinates(),
            'is_plant' => $isPlant,
            'plant_sort' => $isPlant ? 0 : 2,
        ];
    }

    private function cityKey(string $city, string $state): string
    {
        return Str::lower(Str::squish($city)).'|'.Str::upper(trim($state));
    }
}
