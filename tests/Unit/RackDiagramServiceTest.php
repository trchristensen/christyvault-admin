<?php

use App\Services\LoadPlanning\LoadDemandResult;
use App\Services\LoadPlanning\RackDiagramService;

function rackDiagramItem(array $overrides = []): array
{
    return array_merge([
        'sku' => 'G3086-6',
        'name' => 'Single Garden Crypt',
        'quantity' => 1,
        'fill_load' => false,
        'handling_method' => 'individual',
        'rack_requirement' => 'standard',
        'required_rack_level' => 'any',
        'required_rack_type' => 'standard_3_high',
        'required_rack_level_count' => 3,
        'unit_weight_lbs' => 1750,
        'unit_of_measure' => 'vault',
        'units_per_rack_position' => 1,
    ], $overrides);
}

function rackDiagramStop(int $sequence, array $items): array
{
    return [
        'sequence' => $sequence,
        'order_number' => 'ORD-'.$sequence,
        'location_name' => 'Stop '.$sequence,
        'items' => $items,
    ];
}

function rackDiagramDemand(array $stops, int $rackSpots = 8, int $flatbedPalletCapacity = 0): LoadDemandResult
{
    return new LoadDemandResult(
        summary: [],
        stops: $stops,
        warnings: [],
        vehicleConfiguration: [
            'type' => 'rack_trailer',
            'rack_spot_count' => $rackSpots,
            'flatbed_pallet_capacity' => $flatbedPalletCapacity,
        ],
    );
}

it('places a confirmed 22-box load into eight three-high rack spots', function (): void {
    $diagram = (new RackDiagramService)->forDemand(rackDiagramDemand([
        rackDiagramStop(1, [rackDiagramItem(['quantity' => 22])]),
    ]));
    $emptyCells = collect($diagram['racks'])
        ->flatMap(fn (array $rack): array => $rack['cells'])
        ->filter(fn ($cell): bool => $cell === null)
        ->count();

    expect($diagram['placed_units'])->toBe(22)
        ->and($diagram['used_rack_spots'])->toBe(8)
        ->and($diagram['unplaced'])->toBeEmpty()
        ->and($diagram['legend'][0]['code'])->toBe('G6')
        ->and($diagram['legend'][0]['unit_weight_lbs'])->toBe(1750)
        ->and($diagram['legend'][0]['unit_of_measure'])->toBe('vault')
        ->and($diagram['legend'][0]['handling_method'])->toBe('individual')
        ->and($diagram['legend'][0]['rack_requirement'])->toBe('standard')
        ->and($emptyCells)->toBe(2)
        ->and($diagram['racks'][0]['cells'])->not->toContain(null)
        ->and($diagram['racks'][6]['cells'])->not->toContain(null)
        ->and($diagram['racks'][7]['cells'][0])->not->toBeNull()
        ->and($diagram['racks'][7]['cells'][1])->toBeNull()
        ->and($diagram['racks'][7]['cells'][2])->toBeNull();
});

it('counts loose accessories without consuming rack or pallet positions', function (): void {
    $diagram = (new RackDiagramService)->forDemand(rackDiagramDemand([
        rackDiagramStop(1, [rackDiagramItem([
            'sku' => 'SEA-02',
            'name' => 'Sealer roll',
            'quantity' => 20,
            'handling_method' => 'loose',
            'rack_requirement' => 'none',
            'required_rack_type' => null,
            'required_rack_level_count' => null,
            'unit_weight_lbs' => 2,
            'total_weight_lbs' => 40,
        ])]),
    ]));

    expect($diagram['placed_units'])->toBe(20)
        ->and($diagram['used_rack_spots'])->toBe(0)
        ->and($diagram['flatbed_pallets_used'])->toBe(0)
        ->and($diagram['unplaced'])->toBeEmpty()
        ->and($diagram['non_rack_cargo'])->toHaveCount(1)
        ->and($diagram['non_rack_cargo'][0])->toMatchArray([
            'sku' => 'SEA-02',
            'quantity' => 20,
            'stop_sequence' => 1,
            'total_weight_lbs' => 40,
        ]);
});

it('reserves bottom-only products for bottom rack positions', function (): void {
    $diagram = (new RackDiagramService)->forDemand(rackDiagramDemand([
        rackDiagramStop(1, [rackDiagramItem([
            'sku' => 'W3086-SST',
            'quantity' => 10,
            'required_rack_level' => 'bottom',
            'required_rack_type' => 'standard_2_high',
            'required_rack_level_count' => 2,
        ])]),
    ]));

    expect($diagram['placed_units'])->toBe(8)
        ->and($diagram['unplaced'])->toHaveCount(1)
        ->and($diagram['unplaced'][0])->toMatchArray([
            'quantity' => 2,
            'reason' => 'Not enough eligible bottom rack positions.',
        ]);

    foreach ($diagram['racks'] as $rack) {
        expect($rack['cells'][0])->not->toBeNull()
            ->and($rack['cells'][1])->toBeNull();
    }
});

it('loads no more than two regular Wilbert burial vaults in one rack', function (): void {
    $twoHigh = [
        'required_rack_type' => 'standard_2_high',
        'required_rack_level_count' => 2,
    ];
    $diagram = (new RackDiagramService)->forDemand(rackDiagramDemand([
        rackDiagramStop(1, [
            rackDiagramItem([...$twoHigh, ...[
                'sku' => 'W3086-SST',
                'name' => 'Stainless Steel Triune',
                'quantity' => 1,
                'required_rack_level' => 'bottom',
                'unit_weight_lbs' => 2690,
            ]]),
            rackDiagramItem([...$twoHigh, ...[
                'sku' => 'W3086-M',
                'name' => 'Monticello',
                'quantity' => 3,
                'unit_weight_lbs' => 2190,
            ]]),
        ]),
    ]));

    expect($diagram['placed_units'])->toBe(4)
        ->and($diagram['used_rack_spots'])->toBe(2)
        ->and($diagram['unplaced'])->toBeEmpty()
        ->and($diagram['racks'][0]['cells'][0]['sku'])->toBe('W3086-SST')
        ->and($diagram['racks'][0]['cells'][1]['sku'])->toBe('W3086-M');

    foreach (collect($diagram['racks'])->whereNotNull('type_code') as $rack) {
        expect(collect($rack['cells'])->filter())->toHaveCount(2);
    }
});

it('uses one complete rack spot for each oversized product', function (): void {
    $diagram = (new RackDiagramService)->forDemand(rackDiagramDemand([
        rackDiagramStop(1, [rackDiagramItem([
            'sku' => 'W3490-M',
            'quantity' => 2,
            'rack_requirement' => 'single',
            'required_rack_type' => null,
            'required_rack_level_count' => null,
        ])]),
    ]));

    expect($diagram['placed_units'])->toBe(2)
        ->and($diagram['used_rack_spots'])->toBe(2)
        ->and($diagram['racks'][0]['type_code'])->toBe('oversized_single')
        ->and($diagram['racks'][1]['type_code'])->toBe('oversized_single');
});

it('stacks up to four G5 covers in each rack position', function (): void {
    $diagram = (new RackDiagramService)->forDemand(rackDiagramDemand([
        rackDiagramStop(1, [rackDiagramItem([
            'sku' => '2-3690G5',
            'name' => 'G5 Cover',
            'quantity' => 6,
            'required_rack_type' => 'standard_2_high',
            'required_rack_level_count' => 2,
            'unit_weight_lbs' => 680,
            'units_per_rack_position' => 4,
        ])]),
    ]));

    expect($diagram['placed_units'])->toBe(6)
        ->and($diagram['rack_spot_count'])->toBe(8)
        ->and($diagram['racks'])->toHaveCount(8)
        ->and($diagram['used_rack_spots'])->toBe(1)
        ->and($diagram['unplaced'])->toBeEmpty()
        ->and($diagram['legend'][0]['code'])->toBe('G5C')
        ->and($diagram['legend'][0]['units_per_rack_position'])->toBe(4)
        ->and($diagram['racks'][0]['cells'][0]['code'])->toBe('4×G5C')
        ->and($diagram['racks'][0]['cells'][0]['quantity'])->toBe(4)
        ->and($diagram['racks'][0]['cells'][1]['code'])->toBe('2×G5C')
        ->and($diagram['racks'][0]['cells'][1]['quantity'])->toBe(2)
        ->and($diagram['racks'][1]['type_code'])->toBeNull();
});

it('retains all ten physical racks in the ten-rack trailer configuration', function (): void {
    $diagram = (new RackDiagramService)->forDemand(rackDiagramDemand([
        rackDiagramStop(1, [rackDiagramItem(['quantity' => 1])]),
    ], rackSpots: 10));

    expect($diagram['rack_spot_count'])->toBe(10)
        ->and($diagram['racks'])->toHaveCount(10)
        ->and($diagram['used_rack_spots'])->toBe(1)
        ->and(collect($diagram['racks'])->whereNull('type_code'))->toHaveCount(9);
});

it('loads four Wilbert urn vaults per pallet and two pallets per capable rack level', function (): void {
    $palletProfile = [
        'handling_method' => 'pallet',
        'units_per_pallet' => 4,
        'required_rack_type' => 'standard_2_high',
        'required_rack_level_count' => 2,
        'allowed_rack_type_codes' => ['standard_2_high', 'standard_3_high'],
        'allowed_rack_types' => [
            [
                'code' => 'standard_2_high',
                'level_count' => 2,
                'pallet_capable_levels' => 1,
                'pallets_per_capable_level' => 2,
            ],
            [
                'code' => 'standard_3_high',
                'level_count' => 3,
                'pallet_capable_levels' => 2,
                'pallets_per_capable_level' => 2,
            ],
        ],
    ];
    $diagram = (new RackDiagramService)->forDemand(rackDiagramDemand([
        rackDiagramStop(1, [
            rackDiagramItem([...$palletProfile, ...[
                'sku' => 'UV1212-VWS',
                'name' => 'Venetian Urn Vault',
                'quantity' => 5,
                'unit_weight_lbs' => 104,
            ]]),
            rackDiagramItem([...$palletProfile, ...[
                'sku' => 'UV1212-M',
                'name' => 'Monticello Urn Vault',
                'quantity' => 1,
                'unit_weight_lbs' => 104,
            ]]),
        ]),
    ], flatbedPalletCapacity: 4));
    $palletCells = collect($diagram['racks'])
        ->flatMap(fn (array $rack): array => $rack['cells'])
        ->filter(fn ($cell): bool => (bool) ($cell['is_pallet_level'] ?? false));
    $loadedPallets = $palletCells->flatMap(fn (array $cell): array => $cell['pallets']);

    expect($diagram['placed_units'])->toBe(6)
        ->and($diagram['used_rack_spots'])->toBe(2)
        ->and($diagram['flatbed_pallets_used'])->toBe(0)
        ->and($diagram['unplaced'])->toBeEmpty()
        ->and($loadedPallets)->toHaveCount(3)
        ->and($loadedPallets->where('sku', 'UV1212-VWS')->pluck('units')->all())->toBe([4, 1])
        ->and($loadedPallets->where('sku', 'UV1212-M')->pluck('units')->all())->toBe([1])
        ->and($palletCells->first()['pallets'])->toHaveCount(2)
        ->and($diagram['racks'][0]['cells'][1])->toBeNull();
});

it('uses the fewest fallback flatbed pallet spots needed to fit the complete load', function (): void {
    $palletProfile = [
        'handling_method' => 'pallet',
        'units_per_pallet' => 4,
        'required_rack_type' => 'standard_2_high',
        'required_rack_level_count' => 2,
        'allowed_rack_type_codes' => ['standard_2_high', 'standard_3_high'],
        'allowed_rack_types' => [[
            'code' => 'standard_2_high',
            'level_count' => 2,
            'pallet_capable_levels' => 1,
            'pallets_per_capable_level' => 2,
        ]],
    ];
    $diagram = (new RackDiagramService)->forDemand(rackDiagramDemand([
        rackDiagramStop(1, [
            rackDiagramItem([
                'sku' => 'W3490-M',
                'name' => 'Oversized vault',
                'quantity' => 8,
                'rack_requirement' => 'single',
                'required_rack_type' => null,
                'required_rack_level_count' => null,
                'unit_weight_lbs' => 3000,
            ]),
            rackDiagramItem([...$palletProfile, ...[
                'sku' => 'UV1212-M',
                'name' => 'Monticello Urn Vault',
                'quantity' => 4,
                'unit_weight_lbs' => 104,
            ]]),
        ]),
    ], flatbedPalletCapacity: 4));

    expect($diagram['placed_units'])->toBe(12)
        ->and($diagram['used_rack_spots'])->toBe(8)
        ->and($diagram['flatbed_pallet_capacity'])->toBe(4)
        ->and($diagram['flatbed_pallets_used'])->toBe(1)
        ->and($diagram['flatbed_pallets'][0])->toMatchArray([
            'spot_number' => 1,
            'sku' => 'UV1212-M',
            'units' => 4,
            'stop_sequence' => 1,
        ])
        ->and($diagram['unplaced'])->toBeEmpty();
});

it('does not exceed the configured flatbed pallet fallback capacity', function (): void {
    $diagram = (new RackDiagramService)->forDemand(rackDiagramDemand([
        rackDiagramStop(1, [
            rackDiagramItem([
                'sku' => 'W3490-M',
                'quantity' => 8,
                'rack_requirement' => 'single',
                'required_rack_type' => null,
                'required_rack_level_count' => null,
                'unit_weight_lbs' => 3000,
            ]),
            rackDiagramItem([
                'sku' => 'UV1212-M',
                'name' => 'Monticello Urn Vault',
                'quantity' => 20,
                'handling_method' => 'pallet',
                'units_per_pallet' => 4,
                'required_rack_type' => 'standard_2_high',
                'required_rack_level_count' => 2,
                'allowed_rack_type_codes' => ['standard_2_high'],
                'allowed_rack_types' => [[
                    'code' => 'standard_2_high',
                    'level_count' => 2,
                    'pallet_capable_levels' => 1,
                    'pallets_per_capable_level' => 2,
                ]],
                'unit_weight_lbs' => 104,
            ]),
        ]),
    ], flatbedPalletCapacity: 4));

    expect($diagram['placed_units'])->toBe(24)
        ->and($diagram['flatbed_pallets_used'])->toBe(4)
        ->and($diagram['unplaced'])->toHaveCount(1)
        ->and($diagram['unplaced'][0])->toMatchArray([
            'sku' => 'UV1212-M',
            'quantity' => 4,
            'reason' => 'Not enough compatible pallet rack or fallback flatbed positions.',
        ]);
});

it('combines compatible products on one fallback flatbed pallet', function (): void {
    $palletProfile = [
        'handling_method' => 'pallet',
        'pallet_compatibility_group' => 'boxed_urn_products',
        'required_rack_type' => 'standard_2_high',
        'required_rack_level_count' => 2,
        'allowed_rack_type_codes' => ['standard_2_high'],
        'allowed_rack_types' => [[
            'code' => 'standard_2_high',
            'level_count' => 2,
            'pallet_capable_levels' => 1,
            'pallets_per_capable_level' => 2,
        ]],
    ];
    $diagram = (new RackDiagramService)->forDemand(rackDiagramDemand([
        rackDiagramStop(1, [
            rackDiagramItem([
                'sku' => 'W3490-M',
                'quantity' => 8,
                'rack_requirement' => 'single',
                'required_rack_type' => null,
                'required_rack_level_count' => null,
                'unit_weight_lbs' => 3000,
            ]),
            rackDiagramItem([...$palletProfile, ...[
                'sku' => 'P400',
                'name' => 'P400',
                'quantity' => 4,
                'units_per_pallet' => 9,
                'unit_weight_lbs' => 45,
            ]]),
            rackDiagramItem([...$palletProfile, ...[
                'sku' => 'P310',
                'name' => 'P310',
                'quantity' => 10,
                'units_per_pallet' => 18,
                'unit_weight_lbs' => 20,
            ]]),
        ]),
    ], flatbedPalletCapacity: 4));

    expect($diagram['placed_units'])->toBe(22)
        ->and($diagram['flatbed_pallets_used'])->toBe(1)
        ->and($diagram['flatbed_pallets'][0]['sku'])->toBe('MIXED')
        ->and($diagram['flatbed_pallets'][0]['capacity_used'])->toBe(1.0)
        ->and(collect($diagram['flatbed_pallets'][0]['products'])->pluck('sku')->all())->toBe(['P400', 'P310'])
        ->and($diagram['unplaced'])->toBeEmpty();
});

it('combines compatible P-series products with different capacities on one mixed pallet', function (): void {
    $palletProfile = [
        'handling_method' => 'pallet',
        'pallet_compatibility_group' => 'boxed_urn_products',
        'required_rack_type' => 'standard_2_high',
        'required_rack_level_count' => 2,
        'allowed_rack_type_codes' => ['standard_2_high', 'standard_3_high'],
        'allowed_rack_types' => [[
            'code' => 'standard_2_high',
            'level_count' => 2,
            'pallet_capable_levels' => 1,
            'pallets_per_capable_level' => 2,
        ]],
    ];
    $diagram = (new RackDiagramService)->forDemand(rackDiagramDemand([
        rackDiagramStop(1, [
            rackDiagramItem([...$palletProfile, ...[
                'sku' => 'P400',
                'name' => 'P400',
                'quantity' => 4,
                'units_per_pallet' => 9,
                'unit_weight_lbs' => 45,
            ]]),
            rackDiagramItem([...$palletProfile, ...[
                'sku' => 'P310',
                'name' => 'P310',
                'quantity' => 10,
                'units_per_pallet' => 18,
                'unit_weight_lbs' => 20,
            ]]),
        ]),
    ]));
    $loadedPallets = collect($diagram['racks'])
        ->flatMap(fn (array $rack): array => $rack['cells'])
        ->filter(fn ($cell): bool => (bool) ($cell['is_pallet_level'] ?? false))
        ->flatMap(fn (array $cell): array => $cell['pallets']);
    $mixedPallet = $loadedPallets->first();

    expect($diagram['placed_units'])->toBe(14)
        ->and($diagram['used_rack_spots'])->toBe(1)
        ->and($diagram['unplaced'])->toBeEmpty()
        ->and($loadedPallets)->toHaveCount(1)
        ->and($mixedPallet['sku'])->toBe('MIXED')
        ->and($mixedPallet['capacity_used'])->toBe(1.0)
        ->and(collect($mixedPallet['products'])->pluck('sku')->all())->toBe(['P400', 'P310']);
});

it('shares the open bottom G4 rack position with G5 covers from the same stop', function (): void {
    $diagram = (new RackDiagramService)->forDemand(rackDiagramDemand([
        rackDiagramStop(2, [
            rackDiagramItem([
                'sku' => 'G3086-4',
                'quantity' => 7,
                'required_rack_type' => 'standard_2_high',
                'required_rack_level_count' => 2,
                'placement_strategy' => 'full_top_split_bottom_pair',
                'unit_weight_lbs' => 3455,
            ]),
            rackDiagramItem([
                'sku' => '2-3690G5',
                'quantity' => 2,
                'required_rack_type' => 'standard_2_high',
                'required_rack_level_count' => 2,
                'unit_weight_lbs' => 680,
                'units_per_rack_position' => 4,
            ]),
        ]),
    ]));
    $coverRack = collect($diagram['racks'])->first(
        fn (array $rack): bool => collect($rack['cells'])->filter()->contains('sku', '2-3690G5'),
    );

    expect($diagram['placed_units'])->toBe(9)
        ->and($diagram['used_rack_spots'])->toBe(7)
        ->and($diagram['unplaced'])->toBeEmpty()
        ->and($coverRack['cells'][1]['code'])->toBe('G4')
        ->and($coverRack['cells'][0]['code'])->toBe('2×G5C')
        ->and($coverRack['stop_sequences'])->toBe([2]);
});

it('fills an open two-high position before opening a preferred three-high ring-liner rack', function (): void {
    $diagram = (new RackDiagramService)->forDemand(rackDiagramDemand([
        rackDiagramStop(1, [
            rackDiagramItem([
                'sku' => 'G3086-4',
                'quantity' => 10,
                'required_rack_type' => 'standard_2_high',
                'required_rack_level_count' => 2,
                'placement_strategy' => 'full_top_split_bottom_pair',
                'unit_weight_lbs' => 3455,
            ]),
            rackDiagramItem([
                'sku' => 'L2472-4',
                'quantity' => 3,
                'required_rack_type' => 'standard_3_high',
                'required_rack_level_count' => 3,
                'allowed_rack_type_codes' => ['standard_2_high', 'standard_3_high'],
                'unit_weight_lbs' => 513,
            ]),
        ]),
    ]));
    $cells = collect($diagram['racks'])->flatMap(fn (array $rack): array => $rack['cells'])->filter();

    expect($diagram['placed_units'])->toBe(13)
        ->and($diagram['used_rack_spots'])->toBe(8)
        ->and($diagram['unplaced'])->toBeEmpty()
        ->and($cells->where('sku', 'G3086-4')->where('component', 'whole'))->toHaveCount(8)
        ->and($cells->where('sku', 'G3086-4')->where('component', 'half'))->toHaveCount(4)
        ->and($cells->where('sku', 'L2472-4'))->toHaveCount(3)
        ->and($cells->where('sku', 'L2472-4')->pluck('level')->unique()->all())->toBe([1]);
});

it('places twelve doubles as eight whole tops and four split bottom pairs', function (): void {
    $diagram = (new RackDiagramService)->forDemand(rackDiagramDemand([
        rackDiagramStop(1, [rackDiagramItem([
            'sku' => 'G3086-5',
            'name' => 'Companion Garden Crypt',
            'quantity' => 12,
            'required_rack_type' => 'standard_2_high',
            'required_rack_level_count' => 2,
            'placement_strategy' => 'full_top_split_bottom_pair',
        ])]),
    ]));
    $cells = collect($diagram['racks'])->flatMap(fn (array $rack): array => $rack['cells']);

    expect($diagram['placed_units'])->toBe(12)
        ->and($diagram['used_rack_spots'])->toBe(8)
        ->and($diagram['unplaced'])->toBeEmpty()
        ->and($cells->where('component', 'whole'))->toHaveCount(8)
        ->and($cells->where('component', 'half'))->toHaveCount(8)
        ->and($cells->sum('unit_fraction'))->toBe(12.0)
        ->and($diagram['racks'][0]['cells'][1]['code'])->toBe('G5')
        ->and($diagram['racks'][0]['cells'][0]['code'])->toBe('½G5')
        ->and($diagram['racks'][1]['cells'][0]['split_pair'])->toBe(1);
});

it('keeps G4 and G5 whole when the mixed load fits without splitting', function (): void {
    $diagram = (new RackDiagramService)->forDemand(rackDiagramDemand([
        rackDiagramStop(1, [
            rackDiagramItem([
                'sku' => 'W3086-SST',
                'name' => 'Stainless Steel Triune',
                'quantity' => 1,
                'required_rack_type' => 'standard_2_high',
                'required_rack_level_count' => 2,
                'required_rack_level' => 'bottom',
                'unit_weight_lbs' => 2690,
                'loading_profile' => 'regular_burial_vault_triune',
            ]),
            rackDiagramItem([
                'sku' => 'G3086-5',
                'name' => 'Companion Garden Crypt',
                'quantity' => 3,
                'required_rack_type' => 'standard_2_high',
                'required_rack_level_count' => 2,
                'placement_strategy' => 'full_top_split_bottom_pair',
                'unit_weight_lbs' => 2520,
                'loading_profile' => 'double_garden_crypt',
            ]),
            rackDiagramItem([
                'sku' => 'W3086-M',
                'name' => 'Monticello',
                'quantity' => 5,
                'required_rack_type' => 'standard_2_high',
                'required_rack_level_count' => 2,
                'unit_weight_lbs' => 2190,
                'loading_profile' => 'regular_burial_vault',
            ]),
            rackDiagramItem([
                'sku' => 'G3086-6',
                'quantity' => 4,
                'unit_weight_lbs' => 1750,
                'allowed_rack_type_codes' => ['standard_2_high', 'standard_3_high'],
            ]),
            rackDiagramItem([
                'sku' => 'L3086-4',
                'quantity' => 5,
                'unit_weight_lbs' => 1175,
                'allowed_rack_type_codes' => ['standard_2_high', 'standard_3_high'],
            ]),
        ]),
    ]));
    $cells = collect($diagram['racks'])->flatMap(fn (array $rack): array => $rack['cells'])->filter();
    $g5Cells = $cells->where('sku', 'G3086-5');
    $g5Racks = collect($diagram['racks'])->filter(
        fn (array $rack): bool => collect($rack['cells'])->filter()->contains('sku', 'G3086-5'),
    );
    $triuneRack = collect($diagram['racks'])->first(
        fn (array $rack): bool => collect($rack['cells'])->filter()->contains('sku', 'W3086-SST'),
    );

    expect($diagram['placed_units'])->toBe(18)
        ->and($diagram['used_rack_spots'])->toBe(8)
        ->and($diagram['unplaced'])->toBeEmpty()
        ->and($g5Cells)->toHaveCount(3)
        ->and($g5Cells->where('component', 'whole'))->toHaveCount(3)
        ->and($g5Cells->where('component', 'half'))->toBeEmpty()
        ->and($g5Cells->pluck('level')->unique()->all())->toBe([2])
        ->and($g5Racks->every(fn (array $rack): bool => data_get($rack, 'cells.0.sku') === 'L3086-4'))->toBeTrue()
        ->and(collect($triuneRack['cells'])->filter()->pluck('sku')->sort()->values()->all())->toBe([
            'W3086-M',
            'W3086-SST',
        ]);
});

it('splits G4 and G5 only when compacting is required to place the complete load', function (): void {
    $diagram = (new RackDiagramService)->forDemand(rackDiagramDemand([
        rackDiagramStop(1, [
            rackDiagramItem([
                'sku' => 'G3086-4',
                'quantity' => 7,
                'required_rack_type' => 'standard_2_high',
                'required_rack_level_count' => 2,
                'placement_strategy' => 'full_top_split_bottom_pair',
                'unit_weight_lbs' => 3455,
            ]),
            rackDiagramItem([
                'sku' => 'G3086-6',
                'quantity' => 6,
                'unit_weight_lbs' => 1750,
            ]),
        ]),
    ]));
    $cells = collect($diagram['racks'])->flatMap(fn (array $rack): array => $rack['cells'])->filter();

    expect($diagram['placed_units'])->toBe(13)
        ->and($diagram['unplaced'])->toBeEmpty()
        ->and($cells->where('sku', 'G3086-4')->where('component', 'whole'))->toHaveCount(5)
        ->and($cells->where('sku', 'G3086-4')->where('component', 'half'))->toHaveCount(4)
        ->and($cells->where('sku', 'G3086-6'))->toHaveCount(6);
});

it('keeps unconfirmed rack heights off the diagram', function (): void {
    $diagram = (new RackDiagramService)->forDemand(rackDiagramDemand([
        rackDiagramStop(1, [rackDiagramItem([
            'quantity' => 4,
            'required_rack_type' => null,
            'required_rack_level_count' => null,
        ])]),
    ]));

    expect($diagram['placed_units'])->toBe(0)
        ->and($diagram['used_rack_spots'])->toBe(0)
        ->and($diagram['unplaced'][0])->toMatchArray([
            'quantity' => 4,
            'reason' => 'Rack height/type has not been confirmed for this product.',
        ]);
});

it('loads the first stop at the rear without mixing stops in a rack', function (): void {
    $diagram = (new RackDiagramService)->forDemand(rackDiagramDemand([
        rackDiagramStop(1, [rackDiagramItem(['quantity' => 1])]),
        rackDiagramStop(2, [rackDiagramItem(['sku' => 'V3086-1', 'quantity' => 1])]),
    ]));

    expect($diagram['racks'][0]['cells'][0]['stop_sequence'])->toBe(2)
        ->and($diagram['racks'][1]['cells'][0]['stop_sequence'])->toBe(1)
        ->and($diagram['racks'][0]['stop_sequences'])->toBe([2])
        ->and($diagram['racks'][1]['stop_sequences'])->toBe([1])
        ->and($diagram['racks'][2]['type_code'])->toBeNull();
});

it('uses one compatible shared rack at the boundary only when no empty rack remains', function (): void {
    $diagram = (new RackDiagramService)->forDemand(rackDiagramDemand([
        rackDiagramStop(1, [
            rackDiagramItem([
                'sku' => 'W3490-M',
                'quantity' => 1,
                'rack_requirement' => 'single',
                'required_rack_type' => null,
                'required_rack_level_count' => null,
                'unit_weight_lbs' => 2780,
            ]),
            rackDiagramItem([
                'sku' => 'W3086-M',
                'quantity' => 2,
                'required_rack_type' => 'standard_2_high',
                'required_rack_level_count' => 2,
                'allowed_rack_type_codes' => ['standard_2_high'],
                'unit_weight_lbs' => 2190,
            ]),
        ]),
        rackDiagramStop(2, [rackDiagramItem([
            'sku' => 'L3086-4',
            'quantity' => 1,
            'required_rack_type' => 'standard_3_high',
            'required_rack_level_count' => 3,
            'allowed_rack_type_codes' => ['standard_2_high', 'standard_3_high'],
            'unit_weight_lbs' => 1175,
        ])]),
    ], rackSpots: 2));
    $sharedRack = collect($diagram['racks'])->first(
        fn (array $rack): bool => count($rack['stop_sequences']) === 2,
    );

    expect($sharedRack)->not->toBeNull()
        ->and($sharedRack['type_code'])->toBe('standard_2_high')
        ->and($sharedRack['stop_sequences'])->toBe([2, 1])
        ->and($sharedRack['cells'][0])->toMatchArray([
            'sku' => 'L3086-4',
            'stop_sequence' => 2,
        ])
        ->and($sharedRack['cells'][1])->toMatchArray([
            'sku' => 'W3086-M',
            'stop_sequence' => 1,
        ])
        ->and($diagram['unplaced'])->toHaveCount(1)
        ->and($diagram['unplaced'][0])->toMatchArray([
            'sku' => 'W3086-M',
            'quantity' => 1,
        ]);
});

it('keeps adjacent stops on separate racks when an empty rack is available', function (): void {
    $diagram = (new RackDiagramService)->forDemand(rackDiagramDemand([
        rackDiagramStop(1, [rackDiagramItem([
            'sku' => 'W3086-M',
            'quantity' => 1,
            'required_rack_type' => 'standard_2_high',
            'required_rack_level_count' => 2,
            'unit_weight_lbs' => 2190,
        ])]),
        rackDiagramStop(2, [rackDiagramItem([
            'sku' => 'L3086-4',
            'quantity' => 1,
            'required_rack_type' => 'standard_3_high',
            'required_rack_level_count' => 3,
            'allowed_rack_type_codes' => ['standard_2_high', 'standard_3_high'],
            'unit_weight_lbs' => 1175,
        ])]),
    ], rackSpots: 3));

    expect(collect($diagram['racks'])->filter(
        fn (array $rack): bool => count($rack['stop_sequences']) > 1,
    ))->toBeEmpty()
        ->and($diagram['unplaced'])->toBeEmpty();
});

it('puts heavier products lower and forward within the same stop', function (): void {
    $diagram = (new RackDiagramService)->forDemand(rackDiagramDemand([
        rackDiagramStop(1, [
            rackDiagramItem(['sku' => 'LIGHT', 'quantity' => 1, 'unit_weight_lbs' => 500]),
            rackDiagramItem(['sku' => 'HEAVY', 'quantity' => 1, 'unit_weight_lbs' => 3000]),
        ]),
    ]));

    expect($diagram['racks'][0]['cells'][0]['sku'])->toBe('HEAVY')
        ->and($diagram['racks'][0]['cells'][1]['sku'])->toBe('LIGHT')
        ->and($diagram['racks'][1]['type_code'])->toBeNull();
});

it('orders completed racks by total rack weight rather than individual unit weight', function (): void {
    $diagram = (new RackDiagramService)->forDemand(rackDiagramDemand([
        rackDiagramStop(1, [
            rackDiagramItem([
                'sku' => 'W3086-M',
                'quantity' => 2,
                'required_rack_type' => 'standard_2_high',
                'required_rack_level_count' => 2,
                'unit_weight_lbs' => 2190,
            ]),
            rackDiagramItem([
                'sku' => 'G3086-6',
                'quantity' => 3,
                'required_rack_type' => 'standard_3_high',
                'required_rack_level_count' => 3,
                'unit_weight_lbs' => 1750,
            ]),
        ]),
    ]));

    expect($diagram['racks'][0]['cells'][0]['sku'])->toBe('G3086-6')
        ->and($diagram['racks'][0]['product_weight_lbs'])->toBe(5250.0)
        ->and($diagram['racks'][1]['cells'][0]['sku'])->toBe('W3086-M')
        ->and($diagram['racks'][1]['product_weight_lbs'])->toBe(4380.0);
});
