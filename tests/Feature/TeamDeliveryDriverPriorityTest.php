<?php

use App\Filament\Team\Pages\Schedule;
use App\Filament\Team\Widgets\TodaysDeliveriesWidget;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Order;
use App\Models\Trip;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Filament::setCurrentPanel('team');
    Carbon::setTestNow(Carbon::parse('2026-08-06 10:00:00', 'America/Los_Angeles'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function priorityTestEmployee(string $name, bool $withLogin = false): array
{
    $user = $withLogin ? User::factory()->create(['name' => $name]) : null;

    if ($user) {
        $user->assignRole(Role::findOrCreate('driver', 'web'));
        $user->givePermissionTo(Permission::findOrCreate('view team delivery schedule', 'web'));
    }

    $employee = Employee::create([
        'user_id' => $user?->getKey(),
        'name' => $name,
        'email' => $user?->email,
        'is_active' => true,
        'christy_location' => 'colma',
    ]);

    return [$user, $employee];
}

function priorityTestTrip(int $driverId, string $number): Trip
{
    return Trip::withoutEvents(fn (): Trip => Trip::create([
        'trip_number' => $number,
        'driver_id' => $driverId,
        'status' => 'confirmed',
        'scheduled_date' => '2026-08-06',
        'uuid' => (string) Str::uuid(),
    ]));
}

function priorityTestOrder(string $customer, Trip $trip, int $staleOrderDriverId, int $stop): Order
{
    $order = Order::factory()->create([
        'location_id' => Location::factory()->create(['name' => $customer])->getKey(),
        'status' => 'ready_for_delivery',
        'is_printed' => true,
    ]);

    $order->forceFill([
        'trip_id' => $trip->getKey(),
        'driver_id' => $staleOrderDriverId,
        'assigned_delivery_date' => '2026-08-06',
        'plant_location' => 'colma_main',
        'stop_number' => $stop,
    ])->saveQuietly();

    return $order;
}

it('puts the signed-in drivers complete trips first on the dashboard and schedule', function (): void {
    [$user, $employee] = priorityTestEmployee('Maite Arias', withLogin: true);
    [, $otherDriver] = priorityTestEmployee('Other Driver');

    $otherTrip = priorityTestTrip($otherDriver->getKey(), 'TEST-OTHER-TRIP');
    $mineTrip = priorityTestTrip($employee->getKey(), 'TEST-MAITE-TRIP');

    priorityTestOrder('Other Stop One', $otherTrip, $employee->getKey(), 1);
    priorityTestOrder('Other Stop Two', $otherTrip, $employee->getKey(), 2);
    $mineFirst = priorityTestOrder('My Stop One', $mineTrip, $otherDriver->getKey(), 1);
    priorityTestOrder('My Stop Two', $mineTrip, $otherDriver->getKey(), 2);

    expect($mineFirst->fresh()->load('trip')->assignedDeliveryDriverId())->toBe($employee->getKey())
        ->and($mineFirst->isAssignedToEmployee($employee->getKey()))->toBeTrue();

    $this->actingAs($user);

    Livewire::test(TodaysDeliveriesWidget::class)
        ->assertSeeInOrder(['My Stop One', 'My Stop Two', 'Other Stop One', 'Other Stop Two']);

    Livewire::test(Schedule::class)
        ->assertSeeInOrder(['My Stop One', 'My Stop Two', 'Other Stop One', 'Other Stop Two']);
});
