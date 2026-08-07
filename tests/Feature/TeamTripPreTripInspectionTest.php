<?php

use App\Filament\Team\Pages\Schedule;
use App\Filament\Team\Widgets\EquipmentCareWidget;
use App\Filament\Team\Widgets\TodaysDeliveriesWidget;
use App\Models\Employee;
use App\Models\Location;
use App\Models\MaintenanceAsset;
use App\Models\MaintenanceRequest;
use App\Models\Order;
use App\Models\Trip;
use App\Models\TripPreTripInspection;
use App\Models\TripPreTripInspectionDefect;
use App\Models\User;
use App\Models\VehicleConfiguration;
use App\Notifications\TripPreTripDefectReported;
use App\Services\Maintenance\MaintenanceRequestConverter;
use App\Support\EquipmentCareChecklist;
use App\Support\TripDailyVehicleReportChecklist;
use App\Support\TripPreTripChecklist;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Filament::setCurrentPanel('team');
    Carbon::setTestNow(Carbon::parse('2026-08-07 07:15:00', 'America/Los_Angeles'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function preTripUser(string $name, string $role = 'driver'): array
{
    $user = User::factory()->create(['name' => $name]);
    $user->assignRole(Role::findOrCreate($role, 'web'));
    $user->givePermissionTo(Permission::findOrCreate('view team delivery schedule', 'web'));

    $employee = Employee::create([
        'user_id' => $user->getKey(),
        'name' => $name,
        'email' => $user->email,
        'is_active' => true,
        'christy_location' => 'colma',
    ]);

    return [$user, $employee];
}

function preTripPlant(string $plant = 'colma_main'): Location
{
    $name = $plant === 'tulare_plant' ? 'Christy Vault - Tulare' : 'Christy Vault - Colma';

    return Location::query()->firstOrCreate(['name' => $name], [
        'address_line1' => '1 Test Plant Way',
        'city' => $plant === 'tulare_plant' ? 'Tulare' : 'Colma',
        'state' => 'CA',
        'postal_code' => '94000',
        'location_type' => 'christy_vault',
    ]);
}

function preTripAsset(string $tag, string $category, string $plant = 'colma_main'): MaintenanceAsset
{
    return MaintenanceAsset::create([
        'asset_tag' => $tag,
        'name' => "Test {$category} {$tag}",
        'category' => $category,
        'status' => 'operational',
        'criticality' => 'high',
        'location_id' => preTripPlant($plant)->getKey(),
    ]);
}

function preTripConfiguration(bool $piggyback = true): VehicleConfiguration
{
    return VehicleConfiguration::create([
        'code' => 'test-pre-trip-'.Str::lower(Str::random(8)),
        'name' => $piggyback ? 'Rack trailer with piggyback' : 'Rack trailer without piggyback',
        'configuration_type' => VehicleConfiguration::TYPE_RACK_TRAILER,
        'rack_spot_count' => 8,
        'flatbed_pallet_capacity' => 4,
        'max_product_weight_lbs' => 38500,
        'piggyback_forklift_onboard' => $piggyback,
        'is_active' => true,
    ]);
}

function preTripScheduledTrip(Employee $driver, VehicleConfiguration $configuration): Trip
{
    $trip = Trip::withoutEvents(fn (): Trip => Trip::create([
        'trip_number' => 'TEST-PRETRIP-'.Str::upper(Str::random(6)),
        'driver_id' => $driver->getKey(),
        'vehicle_configuration_id' => $configuration->getKey(),
        'status' => 'confirmed',
        'scheduled_date' => '2026-08-07',
        'uuid' => (string) Str::uuid(),
    ]));
    $order = Order::factory()->create([
        'location_id' => Location::factory()->create()->getKey(),
        'status' => 'ready_for_delivery',
        'is_printed' => true,
    ]);
    $order->forceFill([
        'trip_id' => $trip->getKey(),
        'driver_id' => $driver->getKey(),
        'assigned_delivery_date' => '2026-08-07',
        'plant_location' => 'colma_main',
        'stop_number' => 1,
    ])->saveQuietly();

    return $trip;
}

function preTripPassingResponses(VehicleConfiguration $configuration): array
{
    return TripPreTripChecklist::items($configuration)
        ->mapWithKeys(fn (array $item, string $key): array => [
            $key => TripPreTripChecklist::RESPONSE_OK,
        ])
        ->all();
}

function dailyPassingResponses(): array
{
    return collect(TripDailyVehicleReportChecklist::items())
        ->mapWithKeys(fn (array $item, string $key): array => [
            $key => TripPreTripChecklist::RESPONSE_OK,
        ])
        ->all();
}

it('shows only the equipment sections required by the selected trip configuration', function (): void {
    $withPiggyback = preTripConfiguration(piggyback: true);
    $withoutPiggyback = preTripConfiguration(piggyback: false);

    expect(collect(TripPreTripChecklist::sections($withPiggyback))->pluck('key')->all())
        ->toContain('truck', 'trailer', 'piggyback')
        ->and(TripPreTripChecklist::items($withPiggyback))->toHaveCount(15)
        ->and(TripPreTripChecklist::sections($withPiggyback)[0]['items'])->toHaveCount(2)
        ->and(collect(TripPreTripChecklist::sections($withoutPiggyback))->pluck('key')->all())
        ->toContain('truck', 'trailer')
        ->not->toContain('piggyback');
});

it('records an optional equipment care check without requiring a trip', function (): void {
    [$user, $driver] = preTripUser('Careful Driver');
    $tractor = preTripAsset('CARE-TRACTOR', 'tractor');

    $this->actingAs($user);

    expect(data_get(EquipmentCareChecklist::items($tractor)->all(), 'engine_oil_level.description'))
        ->toContain('below or above the safe marks')
        ->and(data_get(EquipmentCareChecklist::items($tractor)->all(), 'manual_air_tanks.description'))
        ->toContain('excessive water or oil');

    Livewire::test(EquipmentCareWidget::class)
        ->assertSee('Equipment Care')
        ->callAction('submitEquipmentCare', [
            'asset_id' => $tractor->getKey(),
            'meter_reading' => 123456,
            'completed_tasks' => ['engine_oil_level', 'manual_air_tanks', 'tire_pressures'],
            'tire_readings' => [[
                'position' => 'Steer left',
                'psi' => 108,
                'target_psi' => 110,
            ]],
            'care_notes' => 'Drained the manual tanks and found only a small amount of moisture.',
            'has_issue' => false,
            'certification' => true,
        ])
        ->assertHasNoActionErrors();

    $inspection = TripPreTripInspection::query()
        ->where('report_type', TripPreTripInspection::TYPE_EQUIPMENT_CARE)
        ->sole();

    expect($inspection->trip_id)->toBeNull()
        ->and($inspection->user_id)->toBe($user->getKey())
        ->and($inspection->driver_id)->toBe($driver->getKey())
        ->and($inspection->safe_to_operate)->toBeTrue()
        ->and($inspection->truck_asset_id)->toBe($tractor->getKey())
        ->and($inspection->checklist_version)->toBe(EquipmentCareChecklist::VERSION)
        ->and(data_get($inspection->responses, 'completed_tasks'))->toHaveCount(3)
        ->and(data_get($inspection->responses, 'tire_readings.0.position'))->toBe('Steer left')
        ->and($inspection->assets)->toHaveCount(1);
});

it('routes a problem found during optional equipment care into maintenance', function (): void {
    NotificationFacade::fake();
    [$user] = preTripUser('Care Issue Driver');
    [$manager] = preTripUser('Care Issue Manager', 'manager');
    $manager->givePermissionTo(Permission::findOrCreate('manage delivery trip dispatch', 'web'));
    $trailer = preTripAsset('CARE-TRAILER', 'trailer');

    $this->actingAs($user);

    Livewire::test(EquipmentCareWidget::class)
        ->callAction('submitEquipmentCare', [
            'asset_id' => $trailer->getKey(),
            'completed_tasks' => ['tires_wheels'],
            'has_issue' => true,
            'issue_component' => 'Left rear tire',
            'issue_description' => 'Found a deep sidewall cut while checking the tire.',
            'operating_concern' => TripPreTripInspectionDefect::DRIVER_ASSESSMENT_STOP,
            'certification' => true,
        ])
        ->assertHasNoActionErrors();

    $inspection = TripPreTripInspection::query()
        ->where('report_type', TripPreTripInspection::TYPE_EQUIPMENT_CARE)
        ->sole();
    $defect = $inspection->inspectionDefects()->sole();
    $request = MaintenanceRequest::query()->where('asset_id', $trailer->getKey())->sole();

    expect($inspection->safe_to_operate)->toBeFalse()
        ->and($inspection->trip_id)->toBeNull()
        ->and($defect->component_label)->toBe('Left rear tire')
        ->and($defect->driver_assessment)->toBe(TripPreTripInspectionDefect::DRIVER_ASSESSMENT_STOP)
        ->and($trailer->refresh()->status)->toBe('out_of_service')
        ->and($request->title)->toBe('Equipment care issue — CARE-TRAILER')
        ->and($request->description)->toContain('Optional equipment care');

    NotificationFacade::assertSentTo($manager, TripPreTripDefectReported::class);

    $notification = (new TripPreTripDefectReported($inspection))->toDatabase($manager);

    expect($notification['title'])->toContain('Optional equipment care issue')
        ->and($notification['maintenance_asset_id'])->toBe($trailer->getKey())
        ->and($notification['trip_id'])->toBeNull();
});

it('allows only the assigned driver to submit and persists the equipment and certification snapshot', function (): void {
    [$user, $driver] = preTripUser('Assigned Driver');
    $configuration = preTripConfiguration();
    $trip = preTripScheduledTrip($driver, $configuration);
    $tractor = preTripAsset('TEST-TRACTOR', 'tractor');
    $trailer = preTripAsset('TEST-TRAILER', 'trailer');
    $piggyback = preTripAsset('TEST-PIGGYBACK', 'piggyback_forklift');

    $this->actingAs($user);

    Livewire::test(TodaysDeliveriesWidget::class)
        ->assertSee('Pre-trip inspection')
        ->mountAction('completeTripPreTripInspection', ['trip' => $trip->getKey()])
        ->assertActionMounted('completeTripPreTripInspection')
        ->unmountAction();

    $schedule = Livewire::test(Schedule::class)
        ->assertSee('Pre-trip inspection')
        ->assertSee("wire:click.stop=\"mountAction('completeTripPreTripInspection', { trip: {$trip->getKey()} })\"", false)
        ->mountAction('completeTripPreTripInspection', ['trip' => $trip->getKey()])
        ->assertActionMounted('completeTripPreTripInspection')
        ->unmountAction();

    $schedule
        ->callAction('completeTripPreTripInspection', [
            'truck_asset_id' => $tractor->getKey(),
            'trailer_asset_id' => $trailer->getKey(),
            'piggyback_asset_id' => $piggyback->getKey(),
            'responses' => preTripPassingResponses($configuration),
            'certification' => true,
        ], ['trip' => $trip->getKey()])
        ->assertHasNoActionErrors();

    $inspection = TripPreTripInspection::query()
        ->where('trip_id', $trip->getKey())
        ->sole();

    expect($inspection->trip_id)->toBe($trip->getKey())
        ->and($inspection->user_id)->toBe($user->getKey())
        ->and($inspection->driver_id)->toBe($driver->getKey())
        ->and($inspection->safe_to_operate)->toBeTrue()
        ->and($inspection->status)->toBe(TripPreTripInspection::STATUS_COMPLETED)
        ->and($inspection->report_type)->toBe(TripPreTripInspection::TYPE_PRE_TRIP)
        ->and($inspection->truck_asset_id)->toBe($tractor->getKey())
        ->and($inspection->trailer_asset_id)->toBe($trailer->getKey())
        ->and($inspection->piggyback_asset_id)->toBe($piggyback->getKey())
        ->and(data_get($inspection->equipment_snapshot, 'truck.asset_tag'))->toBe('TEST-TRACTOR')
        ->and($inspection->responses)->toHaveCount(TripPreTripChecklist::items($configuration)->count())
        ->and($inspection->certification_text)->toContain('found it safe to operate')
        ->and($inspection->assets)->toHaveCount(3);
});

it('does not show the completion control to a driver who is not assigned to the trip', function (): void {
    [, $assignedDriver] = preTripUser('Actually Assigned');
    [$otherUser] = preTripUser('Other Driver');
    $configuration = preTripConfiguration();
    $trip = preTripScheduledTrip($assignedDriver, $configuration);
    $tractor = preTripAsset('OTHER-TRACTOR', 'tractor');
    $trailer = preTripAsset('OTHER-TRAILER', 'trailer');
    $piggyback = preTripAsset('OTHER-PIGGYBACK', 'piggyback_forklift');

    $this->actingAs($otherUser);

    Livewire::test(Schedule::class)
        ->assertDontSee('Tap to begin · truck, load, and equipment');

    expect(fn () => Livewire::test(Schedule::class)
        ->callAction('completeTripPreTripInspection', [
            'truck_asset_id' => $tractor->getKey(),
            'trailer_asset_id' => $trailer->getKey(),
            'piggyback_asset_id' => $piggyback->getKey(),
            'responses' => preTripPassingResponses($configuration),
            'certification' => true,
        ], ['trip' => $trip->getKey()]))
        ->toThrow(AuthorizationException::class);

    expect(TripPreTripInspection::query()->where('trip_id', $trip->getKey())->exists())->toBeFalse();
});

it('allows only equipment assigned to the trip plant', function (): void {
    [$user, $driver] = preTripUser('Tulare Driver');
    $configuration = preTripConfiguration();
    $trip = preTripScheduledTrip($driver, $configuration);
    $trip->orders()->update(['plant_location' => 'tulare_plant']);
    $tulareTractor = preTripAsset('TULARE-TRACTOR', 'tractor', 'tulare_plant');
    $tulareTrailer = preTripAsset('TULARE-TRAILER', 'trailer', 'tulare_plant');
    $tularePiggyback = preTripAsset('TULARE-PIGGYBACK', 'piggyback_forklift', 'tulare_plant');
    $colmaTractor = preTripAsset('COLMA-ONLY-TRACTOR', 'tractor');

    $this->actingAs($user);

    Livewire::test(Schedule::class)
        ->callAction('completeTripPreTripInspection', [
            'truck_asset_id' => $colmaTractor->getKey(),
            'trailer_asset_id' => $tulareTrailer->getKey(),
            'piggyback_asset_id' => $tularePiggyback->getKey(),
            'responses' => preTripPassingResponses($configuration),
            'certification' => true,
        ], ['trip' => $trip->getKey()])
        ->assertHasActionErrors(['truck_asset_id']);

    Livewire::test(Schedule::class)
        ->callAction('completeTripPreTripInspection', [
            'truck_asset_id' => $tulareTractor->getKey(),
            'trailer_asset_id' => $tulareTrailer->getKey(),
            'piggyback_asset_id' => $tularePiggyback->getKey(),
            'responses' => preTripPassingResponses($configuration),
            'certification' => true,
        ], ['trip' => $trip->getKey()])
        ->assertHasNoActionErrors();

    expect(TripPreTripInspection::query()->where('trip_id', $trip->getKey())->sole()->truck_asset_id)
        ->toBe($tulareTractor->getKey());
});

it('treats Colma local deliveries as Colma equipment assignments', function (): void {
    [$user, $driver] = preTripUser('Local Boom Driver');
    $configuration = preTripConfiguration();
    $trip = preTripScheduledTrip($driver, $configuration);
    $trip->orders()->update(['plant_location' => 'colma_locals']);
    $tractor = preTripAsset('LOCAL-COLMA-TRACTOR', 'tractor');
    $trailer = preTripAsset('LOCAL-COLMA-TRAILER', 'trailer');
    $piggyback = preTripAsset('LOCAL-COLMA-PIGGYBACK', 'piggyback_forklift');

    $this->actingAs($user);

    Livewire::test(Schedule::class)
        ->callAction('completeTripPreTripInspection', [
            'truck_asset_id' => $tractor->getKey(),
            'trailer_asset_id' => $trailer->getKey(),
            'piggyback_asset_id' => $piggyback->getKey(),
            'responses' => preTripPassingResponses($configuration),
            'certification' => true,
        ], ['trip' => $trip->getKey()])
        ->assertHasNoActionErrors();
});

it('lets a driver confirm the same daily inspection for another trip using the exact equipment', function (): void {
    [$user, $driver] = preTripUser('Two Trip Driver');
    $configuration = preTripConfiguration();
    $firstTrip = preTripScheduledTrip($driver, $configuration);
    $secondTrip = preTripScheduledTrip($driver, $configuration);
    $tractor = preTripAsset('REUSE-TRACTOR', 'tractor');
    $trailer = preTripAsset('REUSE-TRAILER', 'trailer');
    $piggyback = preTripAsset('REUSE-PIGGYBACK', 'piggyback_forklift');

    $this->actingAs($user);

    Livewire::test(Schedule::class)->callAction('completeTripPreTripInspection', [
        'truck_asset_id' => $tractor->getKey(),
        'trailer_asset_id' => $trailer->getKey(),
        'piggyback_asset_id' => $piggyback->getKey(),
        'responses' => preTripPassingResponses($configuration),
        'certification' => true,
    ], ['trip' => $firstTrip->getKey()])->assertHasNoActionErrors();

    $source = $firstTrip->refresh()->currentPreTripInspection();

    Livewire::test(Schedule::class)
        ->assertSee('Confirm today’s inspection')
        ->callAction('reuseSameDayTripPreTripInspection', [
            'same_equipment_confirmation' => true,
        ], [
            'trip' => $secondTrip->getKey(),
            'inspection' => $source->getKey(),
        ])
        ->assertHasNoActionErrors();

    $reused = $secondTrip->refresh()->currentPreTripInspection();

    expect($reused)->not->toBeNull()
        ->and($reused->getKey())->not->toBe($source->getKey())
        ->and($reused->truck_asset_id)->toBe($source->truck_asset_id)
        ->and(data_get($reused->responses, 'same_day_inspection_confirmed'))->toBe(TripPreTripChecklist::RESPONSE_OK);

    Livewire::test(Schedule::class)->callAction('completeTripDailyVehicleReport', [
        'truck_asset_id' => $tractor->getKey(),
        'trailer_asset_id' => $trailer->getKey(),
        'piggyback_asset_id' => $piggyback->getKey(),
        'daily_responses' => dailyPassingResponses(),
        'certification' => true,
    ], ['trip' => $firstTrip->getKey()])->assertHasNoActionErrors();

    expect($secondTrip->refresh()->currentDailyVehicleReport()?->getKey())
        ->toBe($firstTrip->refresh()->currentDailyVehicleReport()?->getKey());
});

it('records defects without certifying the vehicle safe and notifies dispatch managers', function (): void {
    NotificationFacade::fake();
    [$user, $driver] = preTripUser('Defect Driver');
    [$manager] = preTripUser('Operations Manager', 'manager');
    $manager->givePermissionTo(Permission::findOrCreate('manage delivery trip dispatch', 'web'));
    $configuration = preTripConfiguration();
    $trip = preTripScheduledTrip($driver, $configuration);
    $tractor = preTripAsset('DEFECT-TRACTOR', 'tractor');
    $trailer = preTripAsset('DEFECT-TRAILER', 'trailer');
    $piggyback = preTripAsset('DEFECT-PIGGYBACK', 'piggyback_forklift');
    $responses = preTripPassingResponses($configuration);
    $responses['trailer_brakes_running_gear'] = TripPreTripChecklist::RESPONSE_DEFECT;

    $this->actingAs($user);

    Livewire::test(Schedule::class)
        ->callAction('completeTripPreTripInspection', [
            'truck_asset_id' => $tractor->getKey(),
            'trailer_asset_id' => $trailer->getKey(),
            'piggyback_asset_id' => $piggyback->getKey(),
            'responses' => $responses,
            'defect_notes' => 'Trailer brakes did not hold during the test.',
            'operating_concern' => TripPreTripInspectionDefect::DRIVER_ASSESSMENT_STOP,
            'certification' => true,
        ], ['trip' => $trip->getKey()])
        ->assertHasNoActionErrors();

    $inspection = TripPreTripInspection::query()
        ->where('trip_id', $trip->getKey())
        ->sole();

    expect($inspection->safe_to_operate)->toBeFalse()
        ->and($inspection->status)->toBe(TripPreTripInspection::STATUS_DEFECT_REPORTED)
        ->and($inspection->defects)->toHaveKey('trailer_brakes_running_gear')
        ->and($inspection->inspectionDefects)->toHaveCount(1)
        ->and($inspection->inspectionDefects->first()->maintenance_asset_id)->toBe($trailer->getKey())
        ->and($inspection->inspectionDefects->first()->status)->toBe(TripPreTripInspectionDefect::STATUS_OPEN)
        ->and($trailer->refresh()->status)->toBe('out_of_service');

    $request = MaintenanceRequest::query()->where('asset_id', $trailer->getKey())->sole();

    expect($request->priority)->toBe('urgent')
        ->and($request->safety_related)->toBeTrue()
        ->and($request->description)->toContain('Trailer brakes did not hold');

    NotificationFacade::assertSentTo($manager, TripPreTripDefectReported::class);
});

it('records a short end of day report and routes its defects into maintenance', function (): void {
    NotificationFacade::fake();
    [$user, $driver] = preTripUser('Daily Report Driver');
    $configuration = preTripConfiguration();
    $trip = preTripScheduledTrip($driver, $configuration);
    $tractor = preTripAsset('DAILY-TRACTOR', 'tractor');
    $trailer = preTripAsset('DAILY-TRAILER', 'trailer');
    $piggyback = preTripAsset('DAILY-PIGGYBACK', 'piggyback_forklift');

    $this->actingAs($user);

    $dailyResponses = dailyPassingResponses();
    $dailyResponses['lights_visibility_emergency'] = TripPreTripChecklist::RESPONSE_DEFECT;

    Livewire::test(Schedule::class)
        ->callAction('completeTripPreTripInspection', [
            'truck_asset_id' => $tractor->getKey(),
            'trailer_asset_id' => $trailer->getKey(),
            'piggyback_asset_id' => $piggyback->getKey(),
            'responses' => preTripPassingResponses($configuration),
            'certification' => true,
        ], ['trip' => $trip->getKey()])
        ->assertHasNoActionErrors();

    Livewire::test(Schedule::class)
        ->assertSee('End-of-day vehicle report')
        ->callAction('completeTripDailyVehicleReport', [
            'truck_asset_id' => $tractor->getKey(),
            'trailer_asset_id' => $trailer->getKey(),
            'piggyback_asset_id' => $piggyback->getKey(),
            'daily_responses' => $dailyResponses,
            'daily_defects' => [[
                'asset_role' => 'truck',
                'component' => 'Left headlamp',
                'description' => 'Left low-beam headlamp stopped working during the trip.',
            ]],
            'operating_concern' => TripPreTripInspectionDefect::DRIVER_ASSESSMENT_REVIEW,
            'certification' => true,
        ], ['trip' => $trip->getKey()])
        ->assertHasNoActionErrors();

    $report = TripPreTripInspection::query()
        ->where('trip_id', $trip->getKey())
        ->where('report_type', TripPreTripInspection::TYPE_DAILY_REPORT)
        ->sole();

    expect($report->safe_to_operate)->toBeFalse()
        ->and($report->inspectionDefects)->toHaveCount(1)
        ->and($report->inspectionDefects->first()->component_label)->toBe('Left headlamp')
        ->and($report->checklist_version)->toBe(TripDailyVehicleReportChecklist::VERSION)
        ->and($tractor->refresh()->status)->toBe('restricted')
        ->and(MaintenanceRequest::query()->where('asset_id', $tractor->getKey())->exists())->toBeTrue();
});

it('links an inspection defect to its work order and clears it only after verification', function (): void {
    [$driverUser, $driver] = preTripUser('Repair Workflow Driver');
    [$manager] = preTripUser('Maintenance Verifier', 'manager');
    $configuration = preTripConfiguration();
    $trip = preTripScheduledTrip($driver, $configuration);
    $tractor = preTripAsset('REPAIR-TRACTOR', 'tractor');
    $trailer = preTripAsset('REPAIR-TRAILER', 'trailer');
    $piggyback = preTripAsset('REPAIR-PIGGYBACK', 'piggyback_forklift');
    $responses = preTripPassingResponses($configuration);
    $responses['truck_lights_visibility'] = TripPreTripChecklist::RESPONSE_DEFECT;

    $this->actingAs($driverUser);

    Livewire::test(Schedule::class)->callAction('completeTripPreTripInspection', [
        'truck_asset_id' => $tractor->getKey(),
        'trailer_asset_id' => $trailer->getKey(),
        'piggyback_asset_id' => $piggyback->getKey(),
        'responses' => $responses,
        'defect_notes' => 'Right turn signal is not working.',
        'operating_concern' => TripPreTripInspectionDefect::DRIVER_ASSESSMENT_STOP,
        'certification' => true,
    ], ['trip' => $trip->getKey()])->assertHasNoActionErrors();

    $defect = TripPreTripInspectionDefect::query()->where('maintenance_asset_id', $tractor->getKey())->sole();
    $request = $defect->maintenanceRequest;
    $workOrder = app(MaintenanceRequestConverter::class)->convert($request, $manager);
    $workOrder->update([
        'status' => 'pending_verification',
        'work_performed' => 'Replaced the failed signal bulb and tested the circuit.',
    ]);

    expect($defect->refresh()->maintenance_work_order_id)->toBe($workOrder->getKey())
        ->and($tractor->refresh()->status)->toBe('out_of_service');

    $workOrder->verify($manager);

    expect($defect->refresh()->status)->toBe(TripPreTripInspectionDefect::STATUS_CORRECTED)
        ->and($defect->resolved_by_user_id)->toBe($manager->getKey())
        ->and($defect->resolution_certification)->toContain($workOrder->number)
        ->and($tractor->refresh()->status)->toBe('operational');
});

it('keeps an ordinary driver observation in manager review instead of automatically taking the asset out of service', function (): void {
    [$driverUser, $driver] = preTripUser('Observation Driver');
    [$manager] = preTripUser('Observation Reviewer', 'manager');
    $manager->givePermissionTo(Permission::findOrCreate('manage delivery trip dispatch', 'web'));
    $configuration = preTripConfiguration();
    $trip = preTripScheduledTrip($driver, $configuration);
    $tractor = preTripAsset('REVIEW-TRACTOR', 'tractor');
    $trailer = preTripAsset('REVIEW-TRAILER', 'trailer');
    $piggyback = preTripAsset('REVIEW-PIGGYBACK', 'piggyback_forklift');
    $responses = preTripPassingResponses($configuration);
    $responses['truck_lights_visibility'] = TripPreTripChecklist::RESPONSE_DEFECT;

    $this->actingAs($driverUser);

    Livewire::test(Schedule::class)->callAction('completeTripPreTripInspection', [
        'truck_asset_id' => $tractor->getKey(),
        'trailer_asset_id' => $trailer->getKey(),
        'piggyback_asset_id' => $piggyback->getKey(),
        'responses' => $responses,
        'defect_notes' => 'One marker light looks dim but is still illuminated.',
        'operating_concern' => TripPreTripInspectionDefect::DRIVER_ASSESSMENT_REVIEW,
        'certification' => true,
    ], ['trip' => $trip->getKey()])->assertHasNoActionErrors();

    $defect = TripPreTripInspectionDefect::query()->where('maintenance_asset_id', $tractor->getKey())->sole();

    expect($defect->driver_assessment)->toBe(TripPreTripInspectionDefect::DRIVER_ASSESSMENT_REVIEW)
        ->and($tractor->refresh()->status)->toBe('restricted')
        ->and($defect->maintenanceRequest->priority)->toBe('high');

    $this->actingAs($manager);

    Livewire::test(Schedule::class)
        ->mountAction('viewTripPreTripInspection', ['inspection' => $defect->inspection_id])
        ->assertActionMounted('viewTripPreTripInspection')
        ->mountAction('reviewTripInspectionIssue', ['issue' => $defect->getKey()])
        ->assertActionMounted('reviewTripInspectionIssue');

    Livewire::test(Schedule::class)
        ->mountAction('viewTripVehicleInspectionHistory', ['trip' => $trip->getKey()])
        ->assertActionMounted('viewTripVehicleInspectionHistory')
        ->mountAction('reviewTripInspectionIssue', ['issue' => $defect->getKey()])
        ->assertActionMounted('reviewTripInspectionIssue');

    Livewire::test(Schedule::class)->callAction('reviewTripInspectionIssue', [
        'operating_decision' => TripPreTripInspectionDefect::OPERATING_DECISION_MAY_OPERATE,
        'review_notes' => 'Marker light was inspected in daylight and is functioning normally. No repair is required before dispatch.',
    ], ['issue' => $defect->getKey()])->assertHasNoActionErrors();

    expect($defect->refresh()->status)->toBe(TripPreTripInspectionDefect::STATUS_CORRECTION_NOT_REQUIRED)
        ->and($defect->operating_decision)->toBe(TripPreTripInspectionDefect::OPERATING_DECISION_MAY_OPERATE)
        ->and($defect->reviewed_by_user_id)->toBe($manager->getKey())
        ->and($tractor->refresh()->status)->toBe('operational');
});
