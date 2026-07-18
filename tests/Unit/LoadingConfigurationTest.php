<?php

use App\Models\LoadingProfile;
use App\Models\RackType;

it('calculates pallet capacity from pallet-capable rack levels', function (): void {
    $twoHigh = new RackType([
        'level_count' => 2,
        'pallet_capable_levels' => 1,
        'pallets_per_capable_level' => 2,
    ]);
    $threeHigh = new RackType([
        'level_count' => 3,
        'pallet_capable_levels' => 2,
        'pallets_per_capable_level' => 2,
    ]);

    expect($twoHigh->palletCapacity())->toBe(2)
        ->and($threeHigh->palletCapacity())->toBe(4);
});

it('only allows profiles in the same explicit group to share a pallet', function (): void {
    $p400 = new LoadingProfile(['pallet_compatibility_group' => 'boxed_urn_products']);
    $p310 = new LoadingProfile(['pallet_compatibility_group' => 'boxed_urn_products']);
    $wilbertUrnVault = new LoadingProfile(['pallet_compatibility_group' => null]);
    $anotherUngroupedProduct = new LoadingProfile(['pallet_compatibility_group' => null]);

    expect($p400->canSharePalletWith($p310))->toBeTrue()
        ->and($p400->canSharePalletWith($wilbertUrnVault))->toBeFalse()
        ->and($wilbertUrnVault->canSharePalletWith($anotherUngroupedProduct))->toBeFalse();
});

it('defines the paired bottom split strategy for divisible products', function (): void {
    expect(LoadingProfile::placementStrategyOptions())
        ->toHaveKey(LoadingProfile::PLACEMENT_ONE_PER_LEVEL)
        ->toHaveKey(LoadingProfile::PLACEMENT_FULL_TOP_SPLIT_BOTTOM_PAIR);
});
