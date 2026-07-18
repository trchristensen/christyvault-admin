<?php

namespace App\Services;

use App\Models\Order;
use App\Models\VehicleConfiguration;
use App\Support\DeliveryArea;
use Illuminate\Support\Collection;

final class TripVehicleConfigurationResolver
{
    /**
     * @param  iterable<Order>  $orders
     */
    public function defaultForOrders(iterable $orders): ?VehicleConfiguration
    {
        $orders = collect($orders)
            ->filter(fn ($order): bool => $order instanceof Order)
            ->values();

        $configurationCode = $orders->isNotEmpty()
            && $orders->every(fn (Order $order): bool => DeliveryArea::isLocalOrder($order))
                ? VehicleConfiguration::CODE_BOOM_TRUCK
                : VehicleConfiguration::CODE_RACK_TRAILER_FORKLIFT_ONBOARD;

        return VehicleConfiguration::query()
            ->where('code', $configurationCode)
            ->where('is_active', true)
            ->first();
    }

    /**
     * @param  iterable<int|string|null>  $orderIds
     */
    public function defaultIdForOrderIds(iterable $orderIds): ?int
    {
        $orderIds = Collection::make($orderIds)->filter()->unique()->values();

        $orders = Order::query()
            ->with('location')
            ->whereKey($orderIds)
            ->get();

        return $this->defaultForOrders($orders)?->getKey();
    }
}
