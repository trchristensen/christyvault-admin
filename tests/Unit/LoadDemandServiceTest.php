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
use Illuminate\Support\Collection;

function loadDemandProduct(string $sku, float $weight, LoadingProfile $profile): Product
{
    $product = new Product(['sku' => $sku, 'name' => $sku, 'weight_lbs' => $weight]);
    $product->id = abs(crc32($sku));
    $product->setRelation('loadingProfile', $profile);

    return $product;
}

function loadDemandLine(Product $product, ?int $quantity, bool $fillLoad = false): OrderProduct
{
    $line = new OrderProduct([
        'product_id' => $product->getKey(),
        'quantity' => $quantity,
        'fill_load' => $fillLoad ? 1 : 0,
    ]);
    $line->setRelation('product', $product);

    return $line;
}

function loadDemandOrder(string $number, array $lines): Order
{
    $order = new Order(['order_number' => $number]);
    $order->id = abs(crc32($number));
    $order->setRelation('orderProducts', new Collection($lines));
    $order->setRelation('location', null);

    return $order;
}

it('normalizes standard, oversized, pallet, and weight demand', function (): void {
    $standard = new LoadingProfile([
        'code' => 'standard_rack_box',
        'handling_method' => LoadingProfile::HANDLING_INDIVIDUAL,
        'rack_requirement' => LoadingProfile::RACK_STANDARD,
    ]);
    $oversized = new LoadingProfile([
        'code' => 'oversized_single_rack',
        'handling_method' => LoadingProfile::HANDLING_INDIVIDUAL,
        'rack_requirement' => LoadingProfile::RACK_SINGLE,
    ]);
    $pallet = new LoadingProfile([
        'code' => 'wilbert_urn_vault_pallet',
        'handling_method' => LoadingProfile::HANDLING_PALLET,
        'units_per_pallet' => 4,
        'rack_requirement' => LoadingProfile::RACK_STANDARD,
    ]);
    $order = loadDemandOrder('ORD-1', [
        loadDemandLine(loadDemandProduct('W3086-M', 2190, $standard), 2),
        loadDemandLine(loadDemandProduct('W3490-M', 2780, $oversized), 1),
        loadDemandLine(loadDemandProduct('UV1212-V', 104, $pallet), 5),
    ]);

    $result = (new LoadDemandService)->forOrder($order);

    expect($result->summary)->toMatchArray([
        'product_units' => 8,
        'standard_box_units' => 2,
        'oversized_rack_spots' => 1,
        'pallets' => 2,
        'known_weight_lbs' => 7680.0,
        'unknown_weight_items' => 0,
    ])->and($result->warnings)->toBeEmpty()
        ->and($result->isReadyForAutomaticPlacement())->toBeTrue();
});

it('counts loose accessories by weight without creating rack or pallet demand', function (): void {
    $profile = new LoadingProfile([
        'code' => 'loose_accessory',
        'handling_method' => LoadingProfile::HANDLING_LOOSE,
        'rack_requirement' => LoadingProfile::RACK_NONE,
    ]);
    $order = loadDemandOrder('ORD-LOOSE', [
        loadDemandLine(loadDemandProduct('SEA-02', 2, $profile), 20),
    ]);

    $result = (new LoadDemandService)->forOrder($order);

    expect($result->summary)->toMatchArray([
        'product_units' => 20,
        'standard_box_units' => 0,
        'oversized_rack_spots' => 0,
        'pallets' => 0,
        'known_weight_lbs' => 40.0,
        'unknown_weight_items' => 0,
    ])->and($result->warnings)->toBeEmpty()
        ->and($result->isReadyForAutomaticPlacement())->toBeTrue();
});

it('combines compatible products on a shared pallet within one stop', function (): void {
    $p400Profile = new LoadingProfile([
        'code' => 'p400_boxed',
        'handling_method' => LoadingProfile::HANDLING_PALLET,
        'units_per_pallet' => 4,
        'pallet_compatibility_group' => 'boxed_urn_products',
    ]);
    $p310Profile = new LoadingProfile([
        'code' => 'p310_boxed',
        'handling_method' => LoadingProfile::HANDLING_PALLET,
        'units_per_pallet' => 4,
        'pallet_compatibility_group' => 'boxed_urn_products',
    ]);
    $order = loadDemandOrder('ORD-2', [
        loadDemandLine(loadDemandProduct('P400', 45, $p400Profile), 2),
        loadDemandLine(loadDemandProduct('P310', 20, $p310Profile), 2),
    ]);

    $result = (new LoadDemandService)->forOrder($order);

    expect($result->summary['pallets'])->toBe(1);
});

it('orders trip stops for rear-first unloading and requires a vehicle configuration', function (): void {
    $profile = new LoadingProfile([
        'code' => 'standard_rack_box',
        'handling_method' => LoadingProfile::HANDLING_INDIVIDUAL,
        'rack_requirement' => LoadingProfile::RACK_STANDARD,
    ]);
    $firstOrder = loadDemandOrder('ORD-FIRST', [
        loadDemandLine(loadDemandProduct('W3086-M', 2190, $profile), 1),
    ]);
    $secondOrder = loadDemandOrder('ORD-SECOND', [
        loadDemandLine(loadDemandProduct('W3086-V', 2500, $profile), 1),
    ]);
    $firstStop = new TripStop(['order_id' => $firstOrder->getKey(), 'sequence' => 1]);
    $firstStop->setRelation('order', $firstOrder);
    $secondStop = new TripStop(['order_id' => $secondOrder->getKey(), 'sequence' => 2]);
    $secondStop->setRelation('order', $secondOrder);
    $trip = new Trip(['trip_number' => 'TRIP-1']);
    $trip->setRelation('stops', new Collection([$secondStop, $firstStop]));
    $trip->setRelation('orders', new Collection([$secondOrder, $firstOrder]));
    $trip->setRelation('vehicleConfiguration', null);

    $result = (new LoadDemandService)->forTrip($trip);

    expect($result->stops[0]['order_number'])->toBe('ORD-FIRST')
        ->and($result->stops[0]['unload_position'])->toBe('rear_first')
        ->and($result->warnings[0]['code'])->toBe('missing_vehicle_configuration')
        ->and($result->isReadyForAutomaticPlacement())->toBeFalse();
});

it('blocks automatic placement for missing profiles, weights, and fill-load quantities', function (): void {
    $profile = new LoadingProfile([
        'code' => 'standard_rack_box',
        'handling_method' => LoadingProfile::HANDLING_INDIVIDUAL,
        'rack_requirement' => LoadingProfile::RACK_STANDARD,
    ]);
    $knownProduct = loadDemandProduct('W3086-M', 2190, $profile);
    $unknownProduct = new Product(['sku' => 'UNKNOWN', 'name' => 'Unknown', 'weight_lbs' => null]);
    $unknownProduct->id = 999;
    $unknownProduct->setRelation('loadingProfile', null);
    $order = loadDemandOrder('ORD-3', [
        loadDemandLine($knownProduct, null, true),
        loadDemandLine($unknownProduct, 1),
    ]);

    $result = (new LoadDemandService)->forOrder($order);
    $codes = collect($result->warnings)->pluck('code');

    expect($codes)->toContain('fill_load_quantity_required', 'missing_loading_profile', 'missing_weight')
        ->and($result->isReadyForAutomaticPlacement())->toBeFalse();
});

it('treats 38,500 pounds as a hard product-cargo limit', function (): void {
    $profile = new LoadingProfile([
        'code' => 'standard_three_high_box',
        'name' => 'Standard box — 22 per 3-high load',
        'handling_method' => LoadingProfile::HANDLING_INDIVIDUAL,
        'rack_requirement' => LoadingProfile::RACK_STANDARD,
        'full_load_units' => 22,
    ]);
    $vehicle = new VehicleConfiguration([
        'name' => 'Rack trailer',
        'configuration_type' => VehicleConfiguration::TYPE_RACK_TRAILER,
        'max_product_weight_lbs' => 38500,
    ]);
    $product = loadDemandProduct('G3086-6', 1750, $profile);
    $exactResult = (new LoadDemandService)->forOrder(
        loadDemandOrder('ORD-EXACT', [loadDemandLine($product, 22)]),
        $vehicle,
    );
    $overResult = (new LoadDemandService)->forOrder(
        loadDemandOrder('ORD-OVER', [loadDemandLine($product, 23)]),
        $vehicle,
    );
    $overCodes = collect($overResult->warnings)->pluck('code');

    expect($exactResult->summary['known_weight_lbs'])->toBe(38500.0)
        ->and($exactResult->summary['remaining_product_weight_lbs'])->toBe(0.0)
        ->and($exactResult->summary['is_overweight'])->toBeFalse()
        ->and($exactResult->isReadyForAutomaticPlacement())->toBeTrue()
        ->and($overResult->summary['overweight_by_lbs'])->toBe(1750.0)
        ->and($overCodes)->toContain('weight_limit_exceeded', 'physical_full_load_exceeded')
        ->and($overResult->isReadyForAutomaticPlacement())->toBeFalse();
});

it('carries the Triune bottom-only constraint into planner items', function (): void {
    $profile = new LoadingProfile([
        'code' => 'regular_burial_vault_triune',
        'name' => 'Regular Wilbert Triune — bottom only',
        'handling_method' => LoadingProfile::HANDLING_INDIVIDUAL,
        'rack_requirement' => LoadingProfile::RACK_STANDARD,
        'required_rack_level' => LoadingProfile::LEVEL_BOTTOM,
        'full_load_units' => 15,
    ]);
    $vehicle = new VehicleConfiguration([
        'configuration_type' => VehicleConfiguration::TYPE_RACK_TRAILER,
        'max_product_weight_lbs' => 38500,
    ]);
    $result = (new LoadDemandService)->forOrder(
        loadDemandOrder('ORD-TRIUNE', [
            loadDemandLine(loadDemandProduct('W3086-SST', 2690, $profile), 15),
        ]),
        $vehicle,
    );

    expect($result->stops[0]['items'][0]['required_rack_level'])->toBe(LoadingProfile::LEVEL_BOTTOM)
        ->and($result->summary['overweight_by_lbs'])->toBe(1850.0)
        ->and(collect($result->warnings)->pluck('code'))->toContain('weight_limit_exceeded');
});

it('carries preferred and alternate rack compatibility into planner items', function (): void {
    $twoHigh = new RackType(['code' => 'standard_2_high', 'level_count' => 2]);
    $twoHigh->id = 1;
    $threeHigh = new RackType(['code' => 'standard_3_high', 'level_count' => 3]);
    $threeHigh->id = 2;
    $profile = new LoadingProfile([
        'code' => 'ring_liner_three_high',
        'handling_method' => LoadingProfile::HANDLING_INDIVIDUAL,
        'rack_requirement' => LoadingProfile::RACK_STANDARD,
        'required_rack_type_id' => 2,
    ]);
    $profile->setRelation('requiredRackType', $threeHigh);
    $profile->setRelation('allowedRackTypes', new Collection([$twoHigh, $threeHigh]));
    $result = (new LoadDemandService)->forOrder(loadDemandOrder('ORD-LINER', [
        loadDemandLine(loadDemandProduct('L2472-4', 513, $profile), 3),
    ]));
    $item = $result->stops[0]['items'][0];

    expect($item['required_rack_type'])->toBe('standard_3_high')
        ->and($item['required_rack_level_count'])->toBe(3)
        ->and($item['allowed_rack_type_codes'])->toBe([
            'standard_2_high',
            'standard_3_high',
        ]);
});

it('keeps three-high boxes preferred while allowing two-high rack openings', function (): void {
    $twoHigh = new RackType(['code' => 'standard_2_high', 'level_count' => 2]);
    $twoHigh->id = 1;
    $threeHigh = new RackType(['code' => 'standard_3_high', 'level_count' => 3]);
    $threeHigh->id = 2;
    $profile = new LoadingProfile([
        'code' => 'standard_three_high_box',
        'handling_method' => LoadingProfile::HANDLING_INDIVIDUAL,
        'rack_requirement' => LoadingProfile::RACK_STANDARD,
        'required_rack_type_id' => 2,
    ]);
    $profile->setRelation('requiredRackType', $threeHigh);
    $profile->setRelation('allowedRackTypes', new Collection([$twoHigh, $threeHigh]));
    $result = (new LoadDemandService)->forOrder(loadDemandOrder('ORD-L4', [
        loadDemandLine(loadDemandProduct('L3086-4', 1175, $profile), 1),
    ]));
    $item = $result->stops[0]['items'][0];

    expect($item['required_rack_type'])->toBe('standard_3_high')
        ->and($item['allowed_rack_type_codes'])->toBe([
            'standard_2_high',
            'standard_3_high',
        ]);
});
