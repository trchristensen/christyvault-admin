<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Trip;
use App\Models\TripStop;
use Illuminate\Support\Facades\DB;

class DeliveryTripService
{
    public function __construct(
        private readonly TripVehicleConfigurationResolver $vehicleConfigurations,
    ) {}

    /**
     * These statuses do not represent a routed delivery.
     */
    private const NON_ROUTE_STATUSES = [
        OrderStatus::WILL_CALL->value,
        OrderStatus::PICKED_UP->value,
        OrderStatus::SHIPPED->value,
        OrderStatus::CANCELLED->value,
    ];

    public static function nonRouteStatuses(): array
    {
        return self::NON_ROUTE_STATUSES;
    }

    public function shouldHaveTrip(Order $order): bool
    {
        return $order->assigned_delivery_date !== null
            && ! in_array($order->status, self::NON_ROUTE_STATUSES, true);
    }

    public function ensureScheduledOrderHasTrip(Order $order): ?Trip
    {
        if (! $this->shouldHaveTrip($order)) {
            return null;
        }

        return DB::transaction(function () use ($order): Trip {
            $order = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $order->loadMissing('location');
            $trip = $order->trip;

            if ($trip) {
                $this->syncStopsFromLegacyOrders($trip);

                return $trip->fresh(['driver', 'stops.order']);
            }

            $trip = Trip::create([
                'driver_id' => $order->driver_id,
                'vehicle_configuration_id' => $this->vehicleConfigurations
                    ->defaultForOrders([$order])?->getKey(),
                'status' => 'pending',
                'scheduled_date' => $order->assigned_delivery_date,
            ]);

            $order->update([
                'trip_id' => $trip->getKey(),
                'stop_number' => 1,
                'driver_id' => $trip->driver_id,
            ]);

            $this->syncStopsFromLegacyOrders($trip);

            return $trip->fresh(['driver', 'stops.order']);
        });
    }

    /**
     * Synchronize a single-order schedule edit back to its trip. Multi-stop trips
     * remain authoritative and restore their date/driver onto the edited order.
     */
    public function synchronizeOrderSchedule(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $order = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $trip = $order->trip;

            if (! $trip) {
                if ($this->shouldHaveTrip($order)) {
                    $this->ensureScheduledOrderHasTrip($order);
                }

                return;
            }

            $orders = $trip->orders()->orderBy('stop_number')->lockForUpdate()->get();

            if ($orders->count() === 1 && ! $this->shouldHaveTrip($order)) {
                $this->retireStops($trip);
                $order->updateQuietly([
                    'trip_id' => null,
                    'stop_number' => null,
                ]);
                $trip->delete();

                return;
            }

            if ($orders->count() === 1) {
                $trip->update([
                    'scheduled_date' => $order->assigned_delivery_date,
                    'driver_id' => $order->driver_id,
                ]);
                $this->syncStopsFromLegacyOrders($trip);

                return;
            }

            $stop = $trip->stops()->where('order_id', $order->getKey())->first();

            $order->updateQuietly([
                'assigned_delivery_date' => $trip->scheduled_date,
                'driver_id' => $trip->driver_id,
                'stop_number' => $stop?->sequence ?? $order->stop_number,
            ]);
        });
    }

    public function synchronizeLegacyMembershipChange(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $tripIds = collect([
                $order->getOriginal('trip_id'),
                $order->trip_id,
            ])->filter()->unique()->values();

            foreach (Trip::query()->whereKey($tripIds)->lockForUpdate()->get() as $trip) {
                $this->syncStopsFromLegacyOrders($trip);

                if ($order->wasChanged(['trip_id', 'stop_number'])) {
                    $this->invalidateStopOrderConfirmation($trip);
                }

                if (! $trip->orders()->exists()) {
                    $trip->delete();
                }
            }

            if ($order->trip_id === null && $this->shouldHaveTrip($order)) {
                $this->ensureScheduledOrderHasTrip($order);
            }
        });
    }

    /**
     * Copy the legacy relationship into trip_stops. This is idempotent and never
     * deletes a stop row; stops no longer on the route receive removed_at.
     */
    public function syncStopsFromLegacyOrders(Trip $trip): void
    {
        $orders = $trip->orders()->orderBy('stop_number')->get();
        $activeOrderIds = $orders->pluck('id')->all();

        $removedQuery = TripStop::query()
            ->where('trip_id', $trip->getKey())
            ->whereNull('removed_at');

        if ($activeOrderIds === []) {
            $removedQuery->update([
                'sequence' => null,
                'removed_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $removedQuery
                ->whereNotIn('order_id', $activeOrderIds)
                ->update([
                    'sequence' => null,
                    'removed_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        TripStop::query()
            ->where('trip_id', $trip->getKey())
            ->whereNull('removed_at')
            ->whereIn('order_id', $activeOrderIds)
            ->update([
                'sequence' => null,
                'updated_at' => now(),
            ]);

        foreach ($orders as $index => $order) {
            $stop = TripStop::query()
                ->where('trip_id', $trip->getKey())
                ->where('order_id', $order->getKey())
                ->whereNull('removed_at')
                ->first();

            if (! $stop) {
                $stop = new TripStop([
                    'trip_id' => $trip->getKey(),
                    'order_id' => $order->getKey(),
                ]);
            }

            $stop->fill([
                'sequence' => $index + 1,
                'delivery_notes' => $order->delivery_notes,
                'removed_at' => null,
            ])->save();
        }

        $trip->unsetRelation('stops');
    }

    public function retireStops(Trip $trip): void
    {
        TripStop::query()
            ->where('trip_id', $trip->getKey())
            ->whereNull('removed_at')
            ->update([
                'sequence' => null,
                'removed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function invalidateStopOrderConfirmation(Trip $trip): void
    {
        if ($trip->dispatch_confirmed_at === null && $trip->dispatch_confirmed_by_user_id === null) {
            return;
        }

        $trip->updateQuietly([
            'dispatch_confirmed_at' => null,
            'dispatch_confirmed_by_user_id' => null,
        ]);
    }
}
