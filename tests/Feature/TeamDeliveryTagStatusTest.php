<?php

use App\Enums\PlantLocation;
use App\Models\Order;
use Illuminate\Support\Facades\Blade;

function renderTeamDeliveryTagStatus(string $plantLocation, bool $isPrinted): string
{
    $order = (new Order)->forceFill([
        'id' => 42,
        'status' => 'approved',
        'plant_location' => $plantLocation,
        'is_printed' => $isPrinted,
        'delivery_photos_count' => 0,
    ]);
    $order->setRelation('location', null);
    $order->setRelation('driver', null);
    $order->setRelation('trip', null);
    $order->setRelation('activeTripStop', null);

    return Blade::render(
        '<x-delivery-order-summary :order="$order" :show-photo-action="false" />',
        compact('order'),
    );
}

it('hides the unprinted tag warning for Tulare deliveries', function () {
    $html = renderTeamDeliveryTagStatus(PlantLocation::TULARE_PLANT->value, false);

    expect($html)
        ->not->toContain('Tag not printed')
        ->not->toContain('delivery-tag-not-printed');
});

it('keeps the unprinted tag warning for Colma deliveries', function () {
    $html = renderTeamDeliveryTagStatus(PlantLocation::COLMA_MAIN->value, false);

    expect($html)
        ->toContain('Tag not printed')
        ->toContain('delivery-tag-not-printed');
});

it('still shows a printed tag status for Tulare deliveries when present', function () {
    $html = renderTeamDeliveryTagStatus(PlantLocation::TULARE_PLANT->value, true);

    expect($html)
        ->toContain('Tag printed')
        ->toContain('delivery-tag-printed');
});
