<?php

use App\Support\DeliveryArea;

it('recognizes the local delivery cities', function (string $city): void {
    expect(DeliveryArea::isLocalCity($city))->toBeTrue();
})->with([
    'Colma' => 'Colma',
    'South San Francisco' => 'South San Francisco',
    'case and whitespace' => '  SOUTH SAN FRANCISCO  ',
]);

it('does not classify other or missing cities as local', function (?string $city): void {
    expect(DeliveryArea::isLocalCity($city))->toBeFalse();
})->with([
    'Fresno' => 'Fresno',
    'San Francisco' => 'San Francisco',
    'blank' => '',
    'missing' => null,
]);
