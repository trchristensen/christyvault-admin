<?php

use App\Models\LoadingProfile;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\RackType;
use App\Models\Trip;
use App\Models\TripStop;
use App\Models\VehicleConfiguration;
use App\Services\LoadPlanning\DraftOrderLoadPreviewService;
use App\Services\LoadPlanning\LoadDemandService;
use App\Services\LoadPlanning\RackDiagramService;
use App\Services\LoadPlanning\TripLoadPlanService;
use App\Services\TripVehicleConfigurationResolver;
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

function fillPlanTrip(
    array $orders,
    int $rackSpots = 8,
    float $maxWeight = 38500,
    int $flatbedPalletCapacity = 0,
): Trip {
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
        'flatbed_pallet_capacity' => $flatbedPalletCapacity,
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

function draftOrderPreviewer(): DraftOrderLoadPreviewService
{
    return new DraftOrderLoadPreviewService(
        fillPlanner(),
        new TripVehicleConfigurationResolver,
    );
}

it('previews a standalone draft without persisting an order or trip', function (): void {
    $threeHigh = new RackType(['code' => 'standard_3_high', 'level_count' => 3]);
    $threeHigh->id = 1;
    $profile = fillPlanProfile('standard_three_high_box', $threeHigh, 22);
    $product = fillPlanProduct('V3086-1', 1288, $profile);
    $draft = fillPlanOrder(-1, 'DRAFT ORDER', [
        fillPlanLine(-10, $product, 3),
    ]);
    $vehicle = new VehicleConfiguration([
        'name' => 'Rack trailer — forklift onboard',
        'configuration_type' => VehicleConfiguration::TYPE_RACK_TRAILER,
        'rack_spot_count' => 8,
        'flatbed_pallet_capacity' => 4,
        'max_product_weight_lbs' => 38500,
    ]);

    $preview = draftOrderPreviewer()->preview($draft, null, $vehicle);

    expect($preview['status'])->toBe('fits')
        ->and($preview['context'])->toMatchArray([
            'trip_number' => null,
            'existing_stops' => 0,
            'draft_stop_sequence' => 1,
        ])
        ->and($preview['draft'])->toMatchArray([
            'line_count' => 1,
            'product_units' => 3,
            'known_weight_lbs' => 3864.0,
        ])
        ->and($preview['weight']['existing'])->toBe(0.0)
        ->and($preview['racks']['used'])->toBe(1)
        ->and($preview['racks']['draft_touched'])->toBe(1)
        ->and($draft->exists)->toBeFalse();
});

it('replaces the edited order inside an existing trip preview instead of counting it twice', function (): void {
    $threeHigh = new RackType(['code' => 'standard_3_high', 'level_count' => 3]);
    $threeHigh->id = 1;
    $profile = fillPlanProfile('standard_three_high_box', $threeHigh, 22);
    $product = fillPlanProduct('V3086-1', 1288, $profile);
    $existingOrder = fillPlanOrder(1, 'ORD-EXISTING', [
        fillPlanLine(1, $product, 1),
    ]);
    $savedVersion = fillPlanOrder(2, 'ORD-EDITING', [
        fillPlanLine(2, $product, 1),
    ]);
    $draftVersion = fillPlanOrder(2, 'ORD-EDITING', [
        fillPlanLine(-2, $product, 2),
    ]);
    $sourceTrip = fillPlanTrip([$existingOrder, $savedVersion], flatbedPalletCapacity: 4);
    $sourceTrip->trip_number = 'TRIP-00049';
    $vehicle = $sourceTrip->vehicleConfiguration;

    $preview = draftOrderPreviewer()->preview($draftVersion, $sourceTrip, $vehicle);

    expect($preview['status'])->toBe('fits')
        ->and($preview['context'])->toMatchArray([
            'trip_number' => 'TRIP-00049',
            'existing_stops' => 1,
            'draft_stop_sequence' => 2,
        ])
        ->and($preview['weight']['known'])->toBe(3864.0)
        ->and($preview['weight']['existing'])->toBe(1288.0)
        ->and($preview['weight']['draft'])->toBe(2576.0)
        ->and($preview['draft']['product_units'])->toBe(2)
        ->and($savedVersion->orderProducts->first()->quantity)->toBe(1);
});

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

it('adds a fourth V1 after preferred-bottom Wilberts take priority over G5 and V1 pairing', function (): void {
    $twoHigh = new RackType(['code' => 'standard_2_high', 'level_count' => 2]);
    $twoHigh->id = 1;
    $threeHigh = new RackType(['code' => 'standard_3_high', 'level_count' => 3]);
    $threeHigh->id = 2;
    $v1Profile = fillPlanProfile('standard_three_high_box', $threeHigh, 22);
    $v1Profile->setRelation('allowedRackTypes', new Collection([$twoHigh, $threeHigh]));
    $gardenProfile = fillPlanProfile(
        'double_garden_crypt',
        $twoHigh,
        12,
        LoadingProfile::PLACEMENT_FULL_TOP_SPLIT_BOTTOM_PAIR,
    );
    $wilbertProfile = fillPlanProfile('regular_burial_vault', $twoHigh, 15);
    $wilbertProfile->preferred_rack_level = LoadingProfile::LEVEL_BOTTOM;

    $stopOne = fillPlanOrder(1, 'ORD-02216', [
        fillPlanLine(1, fillPlanProduct('G3086-5', 2520, $gardenProfile), 3),
        fillPlanLine(2, fillPlanProduct('V3086-1', 1288, $v1Profile), 3),
    ]);
    $stopTwo = fillPlanOrder(2, 'ORD-02217', [
        fillPlanLine(3, fillPlanProduct('W3086-C', 2460, $wilbertProfile), 1),
        fillPlanLine(4, fillPlanProduct('G3086-5', 2520, $gardenProfile), 3),
        fillPlanLine(5, fillPlanProduct('W3086-M', 2190, $wilbertProfile), 3),
        fillPlanLine(6, fillPlanProduct('V3086-1', 1288, $v1Profile), null, true, priority: 1),
    ]);

    $plan = fillPlanner()->forTrip(fillPlanTrip([$stopOne, $stopTwo]));
    $cells = collect($plan['diagram']['racks'])
        ->flatMap(fn (array $rack): array => $rack['cells'])
        ->filter();
    $stopTwoWilberts = $cells
        ->where('stop_sequence', 2)
        ->filter(fn (array $cell): bool => str_starts_with($cell['sku'], 'W3086-'));

    expect($plan['fill_allocations'][0])->toMatchArray([
        'sku' => 'V3086-1',
        'planned_quantity' => 4,
        'resolved' => true,
        'source' => 'automatic',
    ])->and($plan['demand']->summary['known_weight_lbs'])->toBe(33166.0)
        ->and($plan['demand']->summary['remaining_product_weight_lbs'])->toBe(5334.0)
        ->and($plan['diagram']['used_rack_spots'])->toBe(8)
        ->and($plan['diagram']['mixed_stop_racks'])->toBe(0)
        ->and($plan['diagram']['split_products'])->toBe(0)
        ->and($plan['diagram']['preferred_level_violations'])->toBe(0)
        ->and($plan['diagram']['unplaced'])->toBeEmpty()
        ->and($stopTwoWilberts)->toHaveCount(4)
        ->and($stopTwoWilberts->pluck('level')->unique()->values()->all())->toBe([1]);
});

it('moves eligible fixed products to the flatbed to maximize the reviewed V1 fill load', function (): void {
    $twoHigh = new RackType([
        'code' => 'standard_2_high',
        'level_count' => 2,
        'pallet_capable_levels' => 1,
        'pallets_per_capable_level' => 2,
    ]);
    $twoHigh->id = 1;
    $threeHigh = new RackType([
        'code' => 'standard_3_high',
        'level_count' => 3,
        'pallet_capable_levels' => 2,
        'pallets_per_capable_level' => 2,
    ]);
    $threeHigh->id = 2;
    $bothRackTypes = new Collection([$twoHigh, $threeHigh]);

    $v1Profile = fillPlanProfile('standard_three_high_box', $threeHigh, 22);
    $v1Profile->setRelation('allowedRackTypes', $bothRackTypes);
    $linerProfile = fillPlanProfile('ring_liner_three_high', $threeHigh, 22);
    $linerProfile->setRelation('allowedRackTypes', $bothRackTypes);
    $wilbertProfile = fillPlanProfile('regular_burial_vault', $twoHigh, 15);
    $gardenProfile = fillPlanProfile(
        'double_garden_crypt',
        $twoHigh,
        12,
        LoadingProfile::PLACEMENT_FULL_TOP_SPLIT_BOTTOM_PAIR,
    );
    $coverProfile = fillPlanProfile(
        'garden_crypt_cover_6_lower_bays',
        $threeHigh,
        0,
        level: LoadingProfile::LEVEL_LOWER_NOT_TOP,
    );
    $coverProfile->units_per_rack_position = 6;
    $coverProfile->full_load_units = null;
    $coverProfile->setRelation('allowedRackTypes', $bothRackTypes);
    $smallVaultProfile = fillPlanProfile(
        'christy_1637_vault_lower_bays_flatbed',
        $threeHigh,
        0,
        level: LoadingProfile::LEVEL_LOWER_NOT_TOP,
    );
    $smallVaultProfile->units_per_rack_position = 4;
    $smallVaultProfile->flatbed_fallback_units_per_pallet = 2;
    $smallVaultProfile->full_load_units = null;
    $smallVaultProfile->setRelation('allowedRackTypes', $bothRackTypes);
    $smallCoverProfile = new LoadingProfile([
        'code' => 'christy_1637_cover_4_per_pallet',
        'name' => 'Christy cover pallet',
        'handling_method' => LoadingProfile::HANDLING_PALLET,
        'units_per_pallet' => 4,
        'rack_requirement' => LoadingProfile::RACK_STANDARD,
        'required_rack_level' => LoadingProfile::LEVEL_LOWER_NOT_TOP,
        'required_rack_type_id' => $threeHigh->getKey(),
        'placement_strategy' => LoadingProfile::PLACEMENT_ONE_PER_LEVEL,
    ]);
    $smallCoverProfile->setRelation('requiredRackType', $threeHigh);
    $smallCoverProfile->setRelation('allowedRackTypes', $bothRackTypes);

    $order = fillPlanOrder(1, 'ORD-02215', [
        fillPlanLine(1, fillPlanProduct('V3086-1', 1288, $v1Profile), null, true, priority: 1),
        fillPlanLine(2, fillPlanProduct('L3086-4', 1175, $linerProfile), 4),
        fillPlanLine(3, fillPlanProduct('W3086-M', 2190, $wilbertProfile), 4),
        fillPlanLine(4, fillPlanProduct('V1637-1', 300, $smallVaultProfile), 2),
        fillPlanLine(5, fillPlanProduct('2-3086G5', 575, $coverProfile), 1),
        fillPlanLine(6, fillPlanProduct('2-1637V1', 100, $smallCoverProfile), 1),
        fillPlanLine(7, fillPlanProduct('G3086-4', 3455, $gardenProfile), 2),
    ]);

    $plan = fillPlanner()->forTrip(fillPlanTrip(
        [$order],
        flatbedPalletCapacity: 4,
    ));

    expect($plan['fill_allocations'][0])->toMatchArray([
        'sku' => 'V3086-1',
        'planned_quantity' => 9,
        'resolved' => true,
        'source' => 'automatic',
    ])->and($plan['demand']->summary['known_weight_lbs'])->toBe(33237.0)
        ->and($plan['demand']->summary['remaining_product_weight_lbs'])->toBe(5263.0)
        ->and($plan['diagram']['flatbed_pallets_used'])->toBe(2)
        ->and(collect($plan['diagram']['flatbed_pallets'])->pluck('sku')->all())->toBe([
            'V1637-1',
            '2-1637V1',
        ])
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
