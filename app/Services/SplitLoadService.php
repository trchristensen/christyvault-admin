<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Employee;
use App\Models\Order;
use App\Models\Trip;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SplitLoadService
{
    public function __construct(
        private readonly DeliveryCalendarAvailability $availability,
        private readonly DeliveryTripService $deliveryTrips,
    ) {}

    public function create(
        Order $firstStop,
        Order $secondStop,
        CarbonInterface|string $scheduledDate,
        ?int $driverId = null,
    ): Trip {
        return $this->createTrip([
            ['order_id' => $firstStop->getKey(), 'delivery_notes' => $firstStop->delivery_notes],
            ['order_id' => $secondStop->getKey(), 'delivery_notes' => $secondStop->delivery_notes],
        ], $scheduledDate, $driverId);
    }

    /**
     * @param  array<int, array{order_id: int|string, delivery_notes?: string|null}>  $stops
     */
    public function createTrip(
        array $stops,
        CarbonInterface|string $scheduledDate,
        ?int $driverId = null,
    ): Trip {
        $date = Carbon::parse($scheduledDate)->toDateString();
        $stops = collect($stops)->values();
        $orderIds = $stops->pluck('order_id')->filter()->values();

        if ($orderIds->isEmpty()) {
            throw ValidationException::withMessages([
                'stops' => 'A delivery trip needs at least one stop.',
            ]);
        }

        if ($orderIds->unique()->count() !== $orderIds->count()) {
            throw ValidationException::withMessages([
                'stops' => 'Each order can appear only once in a delivery trip.',
            ]);
        }

        $this->availability->validateDate($date, 'scheduled_date');

        return DB::transaction(function () use ($stops, $orderIds, $date, $driverId): Trip {
            $orders = Order::query()
                ->whereKey($orderIds)
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (Order $order): int|string => $order->getKey());

            if ($orders->count() !== $orderIds->count()) {
                throw ValidationException::withMessages([
                    'stops' => 'One or more selected orders could not be found.',
                ]);
            }

            $sourceTrips = Trip::query()
                ->whereKey($orders->pluck('trip_id')->filter()->unique())
                ->withCount('orders')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($orders as $order) {
                $sourceTrip = $sourceTrips->get($order->trip_id);

                if ($sourceTrip && $sourceTrip->orders_count > 1) {
                    throw ValidationException::withMessages([
                        'stops' => "{$order->order_number} already belongs to a multi-stop trip.",
                    ]);
                }

                if (in_array($order->status, [
                    OrderStatus::DELIVERED->value,
                    OrderStatus::CANCELLED->value,
                    OrderStatus::COMPLETED->value,
                ], true)) {
                    throw ValidationException::withMessages([
                        'stops' => "{$order->order_number} can no longer be placed in a delivery trip.",
                    ]);
                }
            }

            $existingDrivers = $orders->pluck('driver_id')
                ->filter()
                ->unique()
                ->values();

            if ($driverId === null && $existingDrivers->count() > 1) {
                throw ValidationException::withMessages([
                    'driver_id' => 'These orders have different drivers. Choose the driver for the delivery trip.',
                ]);
            }

            $driverId ??= $existingDrivers->first();

            $preferredTripId = $orders->get($orderIds->first())?->trip_id;
            $trip = $sourceTrips->get($preferredTripId) ?? $sourceTrips->first();

            if ($trip) {
                $trip->update([
                    'driver_id' => $driverId,
                    'scheduled_date' => $date,
                    'dispatch_confirmed_at' => null,
                    'dispatch_confirmed_by_user_id' => null,
                ]);
            } else {
                $trip = Trip::create([
                    'driver_id' => $driverId,
                    'status' => 'pending',
                    'scheduled_date' => $date,
                ]);
            }

            Order::query()->whereKey($orderIds)->update(['stop_number' => null]);
            $orders = Order::query()->whereKey($orderIds)->get()->keyBy(
                fn (Order $order): int|string => $order->getKey(),
            );

            foreach ($stops as $index => $stop) {
                $order = $orders->get($stop['order_id']);

                $order->update([
                    'trip_id' => $trip->getKey(),
                    'stop_number' => $index + 1,
                    'assigned_delivery_date' => $date,
                    'driver_id' => $driverId,
                    'delivery_notes' => $stop['delivery_notes'] ?? $order->delivery_notes,
                ]);
            }

            $this->deliveryTrips->syncStopsFromLegacyOrders($trip);

            foreach ($sourceTrips->except($trip->getKey()) as $sourceTrip) {
                $this->deliveryTrips->syncStopsFromLegacyOrders($sourceTrip);
                $sourceTrip->delete();
            }

            return $trip->refresh()->load(['driver', 'orders.location', 'stops.order']);
        });
    }

    /**
     * @param  array<int, array{order_id: int|string, delivery_notes?: string|null}>  $stops
     */
    public function updateTrip(
        Trip $trip,
        array $stops,
        CarbonInterface|string $scheduledDate,
        ?int $driverId = null,
    ): Trip {
        $date = Carbon::parse($scheduledDate)->toDateString();
        $stops = collect($stops)->values();
        $orderIds = $stops->pluck('order_id')->filter()->values();

        if ($orderIds->isEmpty()) {
            throw ValidationException::withMessages([
                'stops' => 'A delivery trip needs at least one stop.',
            ]);
        }

        if ($orderIds->unique()->count() !== $orderIds->count()) {
            throw ValidationException::withMessages([
                'stops' => 'Each order can appear only once in a delivery trip.',
            ]);
        }

        $this->availability->validateDate($date, 'scheduled_date');

        $removedOrderIds = [];

        $trip = DB::transaction(function () use ($trip, $stops, $orderIds, $date, $driverId, &$removedOrderIds): Trip {
            $trip = Trip::query()->lockForUpdate()->findOrFail($trip->getKey());
            $existingOrders = $trip->orders()->lockForUpdate()->get();
            $orders = Order::query()
                ->whereKey($orderIds)
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (Order $order): int|string => $order->getKey());

            if ($orders->count() !== $orderIds->count()) {
                throw ValidationException::withMessages([
                    'stops' => 'One or more selected orders could not be found.',
                ]);
            }

            $sourceTrips = Trip::query()
                ->whereKey($orders->pluck('trip_id')->filter()->unique()->reject(
                    fn ($tripId): bool => (int) $tripId === (int) $trip->getKey(),
                ))
                ->withCount('orders')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($orders as $order) {
                $sourceTrip = $sourceTrips->get($order->trip_id);

                if ($sourceTrip && $sourceTrip->orders_count > 1) {
                    throw ValidationException::withMessages([
                        'stops' => "{$order->order_number} already belongs to another multi-stop trip.",
                    ]);
                }

                if ($order->trip_id !== $trip->getKey() && in_array($order->status, [
                    OrderStatus::DELIVERED->value,
                    OrderStatus::CANCELLED->value,
                    OrderStatus::COMPLETED->value,
                ], true)) {
                    throw ValidationException::withMessages([
                        'stops' => "{$order->order_number} can no longer be placed in a delivery trip.",
                    ]);
                }
            }

            $existingDrivers = $orders->pluck('driver_id')
                ->filter()
                ->unique()
                ->values();

            if ($driverId === null && $existingDrivers->count() > 1) {
                throw ValidationException::withMessages([
                    'driver_id' => 'These orders have different drivers. Choose the driver for the delivery trip.',
                ]);
            }

            $driverId ??= $trip->driver_id ?? $existingDrivers->first();

            $removedOrderIds = $existingOrders
                ->whereNotIn('id', $orderIds)
                ->pluck('id')
                ->all();

            $trip->orders()
                ->whereNotIn('id', $orderIds)
                ->update([
                    'trip_id' => null,
                    'stop_number' => null,
                ]);

            $trip->orders()
                ->whereIn('id', $orderIds)
                ->update(['stop_number' => null]);

            $trip->update([
                'driver_id' => $driverId,
                'scheduled_date' => $date,
                'dispatch_confirmed_at' => null,
                'dispatch_confirmed_by_user_id' => null,
            ]);
            $orders = Order::query()->whereKey($orderIds)->get()->keyBy(
                fn (Order $order): int|string => $order->getKey(),
            );

            foreach ($stops as $index => $stop) {
                $order = $orders->get($stop['order_id']);

                $order->update([
                    'trip_id' => $trip->getKey(),
                    'stop_number' => $index + 1,
                    'assigned_delivery_date' => $date,
                    'driver_id' => $driverId,
                    'delivery_notes' => $stop['delivery_notes'] ?? $order->delivery_notes,
                ]);
            }

            $this->deliveryTrips->syncStopsFromLegacyOrders($trip);

            foreach ($sourceTrips as $sourceTrip) {
                $this->deliveryTrips->syncStopsFromLegacyOrders($sourceTrip);
                $sourceTrip->delete();
            }

            return $trip->refresh()->load(['driver', 'orders.location', 'stops.order']);
        });

        foreach (Order::query()->whereKey($removedOrderIds)->get() as $removedOrder) {
            $this->deliveryTrips->ensureScheduledOrderHasTrip($removedOrder);
        }

        return $trip->refresh()->load(['driver', 'orders.location', 'stops.order']);
    }

    public function reverse(Trip $trip): Trip
    {
        return DB::transaction(function () use ($trip): Trip {
            $trip = Trip::query()->lockForUpdate()->findOrFail($trip->getKey());
            $orders = $trip->orders()->orderBy('stop_number')->lockForUpdate()->get();

            if ($orders->count() < 2) {
                throw ValidationException::withMessages([
                    'trip' => 'A delivery trip needs at least two stops to be reordered.',
                ]);
            }

            foreach ($orders as $index => $order) {
                DB::table('orders')->where('id', $order->id)->update(['stop_number' => -($index + 1)]);
            }

            foreach ($orders->reverse()->values() as $index => $order) {
                DB::table('orders')->where('id', $order->id)->update(['stop_number' => $index + 1]);
            }

            $this->deliveryTrips->syncStopsFromLegacyOrders($trip);
            $this->deliveryTrips->invalidateStopOrderConfirmation($trip);

            return $trip->refresh()->load(['driver', 'orders.location', 'stops.order']);
        });
    }

    /**
     * @param  array<int, int|string>  $orderedOrderIds
     */
    public function updateDispatchPlan(Trip $trip, array $orderedOrderIds, ?int $driverId): Trip
    {
        $orderedOrderIds = collect($orderedOrderIds)->map(fn ($id): int => (int) $id)->values();

        if ($orderedOrderIds->isEmpty() || $orderedOrderIds->unique()->count() !== $orderedOrderIds->count()) {
            throw ValidationException::withMessages([
                'stops' => 'The delivery trip must contain each of its stops exactly once.',
            ]);
        }

        if ($driverId !== null && ! Employee::query()
            ->whereKey($driverId)
            ->where('is_active', true)
            ->whereHas('positions', fn ($query) => $query->where('name', 'driver'))
            ->exists()) {
            throw ValidationException::withMessages([
                'driver_id' => 'Choose an active employee with the driver position.',
            ]);
        }

        return DB::transaction(function () use ($trip, $orderedOrderIds, $driverId): Trip {
            $trip = Trip::query()->lockForUpdate()->findOrFail($trip->getKey());
            $orders = $trip->orders()->orderBy('stop_number')->lockForUpdate()->get();
            $currentOrderIds = $orders->pluck('id')->map(fn ($id): int => (int) $id)->values();

            if ($currentOrderIds->sort()->values()->all() !== $orderedOrderIds->sort()->values()->all()) {
                throw ValidationException::withMessages([
                    'stops' => 'The trip changed while you were editing it. Close the modal and try again.',
                ]);
            }

            $oldDriverId = $trip->driver_id;
            $oldStopOrder = $currentOrderIds->all();
            $wasConfirmed = $trip->dispatch_confirmed_at !== null;

            $trip->orders()->update(['stop_number' => null]);
            $trip->update([
                'driver_id' => $driverId,
                'dispatch_confirmed_at' => now(),
                'dispatch_confirmed_by_user_id' => auth()->id(),
            ]);

            foreach ($orderedOrderIds as $index => $orderId) {
                DB::table('orders')->where('id', $orderId)->update([
                    'stop_number' => $index + 1,
                    'driver_id' => $driverId,
                    'updated_at' => now(),
                ]);
            }

            $this->deliveryTrips->syncStopsFromLegacyOrders($trip);

            if (! $wasConfirmed || $oldDriverId !== $driverId || $oldStopOrder !== $orderedOrderIds->all()) {
                activity('trip')
                    ->performedOn($trip)
                    ->causedBy(auth()->user())
                    ->event('dispatch_updated')
                    ->withProperties([
                        'old' => [
                            'driver_id' => $oldDriverId,
                            'stop_order' => $oldStopOrder,
                        ],
                        'attributes' => [
                            'driver_id' => $driverId,
                            'stop_order' => $orderedOrderIds->all(),
                            'dispatch_confirmed_at' => $trip->dispatch_confirmed_at,
                        ],
                    ])
                    ->log('delivery trip dispatch updated');
            }

            return $trip->refresh()->load(['driver', 'orders.location', 'stops.order']);
        });
    }

    public function dissolve(Trip $trip): void
    {
        $orderIds = DB::transaction(function () use ($trip): array {
            $trip = Trip::query()->lockForUpdate()->findOrFail($trip->getKey());
            $orderIds = $trip->orders()->lockForUpdate()->pluck('id')->all();

            $trip->orders()->update([
                'trip_id' => null,
                'stop_number' => null,
            ]);

            $this->deliveryTrips->retireStops($trip);
            $trip->delete();

            return $orderIds;
        });

        foreach (Order::query()->whereKey($orderIds)->get() as $order) {
            $this->deliveryTrips->ensureScheduledOrderHasTrip($order);
        }
    }
}
