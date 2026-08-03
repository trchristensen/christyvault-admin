<?php

use App\Models\Location;
use App\Models\MaintenanceAsset;
use App\Models\MaintenanceMeterReading;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceWorkOrder;
use App\Models\User;
use App\Notifications\MaintenanceRequestSubmitted;
use App\Services\Maintenance\MaintenancePlanScheduler;
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
