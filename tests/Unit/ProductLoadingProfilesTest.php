<?php

it('contains unique normalized loading-profile SKU assignments', function (): void {
    $assignments = require dirname(__DIR__, 2).'/database/data/product_loading_profiles.php';
    $skus = [];

    foreach ($assignments as $assignment) {
        expect($assignment)
            ->toHaveKeys(['sku', 'profile_code'])
            ->and($assignment['sku'])->toBeString()->not->toBeEmpty()
            ->and($assignment['profile_code'])->toBeString()->not->toBeEmpty();

        $normalizedSku = mb_strtoupper(trim($assignment['sku']));

        expect($skus)->not->toHaveKey($normalizedSku);
        $skus[$normalizedSku] = $assignment['profile_code'];
    }
});

it('keeps uncertain large-product families out of automatic assignment', function (): void {
    $assignments = collect(require dirname(__DIR__, 2).'/database/data/product_loading_profiles.php')
        ->keyBy(fn (array $assignment): string => mb_strtoupper(trim($assignment['sku'])));

    foreach (['3-3086G4', 'G2412NV'] as $sku) {
        expect($assignments)->not->toHaveKey($sku);
    }
});

it('assigns the confirmed cover and 1637 loading profiles', function (): void {
    $assignments = collect(require dirname(__DIR__, 2).'/database/data/product_loading_profiles.php')
        ->keyBy(fn (array $assignment): string => mb_strtoupper(trim($assignment['sku'])));

    expect($assignments['2-3690G5']['profile_code'])->toBe('garden_crypt_cover_4_high')
        ->and($assignments['2-3086G5']['profile_code'])->toBe('garden_crypt_cover_6_lower_bays')
        ->and($assignments['V1637-1']['profile_code'])->toBe('christy_1637_vault_lower_bays_flatbed')
        ->and($assignments['2-1637V1']['profile_code'])->toBe('christy_1637_cover_4_per_pallet');
});

it('assigns L2472-4 to its confirmed three-high profile', function (): void {
    $assignments = collect(require dirname(__DIR__, 2).'/database/data/product_loading_profiles.php')
        ->keyBy(fn (array $assignment): string => mb_strtoupper(trim($assignment['sku'])));

    expect($assignments['L2472-4']['profile_code'])->toBe('ring_liner_three_high');
});

it('assigns the confirmed P-series pallet capacities', function (): void {
    $assignments = collect(require dirname(__DIR__, 2).'/database/data/product_loading_profiles.php')
        ->keyBy(fn (array $assignment): string => mb_strtoupper(trim($assignment['sku'])));

    foreach (['P300', 'P310', 'P300P', 'P310P', 'P300WS', 'P310WS'] as $sku) {
        expect($assignments[$sku]['profile_code'])->toBe('boxed_urn_products_18_per_pallet');
    }

    foreach (['P400', 'P410', 'P400WS'] as $sku) {
        expect($assignments[$sku]['profile_code'])->toBe('boxed_urn_products_9_per_pallet');
    }
});
