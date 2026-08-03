<?php

use App\Models\Location;
use App\Models\MaintenanceAsset;
use App\Models\MaintenanceFleetPlan;
use App\Models\MaintenanceMeterReading;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceWorkOrder;
use App\Models\User;
use App\Notifications\MaintenanceRequestSubmitted;
use App\Services\Maintenance\MaintenancePlanScheduler;
use App\Services\Maintenance\MaintenanceFleetPlanScheduler;
use App\Services\Maintenance\MaintenanceRequestConverter;
use Database\Seeders\MaintenanceAssetImportSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

it('generates a preventive work order when a meter threshold is reached', function (): void {
    $asset = MaintenanceAsset::create([
        'asset_tag' => 'TEST-METER-'.uniqid(),
        'name' => 'Test forklift',
        'category' => 'forklift',
        'meter_type' => 'hours',
        'current_meter' => 240,
    ]);

    $plan = MaintenancePlan::create([
        'asset_id' => $asset->id,
        'name' => '250-hour service',
        'trigger_type' => 'meter',
        'meter_interval' => 250,
        'next_due_meter' => 250,
        'checklist' => [['task' => 'Change engine oil'], ['task' => 'Inspect brakes']],
        'active' => true,
    ]);

    MaintenanceMeterReading::create([
        'asset_id' => $asset->id,
        'reading' => 251,
        'recorded_at' => now(),
        'source' => 'test',
    ]);

    $workOrder = MaintenanceWorkOrder::where('plan_id', $plan->id)->first();

    expect($asset->fresh()->current_meter)->toBe('251.00')
        ->and($workOrder)->not->toBeNull()
        ->and($workOrder->type)->toBe('preventive')
        ->and($workOrder->number)->toMatch('/^MWO-\d{6}$/')
        ->and($workOrder->checklist)->toHaveCount(2)
        ->and($plan->fresh()->next_due_meter)->toBe('500.00');
});

it('does not create duplicate open work orders for a preventive plan', function (): void {
    $asset = MaintenanceAsset::create([
        'asset_tag' => 'TEST-PM-'.uniqid(),
        'name' => 'Test crane',
        'category' => 'gantry_crane',
    ]);
    $plan = MaintenancePlan::create([
        'asset_id' => $asset->id,
        'name' => 'Monthly crane inspection',
        'trigger_type' => 'calendar',
        'interval_value' => 1,
        'interval_unit' => 'months',
        'next_due_date' => today(),
        'lead_days' => 7,
        'active' => true,
    ]);

    $scheduler = app(MaintenancePlanScheduler::class);

    expect($scheduler->generateDue($asset->id))->toBe(1)
        ->and($scheduler->generateDue($asset->id))->toBe(0)
        ->and(MaintenanceWorkOrder::where('plan_id', $plan->id)->count())->toBe(1);
});

it('converts a request into one traceable work order', function (): void {
    $user = User::factory()->create();
    $asset = MaintenanceAsset::create(['asset_tag' => 'TEST-REQ-'.uniqid(), 'name' => 'Test truck', 'category' => 'truck']);
    $request = MaintenanceRequest::create([
        'asset_id' => $asset->id,
        'requester_name' => 'Driver',
        'title' => 'Brake warning light',
        'description' => 'Warning light remained on after startup.',
        'priority' => 'urgent',
        'safety_related' => true,
        'submitted_at' => now(),
    ]);

    $converter = app(MaintenanceRequestConverter::class);
    $first = $converter->convert($request, $user, ['assigned_to_user_id' => $user->id]);
    $second = $converter->convert($request->fresh(), $user);

    expect($first->id)->toBe($second->id)
        ->and($first->request_id)->toBe($request->id)
        ->and($first->safety_related)->toBeTrue()
        ->and($request->fresh()->status)->toBe('accepted');
});

it('accepts a mobile QR maintenance request with a photo and meter reading', function (): void {
    Notification::fake();
    Storage::fake('public');
    $manager = User::factory()->create();
    $manager->assignRole(Role::findOrCreate('maintenance-manager', 'web'));

    $asset = MaintenanceAsset::create([
        'asset_tag' => 'TEST-QR-'.uniqid(),
        'name' => 'Test batch plant',
        'category' => 'batch_plant',
        'meter_type' => 'cycles',
        'current_meter' => 100,
    ]);

    $response = $this->post(route('maintenance.assets.request', $asset->qr_token), [
        'requester_name' => 'Plant Operator',
        'title' => 'Mixer making unusual noise',
        'description' => 'A grinding sound starts during the mix cycle.',
        'priority' => 'high',
        'safety_related' => '1',
        'meter_reading' => '105',
        'photos' => [UploadedFile::fake()->image('mixer.jpg')],
    ]);

    $response->assertRedirect(route('maintenance.assets.portal', $asset->qr_token));
    $this->assertDatabaseHas('maintenance_requests', [
        'asset_id' => $asset->id,
        'requester_name' => 'Plant Operator',
        'safety_related' => true,
        'status' => 'new',
    ]);
    expect($asset->fresh()->current_meter)->toBe('105.00');
    Notification::assertSentTo($manager, MaintenanceRequestSubmitted::class);
});

it('renders the asset QR portal and an SVG label', function (): void {
    $asset = MaintenanceAsset::create(['asset_tag' => 'TEST-LABEL-'.uniqid(), 'name' => 'Test compressor', 'category' => 'compressor']);

    $this->get(route('maintenance.assets.portal', $asset->qr_token))
        ->assertOk()
        ->assertSee($asset->asset_tag)
        ->assertSee('Report a problem');

    $this->get(route('maintenance.assets.qr', $asset->qr_token))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml');
});

it('renders every maintenance panel workflow for an authorized manager', function (): void {
    $manager = User::factory()->create();
    $manager->assignRole(Role::findOrCreate('maintenance-manager', 'web'));
    $asset = MaintenanceAsset::create(['asset_tag' => 'TEST-UI-'.uniqid(), 'name' => 'UI test asset', 'category' => 'other']);
    $plant = Location::create([
        'name' => 'UI fleet plant '.uniqid(),
        'address_line1' => '1 UI Way',
        'city' => 'Test City',
        'state' => 'CA',
        'postal_code' => '94000',
        'location_type' => 'christy_vault',
    ]);
    $fleetPlan = MaintenanceFleetPlan::create([
        'location_id' => $plant->id,
        'name' => 'UI fleet plan',
        'manufacturer' => 'Hyster',
        'asset_category' => 'forklift',
        'meter_type' => 'hours',
        'meter_interval' => 250,
    ]);

    $this->actingAs($manager);

    foreach ([
        '/maintenance',
        '/maintenance/assets',
        '/maintenance/assets/create',
        "/maintenance/assets/{$asset->id}/edit",
        '/maintenance/requests',
        '/maintenance/requests/create',
        '/maintenance/work-orders',
        '/maintenance/work-orders/create',
        '/maintenance/preventive-maintenance',
        '/maintenance/preventive-maintenance/create',
        '/maintenance/fleet-preventive-maintenance',
        '/maintenance/fleet-preventive-maintenance/create',
        "/maintenance/fleet-preventive-maintenance/{$fleetPlan->id}/edit",
        '/maintenance/meter-readings',
        '/maintenance/meter-readings/create',
    ] as $path) {
        $this->get($path)->assertOk();
    }
});

it('keeps customer locations out of maintenance plant choices', function (): void {
    $plant = Location::create([
        'name' => 'Test internal plant '.uniqid(),
        'address_line1' => '1 Plant Way',
        'city' => 'Test City',
        'state' => 'CA',
        'postal_code' => '94000',
        'location_type' => 'christy_vault',
    ]);
    $customer = Location::create([
        'name' => 'Test customer site '.uniqid(),
        'address_line1' => '2 Customer Road',
        'city' => 'Test City',
        'state' => 'CA',
        'postal_code' => '94000',
        'location_type' => 'cemetery',
    ]);

    $plantIds = Location::query()->christyVault()->pluck('id');

    expect($plantIds)->toContain($plant->id)
        ->and($plantIds)->not->toContain($customer->id);
});

it('imports the equipment register idempotently with plant and registration data', function (): void {
    $seeder = app(MaintenanceAssetImportSeeder::class);
    $seeder->run();
    $seeder->run();

    $assetTags = ['8', '9', '11', '12', '18', '16', '22', '26', '29', '53', '61', '65', '74', '76', '84', '86', '41', '71', '43'];

    expect(MaintenanceAsset::whereIn('asset_tag', $assetTags)->count())->toBe(19)
        ->and(MaintenanceAsset::where('asset_tag', '71')->value('name'))->toBe('2018 Princeton PB55.3 Piggyback')
        ->and(MaintenanceAsset::where('asset_tag', '26')->value('license_plate'))->toBe('60923M3')
        ->and(MaintenanceAsset::where('asset_tag', '43')->firstOrFail()->location->name)->toBe('Christy Vault - Tulare');
});

it('dynamically groups matching fleet assets and honors low-use exclusions', function (): void {
    $location = Location::create([
        'name' => 'Fleet test plant '.uniqid(),
        'address_line1' => '1 Fleet Way',
        'city' => 'Test City',
        'state' => 'CA',
        'postal_code' => '94000',
        'location_type' => 'christy_vault',
    ]);
    $included = MaintenanceAsset::create([
        'asset_tag' => 'FLEET-A-'.uniqid(),
        'name' => 'Included Hyster',
        'category' => 'forklift',
        'manufacturer' => 'Hyster',
        'location_id' => $location->id,
        'meter_type' => 'hours',
        'current_meter' => 100,
    ]);
    $lowUse = MaintenanceAsset::create([
        'asset_tag' => 'FLEET-B-'.uniqid(),
        'name' => 'Low-use Hyster',
        'category' => 'forklift',
        'manufacturer' => 'HYSTER',
        'location_id' => $location->id,
        'meter_type' => 'hours',
        'current_meter' => 200,
    ]);
    MaintenanceAsset::create([
        'asset_tag' => 'FLEET-C-'.uniqid(),
        'name' => 'Different manufacturer',
        'category' => 'forklift',
        'manufacturer' => 'Toyota',
        'location_id' => $location->id,
        'meter_type' => 'hours',
        'current_meter' => 300,
    ]);
    $plan = MaintenanceFleetPlan::create([
        'location_id' => $location->id,
        'name' => 'Group Service B',
        'manufacturer' => 'Hyster',
        'asset_category' => 'forklift',
        'meter_type' => 'hours',
        'meter_interval' => 250,
        'active' => true,
    ]);

    $scheduler = app(MaintenanceFleetPlanScheduler::class);
    $scheduler->syncMatchingAssets($plan);
    $plan->members()->where('asset_id', $lowUse->id)->update(['included' => false]);

    $newMatch = MaintenanceAsset::create([
        'asset_tag' => 'FLEET-D-'.uniqid(),
        'name' => 'New Hyster',
        'category' => 'forklift',
        'manufacturer' => 'Hyster',
        'location_id' => $location->id,
        'meter_type' => 'hours',
        'current_meter' => 400,
    ]);
    $scheduler->syncMatchingAssets($plan);

    expect($plan->members()->where('matches_filter', true)->count())->toBe(3)
        ->and($plan->members()->where('asset_id', $included->id)->value('included'))->toBeTrue()
        ->and($plan->members()->where('asset_id', $lowUse->id)->value('included'))->toBeFalse()
        ->and($plan->members()->where('asset_id', $newMatch->id)->value('included'))->toBeTrue();
});

it('creates one traceable work order per included asset when any fleet unit reaches its threshold', function (): void {
    $location = Location::create([
        'name' => 'Fleet trigger plant '.uniqid(),
        'address_line1' => '2 Fleet Way',
        'city' => 'Test City',
        'state' => 'CA',
        'postal_code' => '94000',
        'location_type' => 'christy_vault',
    ]);
    $assets = collect([1000, 500, 50])->map(fn (int $meter, int $index) => MaintenanceAsset::create([
        'asset_tag' => 'TRIGGER-'.$index.'-'.uniqid(),
        'name' => "Hyster {$index}",
        'category' => 'forklift',
        'manufacturer' => 'Hyster',
        'location_id' => $location->id,
        'meter_type' => 'hours',
        'current_meter' => $meter,
    ]));
    $plan = MaintenanceFleetPlan::create([
        'location_id' => $location->id,
        'name' => 'Papé Service B',
        'manufacturer' => 'Hyster',
        'asset_category' => 'forklift',
        'meter_type' => 'hours',
        'meter_interval' => 250,
        'service_provider' => 'Papé',
        'priority' => 'normal',
        'active' => true,
        'checklist' => [['task' => 'Record service meter']],
    ]);
    $scheduler = app(MaintenanceFleetPlanScheduler::class);
    $scheduler->syncMatchingAssets($plan);
    $plan->members()->where('asset_id', $assets[0]->id)->update(['baseline_meter' => 750, 'next_due_meter' => 1000]);
    $plan->members()->where('asset_id', $assets[1]->id)->update(['baseline_meter' => 300, 'next_due_meter' => 550]);
    $plan->members()->where('asset_id', $assets[2]->id)->update(['included' => false]);

    $generated = $scheduler->generateDue($assets[0]->id);
    $run = $plan->serviceRuns()->firstOrFail();

    expect($generated)->toBe(2)
        ->and($run->triggered_by_asset_id)->toBe($assets[0]->id)
        ->and($run->workOrders)->toHaveCount(2)
        ->and($run->workOrders->pluck('asset_id')->all())->toEqualCanonicalizing([$assets[0]->id, $assets[1]->id])
        ->and($run->workOrders->first()->description)->toContain('Papé')
        ->and($scheduler->generateDue())->toBe(0)
        ->and($plan->serviceRuns()->count())->toBe(1);
});

it('resets each fleet baseline when its grouped work order is verified', function (): void {
    $manager = User::factory()->create();
    $location = Location::create([
        'name' => 'Fleet completion plant '.uniqid(),
        'address_line1' => '3 Fleet Way',
        'city' => 'Test City',
        'state' => 'CA',
        'postal_code' => '94000',
        'location_type' => 'christy_vault',
    ]);
    $asset = MaintenanceAsset::create([
        'asset_tag' => 'COMPLETE-'.uniqid(),
        'name' => 'Completion Hyster',
        'category' => 'forklift',
        'manufacturer' => 'Hyster',
        'location_id' => $location->id,
        'meter_type' => 'hours',
        'current_meter' => 1250,
    ]);
    $plan = MaintenanceFleetPlan::create([
        'location_id' => $location->id,
        'name' => 'Completion Service B',
        'manufacturer' => 'Hyster',
        'asset_category' => 'forklift',
        'meter_type' => 'hours',
        'meter_interval' => 250,
        'active' => true,
    ]);
    $scheduler = app(MaintenanceFleetPlanScheduler::class);
    $scheduler->syncMatchingAssets($plan);
    $plan->members()->where('asset_id', $asset->id)->update(['baseline_meter' => 1000, 'next_due_meter' => 1250]);
    $run = $scheduler->generate($plan);
    $asset->update(['current_meter' => 1262]);
    $run->workOrders()->firstOrFail()->verify($manager);
    $member = $plan->members()->where('asset_id', $asset->id)->firstOrFail();

    expect($member->baseline_meter)->toBe('1262.00')
        ->and($member->next_due_meter)->toBe('1512.00')
        ->and($member->last_serviced_at)->not->toBeNull()
        ->and($run->fresh()->status)->toBe('completed')
        ->and($plan->fresh()->last_completed_at)->not->toBeNull();
});
