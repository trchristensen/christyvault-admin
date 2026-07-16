<?php

use App\Models\Employee;
use App\Models\Order;
use App\Models\Trip;
use Illuminate\Support\Facades\Blade;

it('shows the driver assigned directly to an order', function (): void {
    $order = new Order;
    $order->setRelation('driver', (new Employee)->forceFill(['name' => 'Taylor Driver']));
    $order->setRelation('trip', null);

    $html = Blade::render('<x-order-assigned-driver :order="$order" />', compact('order'));

    expect($html)
        ->toContain('Assigned Driver')
        ->toContain('Taylor Driver');
});

it('falls back to the delivery trip driver and shows unassigned when neither exists', function (): void {
    $trip = new Trip;
    $trip->setRelation('driver', (new Employee)->forceFill(['name' => 'Jordan Driver']));

    $tripOrder = new Order;
    $tripOrder->setRelation('driver', null);
    $tripOrder->setRelation('trip', $trip);

    $unassignedOrder = new Order;
    $unassignedOrder->setRelation('driver', null);
    $unassignedOrder->setRelation('trip', null);

    expect(Blade::render('<x-order-assigned-driver :order="$order" />', ['order' => $tripOrder]))
        ->toContain('Jordan Driver')
        ->and(Blade::render('<x-order-assigned-driver :order="$order" />', ['order' => $unassignedOrder]))
        ->toContain('Unassigned');
});
