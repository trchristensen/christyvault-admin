<?php

use App\Enums\PlantLocation;
use App\Filament\Widgets\WeeklyDeliveryWeatherWidget;
use App\Models\Location;
use App\Models\Order;
use App\Support\DeliveryWeatherDashboard;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-08-10 12:00:00');
    config([
        'cache.default' => 'array',
        'services.openweather.api_key' => 'weather-test-key',
    ]);
    Cache::clear();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function weatherTestPlant(string $city, float $latitude, float $longitude): Location
{
    return Location::factory()->create([
        'name' => "Christy Vault - {$city}",
        'city' => $city,
        'location_type' => 'christy_vault',
        'latitude' => $latitude,
        'longitude' => $longitude,
    ]);
}

function weatherTestDailyPayload(): array
{
    return collect(range(0, 7))->map(function (int $offset): array {
        $date = today()->addDays($offset);

        return [
            'dt' => $date->copy()->setTimezone('America/Los_Angeles')->setTime(12, 0)->timestamp,
            'temp' => ['min' => 52 + $offset, 'max' => 72 + $offset],
            'pop' => $offset === 2 ? 0.7 : 0.1,
            'wind_speed' => 9,
            'wind_gust' => 14,
            'weather' => [[
                'id' => $offset === 2 ? 500 : 800,
                'description' => $offset === 2 ? 'light rain' : 'clear sky',
            ]],
        ];
    })->all();
}

function weatherTestNwsPeriods(): array
{
    return collect(range(0, 6))->flatMap(function (int $offset): array {
        $date = today()->addDays($offset);

        return [
            [
                'number' => ($offset * 2) + 1,
                'startTime' => $date->copy()->setTime(6, 0)->toIso8601String(),
                'endTime' => $date->copy()->setTime(18, 0)->toIso8601String(),
                'isDaytime' => true,
                'temperature' => 70 + $offset,
                'temperatureUnit' => 'F',
                'probabilityOfPrecipitation' => ['value' => $offset === 2 ? 60 : 10],
                'windSpeed' => '5 to 10 mph',
                'shortForecast' => $offset === 2 ? 'Rain Showers Likely' : 'Sunny',
            ],
            [
                'number' => ($offset * 2) + 2,
                'startTime' => $date->copy()->setTime(18, 0)->toIso8601String(),
                'endTime' => $date->copy()->addDay()->setTime(6, 0)->toIso8601String(),
                'isDaytime' => false,
                'temperature' => 50 + $offset,
                'temperatureUnit' => 'F',
                'probabilityOfPrecipitation' => ['value' => 10],
                'windSpeed' => '6 mph',
                'shortForecast' => 'Mostly Clear',
            ],
        ];
    })->all();
}

function weatherTestNwsCurrentPeriod(): array
{
    return [[
        'number' => 1,
        'startTime' => now()->startOfHour()->toIso8601String(),
        'endTime' => now()->startOfHour()->addHour()->toIso8601String(),
        'isDaytime' => true,
        'temperature' => 64,
        'temperatureUnit' => 'F',
        'probabilityOfPrecipitation' => ['value' => 10],
        'windSpeed' => '8 mph',
        'shortForecast' => 'Partly Sunny',
    ]];
}

it('shows both plants every day and adds unique nonlocal delivery cities only when scheduled', function (): void {
    weatherTestPlant('Colma', 37.6688, -122.4619);
    weatherTestPlant('Tulare', 36.4415, -119.3850);

    $sacramentoOne = Location::factory()->create([
        'name' => 'Sacramento Memorial One',
        'city' => 'Sacramento',
        'latitude' => 38.5816,
        'longitude' => -121.4944,
    ]);
    $sacramentoTwo = Location::factory()->create([
        'name' => 'Sacramento Memorial Two',
        'city' => '  Sacramento  ',
        'latitude' => 38.5900,
        'longitude' => -121.4800,
    ]);
    $localLane = Location::factory()->create([
        'name' => 'Oakland Local Stop',
        'city' => 'Oakland',
    ]);
    $localCity = Location::factory()->create([
        'name' => 'South San Francisco Local Stop',
        'city' => 'South San Francisco',
    ]);

    foreach ([$sacramentoOne, $sacramentoTwo] as $location) {
        Order::factory()->create([
            'location_id' => $location->getKey(),
            'assigned_delivery_date' => today()->addDays(2),
            'status' => 'confirmed',
            'plant_location' => PlantLocation::COLMA_MAIN->value,
        ]);
    }

    Order::factory()->create([
        'location_id' => $localLane->getKey(),
        'assigned_delivery_date' => today()->addDays(2),
        'status' => 'confirmed',
        'plant_location' => PlantLocation::COLMA_LOCALS->value,
    ]);
    Order::factory()->create([
        'location_id' => $localCity->getKey(),
        'assigned_delivery_date' => today()->addDays(2),
        'status' => 'confirmed',
        'plant_location' => PlantLocation::COLMA_MAIN->value,
    ]);

    Http::fake(fn (Request $request) => Http::response([
        'timezone' => 'America/Los_Angeles',
        'current' => [
            'dt' => now()->timestamp,
            'temp' => 63.4,
            'wind_speed' => 7.6,
            'weather' => [['id' => 801, 'description' => 'few clouds']],
        ],
        'daily' => weatherTestDailyPayload(),
    ]));

    $week = app(DeliveryWeatherDashboard::class)->week();
    $deliveryDay = $week['days'][2];
    $deliveryCities = collect($deliveryDay['destinations'])->pluck('city');
    $sacramento = collect($deliveryDay['destinations'])->firstWhere('city', 'Sacramento');
    $todayColma = collect($week['days'][0]['destinations'])->firstWhere('city', 'Colma');
    $initialRequestCount = Http::recorded()->count();

    expect($week['days'])->toHaveCount(7)
        ->and(collect($week['days'][0]['destinations'])->pluck('city'))->toContain('Colma', 'Tulare')
        ->and(collect($week['days'][0]['destinations'])->pluck('city'))->not->toContain('Sacramento')
        ->and($deliveryCities)->toContain('Colma', 'Tulare', 'Sacramento')
        ->and($deliveryCities)->not->toContain('Oakland', 'South San Francisco')
        ->and($sacramento['stop_count'])->toBe(2)
        ->and($sacramento['weather']['high'])->toBe(74)
        ->and($sacramento['weather']['warnings'])->toContain('70% rain')
        ->and($todayColma['weather']['current']['temperature'])->toBe(63)
        ->and($todayColma['weather']['current']['wind_speed'])->toBe(8)
        ->and($initialRequestCount)->toBeGreaterThanOrEqual(3);

    app(DeliveryWeatherDashboard::class)->week();

    expect(Http::recorded())->toHaveCount($initialRequestCount);

    Livewire::test(WeeklyDeliveryWeatherWidget::class)
        ->assertSee('Now')
        ->assertSee('H 72°')
        ->assertSee('L 52°');
});

it('keeps the widget usable when the forecast provider is unavailable', function (): void {
    weatherTestPlant('Colma', 37.6688, -122.4619);
    weatherTestPlant('Tulare', 36.4415, -119.3850);
    Http::fake(['*' => Http::response([], 503)]);

    Livewire::test(WeeklyDeliveryWeatherWidget::class)
        ->assertOk()
        ->assertSee('7-day delivery weather')
        ->assertSee('Colma')
        ->assertSee('Tulare')
        ->assertSee('Forecast unavailable');
});

it('uses the National Weather Service when no OpenWeather key is configured', function (): void {
    config(['services.openweather.api_key' => null]);
    weatherTestPlant('Colma', 37.6688, -122.4619);
    weatherTestPlant('Tulare', 36.4415, -119.3850);

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/points/')) {
            return Http::response([
                'properties' => [
                    'forecast' => 'https://api.weather.gov/gridpoints/MTR/88,126/forecast',
                    'forecastHourly' => 'https://api.weather.gov/gridpoints/MTR/88,126/forecast/hourly',
                ],
            ]);
        }

        if (str_ends_with($request->url(), '/forecast/hourly')) {
            return Http::response([
                'properties' => [
                    'periods' => weatherTestNwsCurrentPeriod(),
                ],
            ]);
        }

        return Http::response([
            'properties' => [
                'periods' => weatherTestNwsPeriods(),
            ],
        ]);
    });

    $week = app(DeliveryWeatherDashboard::class)->week();
    $todayColma = collect($week['days'][0]['destinations'])->firstWhere('city', 'Colma');
    $colma = collect($week['days'][2]['destinations'])->firstWhere('city', 'Colma');

    expect($todayColma['weather']['current']['temperature'])->toBe(64)
        ->and($todayColma['weather']['current']['wind_speed'])->toBe(8)
        ->and($colma['weather']['high'])->toBe(72)
        ->and($colma['weather']['low'])->toBe(52)
        ->and($colma['weather']['rain_chance'])->toBe(60)
        ->and($colma['weather']['symbol'])->toBe('🌧️');

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('User-Agent')
        && str_starts_with($request->url(), 'https://api.weather.gov/'));
});
