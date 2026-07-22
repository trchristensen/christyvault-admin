<?php

use App\Models\LoadingProfile;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\RackType;
use App\Models\Trip;
use App\Models\TripStop;
use App\Models\VehicleConfiguration;
use App\Services\LoadPlanning\LoadDemandService;
use App\Services\LoadPlanning\RackDiagramService;
use App\Services\LoadPlanning\TripLoadPlanService;
use Illuminate\Support\Collection;

function fillPlanProfile(
    string $code,
    RackType $rackType,
    int $fullLoadUnits,
    string $placement = LoadingProfile::PLACEMENT_ONE_PER_LEVEL,
    string $level = LoadingProfile::LEVEL_ANY,
): LoadingProfile {
    $profile = new LoadingProfile([
        'code' => $code,
        'name' => $code,
        'handling_method' => LoadingProfile::HANDLING_INDIVIDUAL,
        'rack_requirement' => LoadingProfile::RACK_STANDARD,
        'required_rack_level' => $level,
        'required_rack_type_id' => $rackType->getKey(),
        'placement_strategy' => $placement,
        'units_per_rack_position' => 1,
        'full_load_units' => $fullLoadUnits,
    ]);
    $profile->setRelation('requiredRackType', $rackType);
    $profile->setRelation('allowedRackTypes', new Collection([$rackType]));

    return $profile;
}

function fillPlanProduct(string $sku, float $weight, LoadingProfile $profile): Product
{
    $product = new Product(['sku' => $sku, 'name' => $sku, 'weight_lbs' => $weight]);
    $product->id = abs(crc32($sku));
    $product->setRelation('loadingProfile', $profile);

    return $product;
}

function fillPlanLine(
    int $id,
    Product $product,
    ?int $quantity,
    bool $fill = false,
    ?int $priority = null,
    ?int $planned = null,
): OrderProduct {
    $line = new OrderProduct([
        'product_id' => $product->getKey(),
        'quantity' => $quantity,
        'fill_load' => $fill ? 1 : 0,
        'fill_priority' => $priority,
        'planned_fill_quantity' => $planned,
        'fill_plan_source' => $planned === null ? null : 'manual',
    ]);
    $line->id = $id;
    $line->setRelation('product', $product);

    return $line;
}

function fillPlanTrip(array $orders, int $rackSpots = 8, float $maxWeight = 38500): Trip
{
    $stops = collect($orders)->values()->map(function (Order $order, int $index): TripStop {
        $stop = new TripStop([
            'order_id' => $order->getKey(),
            'sequence' => $index + 1,
        ]);
        $stop->setRelation('order', $order);

        return $stop;
    });
    $vehicle = new VehicleConfiguration([
        'name' => 'Rack trailer',
        'configuration_type' => VehicleConfiguration::TYPE_RACK_TRAILER,
        'rack_spot_count' => $rackSpots,
        'max_product_weight_lbs' => $maxWeight,
        'piggyback_forklift_onboard' => true,
    ]);
    $trip = new Trip(['trip_number' => 'TRIP-FILL']);
    $trip->setRelation('stops', $stops);
    $trip->setRelation('orders', new Collection($orders));
    $trip->setRelation('vehicleConfiguration', $vehicle);

    return $trip;
}

function fillPlanOrder(int $id, string $number, array $lines): Order
{
    $order = new Order(['order_number' => $number, 'stop_number' => $id]);
    $order->id = $id;
    $order->setRelation('orderProducts', new Collection($lines));
    $order->setRelation('location', null);

    return $order;
}

function fillPlanner(): TripLoadPlanService
{
    return new TripLoadPlanService(new LoadDemandService, new RackDiagramService);
}

it('calculates the largest safe fill quantity after fixed products', function (): void {
    $twoHigh = new RackType(['code' => 'standard_2_high', 'level_count' => 2]);
    $twoHigh->id = 1;
    $threeHigh = new RackType(['code' => 'standard_3_high', 'level_count' => 3]);
    $threeHigh->id = 2;
    $g6Profile = fillPlanProfile('standard_three_high_box', $threeHigh, 22);
    $g6Profile->setRelation('allowedRackTypes', new Collection([$twoHigh, $threeHigh]));
    $g4Profile = fillPlanProfile(
        'standard_two_high_split_double',
        $twoHigh,
        12,
        LoadingProfile::PLACEMENT_FULL_TOP_SPLIT_BOTTOM_PAIR,
    );
    $triuneProfile = fillPlanProfile(
        'regular_burial_vault_triune',
        $twoHigh,
        15,
        level: LoadingProfile::LEVEL_BOTTOM,
    );
    $order = fillPlanOrder(1, 'ORD-02169', [
        fillPlanLine(1, fillPlanProduct('G3086-6', 1750, $g6Profile), 10),
        fillPlanLine(2, fillPlanProduct('G3086-4', 3455, $g4Profile), null, true),
        fillPlanLine(3, fillPlanProduct('W3086-SST', 2690, $triuneProfile), 1),
    ]);

    $plan = fillPlanner()->forTrip(fillPlanTrip([$order]));
    $allocation = $plan['fill_allocations'][0];

    expect($allocation)->toMatchArray([
        'sku' => 'G3086-4',
        'planned_quantity' => 5,
        'resolved' => true,
        'source' => 'automatic',
    ])->and($plan['demand']->summary['known_weight_lbs'])->toBe(37465.0)
        ->and($plan['demand']->summary['remaining_product_weight_lbs'])->toBe(1035.0)
        ->and($plan['diagram']['unplaced'])->toBeEmpty();
});

it('calculates fill quantities around weighted loose accessories without reserving rack space', function (): void {
    $threeHigh = new RackType(['code' => 'standard_3_high', 'level_count' => 3]);
    $threeHigh->id = 2;
    $g6Profile = fillPlanProfile('standard_three_high_box', $threeHigh, 22);
    $looseProfile = new LoadingProfile([
        'code' => 'loose_accessory',
        'handling_method' => LoadingProfile::HANDLING_LOOSE,
        'rack_requirement' => LoadingProfile::RACK_NONE,
    ]);
    $order = fillPlanOrder(1, 'ORD-LOOSE-FILL', [
        fillPlanLine(1, fillPlanProduct('G3086-6', 1750, $g6Profile), null, true),
        fillPlanLine(2, fillPlanProduct('SEA-02', 2, $looseProfile), 20),
    ]);

    $plan = fillPlanner()->forTrip(fillPlanTrip([$order]));

    expect($plan['fill_allocations'][0])->toMatchArray([
        'sku' => 'G3086-6',
        'planned_quantity' => 21,
        'resolved' => true,
        'source' => 'automatic',
    ])->and($plan['demand']->summary['known_weight_lbs'])->toBe(36790.0)
        ->and($plan['diagram']['used_rack_spots'])->toBe(7)
        ->and($plan['diagram']['non_rack_cargo'])->toHaveCount(1)
        ->and($plan['diagram']['unplaced'])->toBeEmpty();
});

it('allocates multiple automatic fills by adjustable priority', function (): void {
    $threeHigh = new RackType(['code' => 'standard_3_high', 'level_count' => 3]);
    $threeHigh->id = 2;
    $profile = fillPlanProfile('standard_three_high_box', $threeHigh, 22);
    $product = fillPlanProduct('G3086-6', 1000, $profile);
    $first = fillPlanOrder(1, 'ORD-FIRST', [
        fillPlanLine(1, $product, null, true, priority: 2),
    ]);
    $second = fillPlanOrder(2, 'ORD-SECOND', [
        fillPlanLine(2, $product, null, true, priority: 1),
    ]);

    $plan = fillPlanner()->forTrip(fillPlanTrip([$first, $second]));
    $allocations = collect($plan['fill_allocations'])->keyBy('order_number');

    expect($allocations['ORD-SECOND']['planned_quantity'])->toBe(22)
        ->and($allocations['ORD-FIRST']['planned_quantity'])->toBe(0)
        ->and($plan['demand']->summary['product_units'])->toBe(22);
});

it('gives blank Fill priorities to the earlier order first', function (): void {
    $threeHigh = new RackType(['code' => 'standard_3_high', 'level_count' => 3]);
    $threeHigh->id = 2;
    $profile = fillPlanProfile('standard_three_high_box', $threeHigh, 22);
    $product = fillPlanProduct('G3086-6', 1000, $profile);
    $later = fillPlanOrder(1, 'ORD-LATER', [
        fillPlanLine(1, $product, null, true),
    ]);
    $later->setRawAttributes([...$later->getAttributes(), 'order_date' => '2026-07-18']);
    $earlier = fillPlanOrder(2, 'ORD-EARLIER', [
        fillPlanLine(2, $product, null, true),
    ]);
    $earlier->setRawAttributes([...$earlier->getAttributes(), 'order_date' => '2026-07-17']);

    $plan = fillPlanner()->forTrip(fillPlanTrip([$later, $earlier]));
    $allocations = collect($plan['fill_allocations'])->keyBy('order_number');

    expect($allocations['ORD-EARLIER']['planned_quantity'])->toBe(22)
        ->and($allocations['ORD-LATER']['planned_quantity'])->toBe(0);
});

it('honors a manual fill allocation before assigning automatic residual capacity', function (): void {
    $threeHigh = new RackType(['code' => 'standard_3_high', 'level_count' => 3]);
    $threeHigh->id = 2;
    $profile = fillPlanProfile('standard_three_high_box', $threeHigh, 22);
    $product = fillPlanProduct('G3086-6', 1000, $profile);
    $first = fillPlanOrder(1, 'ORD-MANUAL', [
        fillPlanLine(1, $product, null, true, planned: 10),
    ]);
    $second = fillPlanOrder(2, 'ORD-AUTO', [
        fillPlanLine(2, $product, null, true),
    ]);

    $plan = fillPlanner()->forTrip(fillPlanTrip([$first, $second]));
    $allocations = collect($plan['fill_allocations'])->keyBy('order_number');

    expect($allocations['ORD-MANUAL'])->toMatchArray([
        'planned_quantity' => 10,
        'source' => 'manual',
    ])->and($allocations['ORD-AUTO'])->toMatchArray([
        'planned_quantity' => 12,
        'source' => 'automatic',
    ]);
});
