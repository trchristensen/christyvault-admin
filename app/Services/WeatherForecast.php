<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class WeatherForecast
{
    /**
     * Return daily forecasts keyed first by the caller's location key and then
     * by local forecast date. Cached locations do not make another API call.
     *
     * @param  array<string, array{latitude: float, longitude: float}>  $locations
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function dailyFor(array $locations): array
    {
        $provider = filled(config('services.openweather.api_key')) ? 'openweather' : 'nws';
        $forecasts = [];
        $missing = [];

        foreach ($locations as $key => $location) {
            $cached = Cache::get($this->cacheKey($provider, $location['latitude'], $location['longitude']));

            if (is_array($cached)) {
                $forecasts[$key] = $cached;
            } else {
                $missing[$key] = $location;
            }
        }

        if ($missing === []) {
            return $forecasts;
        }

        if ($provider === 'openweather') {
            $fresh = $this->fetchOpenWeather($missing);
            $fallbackLocations = collect($missing)
                ->filter(fn (array $_location, string $key): bool => ($fresh[$key] ?? []) === [])
                ->all();

            if ($fallbackLocations !== []) {
                $fallback = $this->fetchNationalWeatherService($fallbackLocations);

                foreach ($fallbackLocations as $key => $_location) {
                    if (($fallback[$key] ?? []) !== []) {
                        $fresh[$key] = $fallback[$key];
                    }
                }
            }
        } else {
            $fresh = $this->fetchNationalWeatherService($missing);
        }

        foreach ($missing as $key => $location) {
            $forecast = $fresh[$key] ?? [];

            Cache::put(
                $this->cacheKey($provider, $location['latitude'], $location['longitude']),
                $forecast,
                now()->addMinutes($forecast === [] ? 5 : 30),
            );
            $forecasts[$key] = $forecast;
        }

        return $forecasts;
    }

    /**
     * @param  array<string, array{latitude: float, longitude: float}>  $locations
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function fetchOpenWeather(array $locations): array
    {
        try {
            $responses = Http::pool(fn (Pool $pool): array => collect($locations)
                ->map(fn (array $location, string $key) => $pool
                    ->as($key)
                    ->timeout(8)
                    ->get('https://api.openweathermap.org/data/3.0/onecall', [
                        'lat' => $location['latitude'],
                        'lon' => $location['longitude'],
                        'appid' => config('services.openweather.api_key'),
                        'units' => 'imperial',
                        'exclude' => 'minutely,hourly,alerts',
                    ]))
                ->values()
                ->all());
        } catch (Throwable $exception) {
            $this->logBatchFailure('OpenWeather', $exception);

            return [];
        }

        return collect($locations)->mapWithKeys(function (array $_location, string $key) use ($responses): array {
            $response = $responses[$key] ?? null;

            if (! $response instanceof Response || ! $response->successful()) {
                $this->logResponseFailure('OpenWeather', $key, $response);

                return [$key => []];
            }

            return [$key => $this->parseOpenWeather($response)];
        })->all();
    }

    /**
     * The NWS API is open U.S. government data and requires no key. Its point
     * lookup is cached separately because grid assignments change rarely.
     *
     * @param  array<string, array{latitude: float, longitude: float}>  $locations
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function fetchNationalWeatherService(array $locations): array
    {
        $forecastUrls = [];
        $unresolved = [];

        foreach ($locations as $key => $location) {
            $url = Cache::get($this->nwsPointCacheKey($location['latitude'], $location['longitude']));

            if (is_array($url) && $this->hasValidNwsUrls($url)) {
                $forecastUrls[$key] = $url;
            } else {
                $unresolved[$key] = $location;
            }
        }

        if ($unresolved !== []) {
            try {
                $pointResponses = Http::pool(fn (Pool $pool): array => collect($unresolved)
                    ->map(fn (array $location, string $key) => $pool
                        ->as($key)
                        ->withHeaders($this->nwsHeaders())
                        ->timeout(8)
                        ->get(sprintf(
                            'https://api.weather.gov/points/%.4f,%.4f',
                            $location['latitude'],
                            $location['longitude'],
                        )))
                    ->values()
                    ->all());
            } catch (Throwable $exception) {
                $this->logBatchFailure('National Weather Service point lookup', $exception);
                $pointResponses = [];
            }

            foreach ($unresolved as $key => $location) {
                $response = $pointResponses[$key] ?? null;
                $url = $response instanceof Response && $response->successful()
                    ? [
                        'daily' => $response->json('properties.forecast'),
                        'hourly' => $response->json('properties.forecastHourly'),
                    ]
                    : null;

                if (! is_array($url) || ! $this->hasValidNwsUrls($url)) {
                    $this->logResponseFailure('National Weather Service point lookup', $key, $response);

                    continue;
                }

                Cache::put(
                    $this->nwsPointCacheKey($location['latitude'], $location['longitude']),
                    $url,
                    now()->addDays(7),
                );
                $forecastUrls[$key] = $url;
            }
        }

        if ($forecastUrls === []) {
            return [];
        }

        try {
            $forecastResponses = Http::pool(function (Pool $pool) use ($forecastUrls): array {
                $requests = [];

                foreach ($forecastUrls as $key => $urls) {
                    foreach (['daily', 'hourly'] as $period) {
                        $requests[] = $pool
                            ->as("{$key}.{$period}")
                            ->withHeaders($this->nwsHeaders())
                            ->timeout(8)
                            ->get($urls[$period]);
                    }
                }

                return $requests;
            });
        } catch (Throwable $exception) {
            $this->logBatchFailure('National Weather Service forecast', $exception);

            return [];
        }

        return collect($locations)->mapWithKeys(function (array $location, string $key) use ($forecastResponses): array {
            $response = $forecastResponses["{$key}.daily"] ?? null;
            $hourlyResponse = $forecastResponses["{$key}.hourly"] ?? null;

            if (! $response instanceof Response || ! $response->successful()) {
                $this->logResponseFailure('National Weather Service forecast', $key, $response);
                Cache::forget($this->nwsPointCacheKey($location['latitude'], $location['longitude']));

                return [$key => []];
            }

            $forecast = $this->parseNationalWeatherService($response);

            if ($hourlyResponse instanceof Response && $hourlyResponse->successful()) {
                $forecast = $this->attachNationalWeatherServiceCurrent($forecast, $hourlyResponse);
            } else {
                $this->logResponseFailure('National Weather Service hourly forecast', $key, $hourlyResponse);
            }

            return [$key => $forecast];
        })->all();
    }

    /** @return array<string, array<string, mixed>> */
    private function parseOpenWeather(Response $response): array
    {
        $timezone = $response->json('timezone') ?: config('app.timezone');

        $forecast = collect($response->json('daily', []))
            ->filter(fn (mixed $day): bool => is_array($day) && isset($day['dt']))
            ->mapWithKeys(function (array $day) use ($timezone): array {
                $weather = $day['weather'][0] ?? [];
                $weatherId = (int) ($weather['id'] ?? 0);
                $high = isset($day['temp']['max']) ? (int) round($day['temp']['max']) : null;
                $low = isset($day['temp']['min']) ? (int) round($day['temp']['min']) : null;
                $wind = isset($day['wind_speed']) ? (int) round($day['wind_speed']) : null;
                $gust = isset($day['wind_gust']) ? (int) round($day['wind_gust']) : null;
                $rainChance = (int) round(((float) ($day['pop'] ?? 0)) * 100);
                $date = Carbon::createFromTimestamp((int) $day['dt'], $timezone)->toDateString();

                return [$date => $this->forecastDay(
                    high: $high,
                    low: $low,
                    rainChance: $rainChance,
                    wind: $wind,
                    gust: $gust,
                    description: str($weather['description'] ?? 'Forecast available')->ucfirst()->toString(),
                    symbol: $this->openWeatherSymbol((int) ($weather['id'] ?? 0)),
                    thunderstorms: $weatherId >= 200 && $weatherId < 300,
                )];
            })
            ->all();

        $current = $response->json('current');

        if (! is_array($current) || ! isset($current['dt'])) {
            return $forecast;
        }

        $date = Carbon::createFromTimestamp((int) $current['dt'], $timezone)->toDateString();

        if (! isset($forecast[$date])) {
            return $forecast;
        }

        $weather = $current['weather'][0] ?? [];
        $forecast[$date]['current'] = [
            'temperature' => isset($current['temp']) ? (int) round($current['temp']) : null,
            'description' => str($weather['description'] ?? 'Current conditions')->ucfirst()->toString(),
            'symbol' => $this->openWeatherSymbol((int) ($weather['id'] ?? 0)),
            'wind_speed' => isset($current['wind_speed']) ? (int) round($current['wind_speed']) : null,
        ];

        return $forecast;
    }

    /** @return array<string, array<string, mixed>> */
    private function parseNationalWeatherService(Response $response): array
    {
        return collect($response->json('properties.periods', []))
            ->filter(fn (mixed $period): bool => is_array($period) && filled($period['startTime'] ?? null))
            ->groupBy(fn (array $period): string => Carbon::parse($period['startTime'])->toDateString())
            ->map(function ($periods): array {
                $daytime = $periods->where('isDaytime', true);
                $nighttime = $periods->where('isDaytime', false);
                $description = (string) ($daytime->first()['shortForecast'] ?? $periods->first()['shortForecast'] ?? 'Forecast available');
                $allDescriptions = $periods->pluck('shortForecast')->filter()->join(' ');
                $rainChance = (int) $periods
                    ->map(fn (array $period): int => (int) data_get($period, 'probabilityOfPrecipitation.value', 0))
                    ->max();
                $wind = (int) $periods
                    ->map(fn (array $period): int => $this->maximumWindSpeed((string) ($period['windSpeed'] ?? '')))
                    ->max();

                return $this->forecastDay(
                    high: $daytime->isNotEmpty() ? (int) $daytime->max('temperature') : null,
                    low: $nighttime->isNotEmpty() ? (int) $nighttime->min('temperature') : null,
                    rainChance: $rainChance,
                    wind: $wind ?: null,
                    gust: null,
                    description: $description,
                    symbol: $this->descriptionSymbol($allDescriptions),
                    thunderstorms: str_contains(strtolower($allDescriptions), 'thunder'),
                );
            })
            ->all();
    }

    /** @return array<string, mixed> */
    private function forecastDay(
        ?int $high,
        ?int $low,
        int $rainChance,
        ?int $wind,
        ?int $gust,
        string $description,
        string $symbol,
        bool $thunderstorms,
    ): array {
        return [
            'high' => $high,
            'low' => $low,
            'rain_chance' => $rainChance,
            'wind_speed' => $wind,
            'wind_gust' => $gust,
            'description' => $description,
            'symbol' => $symbol,
            'warnings' => $this->warnings($high, $low, $wind, $gust, $rainChance, $thunderstorms),
            'updated_at' => now(),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $forecast
     * @return array<string, array<string, mixed>>
     */
    private function attachNationalWeatherServiceCurrent(array $forecast, Response $response): array
    {
        $period = $response->json('properties.periods.0');

        if (! is_array($period) || blank($period['startTime'] ?? null)) {
            return $forecast;
        }

        $date = Carbon::parse($period['startTime'])->toDateString();

        if (! isset($forecast[$date])) {
            return $forecast;
        }

        $description = (string) ($period['shortForecast'] ?? 'Current conditions');
        $forecast[$date]['current'] = [
            'temperature' => isset($period['temperature']) ? (int) $period['temperature'] : null,
            'description' => $description,
            'symbol' => $this->descriptionSymbol($description),
            'wind_speed' => $this->maximumWindSpeed((string) ($period['windSpeed'] ?? '')) ?: null,
        ];

        return $forecast;
    }

    /** @return array<int, string> */
    private function warnings(?int $high, ?int $low, ?int $wind, ?int $gust, int $rainChance, bool $thunderstorms): array
    {
        return collect([
            $thunderstorms ? 'Thunderstorms' : null,
            $rainChance >= 50 ? "{$rainChance}% rain" : null,
            ($gust ?? $wind) >= 30 ? 'Strong wind' : null,
            $high !== null && $high >= 95 ? 'High heat' : null,
            $low !== null && $low <= 35 ? 'Near freezing' : null,
        ])->filter()->values()->all();
    }

    private function openWeatherSymbol(int $weatherId): string
    {
        return match (true) {
            $weatherId >= 200 && $weatherId < 300 => '⛈️',
            $weatherId >= 300 && $weatherId < 600 => '🌧️',
            $weatherId >= 600 && $weatherId < 700 => '🌨️',
            $weatherId >= 700 && $weatherId < 800 => '🌫️',
            $weatherId === 800 => '☀️',
            $weatherId > 800 && $weatherId <= 802 => '🌤️',
            $weatherId > 802 => '☁️',
            default => '🌡️',
        };
    }

    private function descriptionSymbol(string $description): string
    {
        $description = strtolower($description);

        return match (true) {
            str_contains($description, 'thunder') => '⛈️',
            str_contains($description, 'snow') => '🌨️',
            str_contains($description, 'rain'), str_contains($description, 'shower') => '🌧️',
            str_contains($description, 'fog') => '🌫️',
            str_contains($description, 'partly'), str_contains($description, 'mostly sunny') => '🌤️',
            str_contains($description, 'cloud') => '☁️',
            str_contains($description, 'sunny'), str_contains($description, 'clear') => '☀️',
            default => '🌡️',
        };
    }

    private function maximumWindSpeed(string $windSpeed): int
    {
        preg_match_all('/\d+/', $windSpeed, $matches);

        return collect($matches[0] ?? [])->map(fn (string $speed): int => (int) $speed)->max() ?? 0;
    }

    /** @return array<string, string> */
    private function nwsHeaders(): array
    {
        return [
            'Accept' => 'application/geo+json',
            'User-Agent' => (string) config(
                'services.national_weather_service.user_agent',
                config('app.name').' ('.config('app.url').')',
            ),
        ];
    }

    private function isNwsUrl(string $url): bool
    {
        return str_starts_with($url, 'https://api.weather.gov/');
    }

    /** @param array<string, mixed> $urls */
    private function hasValidNwsUrls(array $urls): bool
    {
        return is_string($urls['daily'] ?? null)
            && is_string($urls['hourly'] ?? null)
            && $this->isNwsUrl($urls['daily'])
            && $this->isNwsUrl($urls['hourly']);
    }

    private function logBatchFailure(string $provider, Throwable $exception): void
    {
        Log::warning("Unable to load {$provider} delivery forecasts.", [
            'exception' => $exception->getMessage(),
        ]);
    }

    private function logResponseFailure(string $provider, string $key, mixed $response): void
    {
        Log::warning("{$provider} request failed.", [
            'status' => $response instanceof Response ? $response->status() : null,
            'location_key' => $key,
        ]);
    }

    private function cacheKey(string $provider, float $latitude, float $longitude): string
    {
        return sprintf('delivery-weather:v3:%s:%.4f:%.4f', $provider, $latitude, $longitude);
    }

    private function nwsPointCacheKey(float $latitude, float $longitude): string
    {
        return sprintf('delivery-weather:nws-point:v2:%.4f:%.4f', $latitude, $longitude);
    }
}
