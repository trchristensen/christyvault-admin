<?php

use App\Enums\PlantLocation;
use App\Filament\Widgets\OfficeManagerAttentionWidget;
use App\Filament\Widgets\PlanningOpportunitiesWidget;
use App\Filament\Widgets\TodayTomorrowBriefingWidget;
use App\Models\CalendarDay;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Order;
use App\Models\Position;
use App\Models\Trip;
use App\Models\User;
use App\Models\VehicleConfiguration;
use App\Support\OfficeManagerDashboard;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

it('opens the admin dashboard instead of redirecting to the delivery calendar', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('admin', 'web'));

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertSee('admin-dashboard-styles.css', false)
        ->assertSee('Your daily briefing across deliveries, employees, and customer follow-up.')
        ->assertSee('Delivery calendar')
        ->assertSee('New order');
});

it('registers and renders the useful admin dashboard widgets', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('admin', 'web'));

    $this->actingAs($user);
    Filament::setCurrentPanel('admin');

    $widgets = Filament::getPanel('admin')->getWidgets();

    expect($widgets)->toContain(
        OfficeManagerAttentionWidget::class,
        TodayTomorrowBriefingWidget::class,
        PlanningOpportunitiesWidget::class,
    );

    Livewire::test(OfficeManagerAttentionWidget::class)
        ->assertOk()
        ->assertSee('Needs attention')
        ->assertSeeHtml('admin-dashboard-attention-section');

    Livewire::test(TodayTomorrowBriefingWidget::class)
        ->assertOk()
        ->assertSee('Today and next workday')
        ->assertSee('Employees off');

    Livewire::test(PlanningOpportunitiesWidget::class)
        ->assertOk()
        ->assertSee('Planning opportunities');
});

it('groups next-workday printing and assignment problems at the trip level', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('admin', 'web'));

    $this->actingAs($user);
    Filament::setCurrentPanel('admin');

    $nextWorkday = app(OfficeManagerDashboard::class)->nextWorkday(today());
    $location = Location::factory()->create();
    $trip = Trip::withoutEvents(fn (): Trip => Trip::create([
        'trip_number' => 'TRIP-BRIEFING-TEST',
        'uuid' => (string) str()->uuid(),
        'status' => 'pending',
        'scheduled_date' => $nextWorkday,
    ]));

    $order = Order::factory()->create([
        'location_id' => $location->id,
        'trip_id' => $trip->id,
        'assigned_delivery_date' => $nextWorkday,
        'status' => 'confirmed',
        'is_printed' => false,
        'plant_location' => PlantLocation::COLMA_MAIN->value,
        'customer_order_number' => 'CUSTOMER-PO-42',
    ]);

    $item = collect(app(OfficeManagerDashboard::class)->attentionItems())
        ->first(fn (array $item): bool => str_starts_with($item['title'], 'TRIP-BRIEFING-TEST'));
    $printIssue = collect($item['issues'])->firstWhere('type', 'print_tags');

    expect($item)->not->toBeNull()
        ->and($item['title'])->toContain($location->name)
        ->and($item['summary'])->toContain('1 order')
        ->and($item['summary'])->toContain('Colma')
        ->and($item['orders'][0]['number'])->toBe($order->order_number)
        ->and($item['orders'][0]['status'])->toBe('Confirmed')
        ->and($item['orders'][0]['customer_order_number'])->toBe('CUSTOMER-PO-42')
        ->and($item['detail'])->toContain('Driver missing')
        ->and($item['detail'])->toContain('Vehicle missing')
        ->and($item['detail'])->toContain('1 tag not printed')
        ->and($printIssue['orders'])->toBe([
            [
                'number' => $order->order_number,
                'url' => route('orders.print', ['order' => $order]),
            ],
        ]);

    Livewire::test(OfficeManagerAttentionWidget::class)
        ->assertSee("Print {$order->order_number}")
        ->assertSeeHtml('href="'.route('orders.print', ['order' => $order]).'"')
        ->assertSeeHtml('target="_blank"');
});

it('assigns a missing driver from the attention widget without confirming the stop order', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('admin', 'web'));

    $this->actingAs($user);
    Filament::setCurrentPanel('admin');

    $driver = Employee::query()->forceCreate([
        'name' => 'Dashboard Driver',
        'email' => 'dashboard-driver@example.test',
        'is_active' => true,
        'christy_location' => 'colma',
        'hire_date' => today()->subYear(),
        'birth_date' => today()->subYears(30),
    ]);
    $driver->positions()->attach(Position::query()->firstOrCreate(
        ['name' => 'driver'],
        ['display_name' => 'Driver'],
    ));
    $vehicle = VehicleConfiguration::query()->firstOrCreate(
        ['code' => 'dashboard-driver-assignment-vehicle'],
        ['name' => 'Dashboard assignment truck', 'configuration_type' => 'boom_truck', 'is_active' => true],
    );
    $trip = Trip::withoutEvents(fn (): Trip => Trip::create([
        'trip_number' => 'TRIP-DASHBOARD-DRIVER',
        'uuid' => (string) str()->uuid(),
        'vehicle_configuration_id' => $vehicle->id,
        'status' => 'pending',
        'scheduled_date' => app(OfficeManagerDashboard::class)->nextWorkday(today()),
    ]));
    $order = Order::factory()->create([
        'location_id' => Location::factory()->create()->id,
        'trip_id' => $trip->id,
        'assigned_delivery_date' => $trip->scheduled_date,
        'status' => 'confirmed',
        'plant_location' => PlantLocation::COLMA_MAIN->value,
        'is_printed' => true,
    ]);

    Livewire::test(OfficeManagerAttentionWidget::class)
        ->assertSee('Driver missing')
        ->set("driverSelections.{$trip->id}", $driver->id)
        ->call('assignDriver', $trip->id)
        ->assertHasNoErrors();

    $attentionItem = collect(app(OfficeManagerDashboard::class)->attentionItems())
        ->first(fn (array $item): bool => str_starts_with($item['title'], 'TRIP-DASHBOARD-DRIVER'));

    expect($trip->fresh()->driver_id)->toBe($driver->id)
        ->and($trip->fresh()->dispatch_confirmed_at)->toBeNull()
        ->and($order->fresh()->driver_id)->toBe($driver->id)
        ->and($attentionItem)->toBeNull();
});

it('rejects a non-driver selected from a dashboard driver assignment', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('admin', 'web'));

    $this->actingAs($user);
    Filament::setCurrentPanel('admin');

    $employee = Employee::query()->forceCreate([
        'name' => 'Not A Driver',
        'email' => 'not-a-driver@example.test',
        'is_active' => true,
        'christy_location' => 'colma',
        'hire_date' => today()->subYear(),
        'birth_date' => today()->subYears(30),
    ]);
    $trip = Trip::withoutEvents(fn (): Trip => Trip::create([
        'trip_number' => 'TRIP-DASHBOARD-INVALID-DRIVER',
        'uuid' => (string) str()->uuid(),
        'status' => 'pending',
        'scheduled_date' => app(OfficeManagerDashboard::class)->nextWorkday(today()),
    ]));
    Order::factory()->create([
        'location_id' => Location::factory()->create()->id,
        'trip_id' => $trip->id,
        'assigned_delivery_date' => $trip->scheduled_date,
        'status' => 'confirmed',
        'plant_location' => PlantLocation::COLMA_MAIN->value,
        'is_printed' => true,
    ]);

    Livewire::test(OfficeManagerAttentionWidget::class)
        ->set("driverSelections.{$trip->id}", $employee->id)
        ->call('assignDriver', $trip->id)
        ->assertHasErrors("driverSelections.{$trip->id}");

    expect($trip->fresh()->driver_id)->toBeNull();
});

it('identifies the actual unscheduled orders and makes the alert title clickable', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('admin', 'web'));

    $this->actingAs($user);
    Filament::setCurrentPanel('admin');

    $location = Location::factory()->create(['name' => 'Dashboard Detail Cemetery']);
    $order = Order::factory()->create([
        'location_id' => $location->id,
        'assigned_delivery_date' => null,
        'status' => 'in_production',
        'plant_location' => PlantLocation::COLMA_MAIN->value,
    ]);
    $item = collect(app(OfficeManagerDashboard::class)->attentionItems())
        ->firstWhere('eyebrow', 'Scheduling');
    $detail = collect($item['orders'])->firstWhere('number', $order->order_number);

    expect($detail)->not->toBeNull()
        ->and($detail['location'])->toBe('Dashboard Detail Cemetery')
        ->and($detail['status'])->toBe('In Production');

    Livewire::test(OfficeManagerAttentionWidget::class)
        ->assertSeeHtml('class="om-attention-title"')
        ->assertSee('Dashboard Detail Cemetery')
        ->assertSee('In Production');
});

it('does not treat hand-printed Tulare orders as missing printed tags', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('admin', 'web'));

    $this->actingAs($user);
    Filament::setCurrentPanel('admin');

    $driver = Employee::query()->forceCreate([
        'name' => 'Tulare Test Driver',
        'email' => 'tulare-dashboard-driver@example.test',
        'is_active' => true,
        'christy_location' => 'tulare',
        'hire_date' => today()->subYear(),
        'birth_date' => today()->subYears(30),
    ]);
    $vehicle = VehicleConfiguration::query()->firstOrCreate(
        ['code' => 'dashboard-test-vehicle'],
        ['name' => 'Dashboard test truck', 'configuration_type' => 'boom_truck', 'is_active' => true],
    );
    $trip = Trip::withoutEvents(fn (): Trip => Trip::create([
        'trip_number' => 'TRIP-TULARE-HAND-PRINTED',
        'uuid' => (string) str()->uuid(),
        'driver_id' => $driver->id,
        'vehicle_configuration_id' => $vehicle->id,
        'status' => 'pending',
        'scheduled_date' => app(OfficeManagerDashboard::class)->nextWorkday(today()),
    ]));

    Order::factory()->create([
        'location_id' => Location::factory()->create()->id,
        'trip_id' => $trip->id,
        'assigned_delivery_date' => $trip->scheduled_date,
        'status' => 'confirmed',
        'plant_location' => PlantLocation::TULARE_PLANT->value,
        'is_printed' => false,
    ]);

    expect(collect(app(OfficeManagerDashboard::class)->attentionItems())
        ->contains(fn (array $item): bool => str_starts_with($item['title'], 'TRIP-TULARE-HAND-PRINTED')))
        ->toBeFalse();
});

it('shows upcoming company calendar days for the rest of this week', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('admin', 'web'));

    $this->actingAs($user);
    Filament::setCurrentPanel('admin');

    CalendarDay::query()->create([
        'date' => today(),
        'name' => 'Company picnic reminder',
        'type' => CalendarDay::TYPE_NOTE,
        'blocks_delivery' => false,
        'opens_delivery' => false,
        'notes' => 'Lunch starts at noon.',
    ]);

    Livewire::test(TodayTomorrowBriefingWidget::class)
        ->assertSee('Upcoming calendar days this week')
        ->assertSee('Company picnic reminder')
        ->assertSee('Calendar Note')
        ->assertSee('Lunch starts at noon.');
});

it('renders the shared compact load diagram used by orders and planning opportunities', function (): void {
    $html = Blade::render('<x-load-planning.compact-diagram :diagram="$diagram" />', [
        'diagram' => [
            'available' => true,
            'racks' => [
                [
                    'number' => 1,
                    'type_code' => 'standard_2_high',
                    'level_count' => 2,
                    'cells' => [
                        ['code' => 'A1', 'stop_sequence' => 1],
                        null,
                    ],
                ],
                [
                    'number' => 2,
                    'type_code' => null,
                    'level_count' => 0,
                    'cells' => [],
                ],
            ],
            'flatbed_pallet_capacity' => 1,
            'flatbed_pallets' => [],
        ],
    ]);

    expect($html)
        ->toContain('Rack placement')
        ->toContain('A1')
        ->toContain('R1')
        ->toContain('Open')
        ->toContain('Flatbed fallback');
});
