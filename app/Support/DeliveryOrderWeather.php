<?php

namespace App\Support;

use App\Models\Order;
use App\Services\WeatherForecast;
use Illuminate\Support\Collection;

final class DeliveryOrderWeather
{
    public function __construct(private readonly WeatherForecast $forecast) {}

    /**
     * @param  iterable<int, Order>  $orders
     * @return array<int, array{symbol: ?string, high: ?int, low: ?int, rain_chance: int, description: string, warnings: array<int, string>}>
     */
    public function forOrders(iterable $orders): array
    {
        $today = now('America/Los_Angeles')->toDateString();
        $forecastEnd = now('America/Los_Angeles')->addDays(6)->toDateString();
        $orders = collect($orders)
            ->filter(fn (mixed $order): bool => $order instanceof Order
                && $order->assigned_delivery_date !== null
                && $order->assigned_delivery_date->toDateString() >= $today
                && $order->assigned_delivery_date->toDateString() <= $forecastEnd
                && $order->location?->hasCoordinates())
            ->values();

        if ($orders->isEmpty()) {
            return [];
        }

        $locations = $orders
            ->mapWithKeys(fn (Order $order): array => [
                $this->locationKey($order) => [
                    'latitude' => (float) $order->location->latitude,
                    'longitude' => (float) $order->location->longitude,
                ],
            ])
            ->all();
        $forecasts = $this->forecast->dailyFor($locations);

        return $orders
            ->mapWithKeys(function (Order $order) use ($forecasts): array {
                $weather = $forecasts[$this->locationKey($order)][$order->assigned_delivery_date->toDateString()] ?? null;

                if (! is_array($weather)) {
                    return [];
                }

                $symbol = $weather['symbol'] ?? null;

                return [$order->getKey() => [
                    'symbol' => filled($symbol) && $symbol !== '🌡️' ? (string) $symbol : null,
                    'high' => isset($weather['high']) ? (int) $weather['high'] : null,
                    'low' => isset($weather['low']) ? (int) $weather['low'] : null,
                    'rain_chance' => (int) ($weather['rain_chance'] ?? 0),
                    'description' => (string) ($weather['description'] ?? 'Forecast available'),
                    'warnings' => Collection::wrap($weather['warnings'] ?? [])->values()->all(),
                ]];
            })
            ->all();
    }

    private function locationKey(Order $order): string
    {
        return sprintf(
            '%.4f,%.4f',
            (float) $order->location->latitude,
            (float) $order->location->longitude,
        );
    }
}
