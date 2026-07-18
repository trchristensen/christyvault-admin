<?php

it('contains valid and unambiguous catalog product weight mappings', function () {
    $rows = require dirname(__DIR__, 2).'/database/data/product_weights_2025.php';
    $claimedSkus = [];

    foreach ($rows as $row) {
        expect($row)
            ->toHaveKeys(['catalog_sku', 'page'])
            ->and($row['catalog_sku'])->toBeString()->not->toBeEmpty()
            ->and($row['page'])->toBeInt()->toBeGreaterThan(0);

        expect(isset($row['weight_lbs']) xor isset($row['review']))->toBeTrue();

        if (isset($row['weight_lbs'])) {
            expect($row['weight_lbs'])->toBeNumeric()->toBeGreaterThan(0);
        } else {
            expect($row['review'])->toBeString()->not->toBeEmpty();
        }

        foreach ([$row['catalog_sku'], ...($row['aliases'] ?? [])] as $sku) {
            $normalizedSku = mb_strtoupper(trim($sku));

            expect($claimedSkus)->not->toHaveKey($normalizedSku);
            $claimedSkus[$normalizedSku] = $row['catalog_sku'];
        }
    }
});

it('keeps internally inconsistent printed totals out of the import', function () {
    $rows = collect(require dirname(__DIR__, 2).'/database/data/product_weights_2025.php')
        ->keyBy('catalog_sku');

    foreach (['P400', 'P400WS', 'G3690-6'] as $sku) {
        expect($rows[$sku])
            ->toHaveKey('review')
            ->not->toHaveKey('weight_lbs');
    }
});
