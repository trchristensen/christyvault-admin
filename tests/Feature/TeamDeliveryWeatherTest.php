<?php

use App\Filament\Team\Pages\Schedule;
use App\Filament\Team\Widgets\TodaysDeliveriesWidget;
use App\Models\Location;
use App\Models\Order;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Filament::setCurrentPanel('team');
    Carbon::setTestNow(Carbon::parse('2026-08-10 10:00:00', 'America/Los_Angeles'));
    config([
        'cache.default' => 'array',
        'services.openweather.api_key' => 'team-weather-test-key',
    ]);
    Cache::clear();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function teamWeatherUser(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('driver', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('view team delivery schedule', 'web'));

    return $user;
}

function fakeTeamDeliveryWeather(): void
{
    Http::fake(fn (Request $request) => Http::response([
        'timezone' => 'America/Los_Angeles',
        'daily' => collect(range(0, 7))->map(function (int $offset): array {
            $date = now('America/Los_Angeles')->startOfDay()->addDays($offset);

            return [
                'dt' => $date->copy()->setTime(12, 0)->timestamp,
                'temp' => ['min' => 52 + $offset, 'max' => 72 + $offset],
                'pop' => match ($offset) {
                    0 => 0.7,
                    1 => 0,
                    default => 0.1,
                },
                'wind_speed' => 9,
                'wind_gust' => 14,
                'weather' => [[
                    'id' => match ($offset) {
                        0 => 500,
                        1 => 0,
                        default => 800,
                    },
                    'description' => match ($offset) {
                        0 => 'light rain',
                        1 => 'forecast available',
                        default => 'clear sky',
                    },
                ]],
            ];
        })->all(),
    ]));
}

it('shows a subtle destination forecast beneath addresses on the team dashboard and schedule', function (): void {
    $location = Location::factory()->create([
        'name' => 'Forecast Memorial Park',
        'address_line1' => '123 Forecast Way',
        'city' => 'Sacramento',
        'state' => 'CA',
        'postal_code' => '95814',
        'latitude' => 38.5816,
        'longitude' => -121.4944,
    ]);
    $order = Order::factory()->create([
        'location_id' => $location->getKey(),
        'assigned_delivery_date' => '2026-08-10',
        'plant_location' => 'colma_main',
        'status' => 'confirmed',
    ]);
    $dryLocation = Location::factory()->create([
        'name' => 'Dry Forecast Cemetery',
        'latitude' => 38.6010,
        'longitude' => -121.5000,
    ]);
    Order::factory()->create([
        'location_id' => $dryLocation->getKey(),
        'assigned_delivery_date' => '2026-08-11',
        'plant_location' => 'colma_main',
        'status' => 'confirmed',
    ]);
    fakeTeamDeliveryWeather();
    $this->actingAs(teamWeatherUser());

    Livewire::test(TodaysDeliveriesWidget::class)
        ->assertSee('123 Forecast Way')
        ->assertSee('Light rain · L 52° · H 72° · 70% rain')
        ->assertSee('Dry Forecast Cemetery')
        ->assertSee('L 53° · H 73°')
        ->assertDontSee('Forecast available · L 53°')
        ->assertDontSee(' · 0% rain')
        ->assertDontSee('🌡️')
        ->assertSeeHtml('delivery-order-weather is-warning');
    $dashboardRequestCount = Http::recorded()->count();

    Livewire::test(Schedule::class)
        ->assertSet('selectedDate', '2026-08-10')
        ->assertSee('123 Forecast Way')
        ->assertSee('Light rain · L 52° · H 72° · 70% rain')
        ->assertSeeHtml('delivery-order-weather is-warning');

    expect(Http::recorded())->toHaveCount($dashboardRequestCount)
        ->and($dashboardRequestCount)->toBeGreaterThan(0)
        ->and($order->fresh()->location_id)->toBe($location->getKey());
});

it('omits weather when an order is outside the forecast window', function (): void {
    $location = Location::factory()->create([
        'name' => 'Future Memorial Park',
        'latitude' => 38.5816,
        'longitude' => -121.4944,
    ]);
    Order::factory()->create([
        'location_id' => $location->getKey(),
        'assigned_delivery_date' => '2026-08-20',
        'plant_location' => 'colma_main',
        'status' => 'confirmed',
    ]);
    fakeTeamDeliveryWeather();
    $this->actingAs(teamWeatherUser());

    Livewire::withQueryParams(['date' => '2026-08-20'])
        ->test(Schedule::class)
        ->assertSee('Future Memorial Park')
        ->assertDontSeeHtml('delivery-order-weather');

    Http::assertNothingSent();
});
