<?php

namespace App\Services;

use App\Enums\OrderStatus;
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

        if ($orderIds->count() < 2) {
            throw ValidationException::withMessages([
                'stops' => 'A delivery trip needs at least two stops.',
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

            foreach ($orders as $order) {
                if ($order->trip_id !== null) {
                    throw ValidationException::withMessages([
                        'stops' => "{$order->order_number} already belongs to a trip.",
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

            $trip = Trip::create([
                'driver_id' => $driverId,
                'status' => 'pending',
                'scheduled_date' => $date,
            ]);

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

            return $trip->load(['driver', 'orders.location']);
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

        if ($orderIds->count() < 2) {
            throw ValidationException::withMessages([
                'stops' => 'A delivery trip needs at least two stops.',
            ]);
        }

        if ($orderIds->unique()->count() !== $orderIds->count()) {
            throw ValidationException::withMessages([
                'stops' => 'Each order can appear only once in a delivery trip.',
            ]);
        }

        $this->availability->validateDate($date, 'scheduled_date');

        return DB::transaction(function () use ($trip, $stops, $orderIds, $date, $driverId): Trip {
            $trip = Trip::query()->lockForUpdate()->findOrFail($trip->getKey());
            $trip->orders()->lockForUpdate()->get();
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

            foreach ($orders as $order) {
                if ($order->trip_id !== null && $order->trip_id !== $trip->getKey()) {
                    throw ValidationException::withMessages([
                        'stops' => "{$order->order_number} already belongs to another trip.",
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
            ]);

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

            return $trip->refresh()->load(['driver', 'orders.location']);
        });
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

            return $trip->refresh()->load(['driver', 'orders.location']);
        });
    }

    public function dissolve(Trip $trip): void
    {
        DB::transaction(function () use ($trip): void {
            $trip = Trip::query()->lockForUpdate()->findOrFail($trip->getKey());

            $trip->orders()->update([
                'trip_id' => null,
                'stop_number' => null,
            ]);

            $trip->delete();
        });
    }
}
