<?php

use App\Enums\PlantLocation;
use App\Filament\Resources\OrderResource\Pages\DeliveryCalendar;
use App\Models\Location;
use App\Models\Order;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

it('groups unassigned orders by plant and puts requested dates first', function (): void {
    $location = Location::factory()->create();

    $laterColma = Order::factory()->create([
        'location_id' => $location->getKey(),
        'plant_location' => PlantLocation::COLMA_MAIN->value,
        'status' => 'confirmed',
        'assigned_delivery_date' => null,
        'requested_delivery_date' => '2026-08-20',
        'order_date' => '2026-08-01',
    ]);
    $urgentColma = Order::factory()->create([
        'location_id' => $location->getKey(),
        'plant_location' => PlantLocation::COLMA_MAIN->value,
        'status' => 'pending',
        'assigned_delivery_date' => null,
        'requested_delivery_date' => '2026-08-10',
        'order_date' => '2026-08-02',
    ]);
    $local = Order::factory()->create([
        'location_id' => $location->getKey(),
        'plant_location' => PlantLocation::COLMA_LOCALS->value,
        'status' => 'ready_for_delivery',
        'assigned_delivery_date' => null,
        'requested_delivery_date' => '2026-08-12',
    ]);
    $tulare = Order::factory()->create([
        'location_id' => $location->getKey(),
        'plant_location' => PlantLocation::TULARE_PLANT->value,
        'status' => 'in_production',
        'assigned_delivery_date' => null,
        'requested_delivery_date' => '2026-09-01',
    ]);

    $assigned = Order::factory()->create([
        'location_id' => $location->getKey(),
        'plant_location' => PlantLocation::COLMA_MAIN->value,
        'status' => 'confirmed',
        'assigned_delivery_date' => '2026-08-14',
    ]);

    $viewData = (new DeliveryCalendar)->getViewData();
    $groups = $viewData['unassignedOrderGroups']->keyBy('key');
    $unassignedKeys = $viewData['unassignedOrders']->modelKeys();
    $testOrderKeys = [$urgentColma->getKey(), $local->getKey(), $laterColma->getKey(), $tulare->getKey()];

    expect(array_values(array_intersect($unassignedKeys, $testOrderKeys)))->toBe($testOrderKeys)
        ->and($unassignedKeys)->not->toContain($assigned->getKey())
        ->and($groups->keys()->all())->toContain(
            PlantLocation::COLMA_MAIN->value,
            PlantLocation::COLMA_LOCALS->value,
            PlantLocation::TULARE_PLANT->value,
        )->and(array_values(array_intersect(
            $groups[PlantLocation::COLMA_MAIN->value]['orders']->modelKeys(),
            $testOrderKeys,
        )))->toBe([$urgentColma->getKey(), $laterColma->getKey()])
        ->and($groups[PlantLocation::COLMA_LOCALS->value]['orders']->modelKeys())->toContain($local->getKey())
        ->and($groups[PlantLocation::TULARE_PLANT->value]['orders']->modelKeys())->toContain($tulare->getKey());
});
