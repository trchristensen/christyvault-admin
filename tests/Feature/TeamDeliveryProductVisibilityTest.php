<?php

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Blade;

function teamDeliveryOrderWithProductLines(bool $isPrinted): Order
{
    $product = (new Product)->forceFill([
        'sku' => 'SECRET-SKU',
        'name' => 'Secret loading product',
    ]);
    $orderProduct = (new OrderProduct)->forceFill([
        'quantity' => 4,
        'notes' => 'Secret loading notes',
    ]);
    $orderProduct->setRelation('product', $product);

    $order = (new Order)->forceFill(['is_printed' => $isPrinted]);
    $order->setRelation('orderProducts', collect([$orderProduct]));

    return $order;
}

it('hides team delivery product lines until the delivery tag is printed', function () {
    $order = teamDeliveryOrderWithProductLines(false);

    $html = Blade::render(
        '<x-delivery-order-products :order="$order" />',
        compact('order'),
    );

    expect($html)
        ->toContain('Waiting for delivery tag')
        ->toContain('Do not pull or load materials')
        ->not->toContain('SECRET-SKU')
        ->not->toContain('Secret loading product')
        ->not->toContain('Secret loading notes');
});

it('shows team delivery product lines after the delivery tag is printed', function () {
    $order = teamDeliveryOrderWithProductLines(true);

    $html = Blade::render(
        '<x-delivery-order-products :order="$order" />',
        compact('order'),
    );

    expect($html)
        ->toContain('SECRET-SKU')
        ->toContain('Secret loading product')
        ->toContain('Secret loading notes')
        ->not->toContain('Waiting for delivery tag');
});

it('shows unprinted team delivery product lines to viewers with the bypass permission', function () {
    $user = new class extends User
    {
        public function can($abilities, $arguments = []): bool
        {
            return $abilities === Order::VIEW_UNPRINTED_PRODUCT_LINES_PERMISSION;
        }
    };
    auth()->setUser($user);
    $order = teamDeliveryOrderWithProductLines(false);

    $html = Blade::render(
        '<x-delivery-order-products :order="$order" />',
        compact('order'),
    );

    expect($html)
        ->toContain('SECRET-SKU')
        ->toContain('Secret loading product')
        ->toContain('Secret loading notes')
        ->not->toContain('Waiting for delivery tag');
});
