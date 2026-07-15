<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Trip;
use App\Services\DeliveryCalendarAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class OrderCalendarController extends Controller
{
    public function events(Request $request, DeliveryCalendarAvailability $availability): JsonResponse
    {
        $start = $request->query('start', now()->startOfMonth()->toDateString());
        $end = $request->query('end', now()->endOfMonth()->toDateString());

        $trips = Trip::query()
            ->with([
                'driver',
                'orders' => fn ($query) => $query->with('location')->orderBy('stop_number'),
                'stops.order.location',
            ])
            ->whereDate('scheduled_date', '>=', $start)
            ->whereDate('scheduled_date', '<=', $end)
            ->get()
            ->filter(fn (Trip $trip): bool => $trip->orderedDeliveryOrders()->count() >= 2);

        $splitLoadOrderIds = $trips
            ->flatMap(fn (Trip $trip): Collection => $trip->orderedDeliveryOrders()->pluck('id'))
            ->all();

        $orders = Order::query()
            ->with('location')
            ->whereNotNull('assigned_delivery_date')
            ->whereDate('assigned_delivery_date', '>=', $start)
            ->whereDate('assigned_delivery_date', '<=', $end)
            ->when($splitLoadOrderIds !== [], fn ($query) => $query->whereNotIn('id', $splitLoadOrderIds))
            ->get();

        $items = collect();

        foreach ($trips as $trip) {
            $tripOrders = $trip->orderedDeliveryOrders();
            $firstOrder = $tripOrders->first();
            $plantLocation = $firstOrder?->plant_location ?? 'colma_main';

            $items->push([
                'date' => $trip->scheduled_date->toDateString(),
                'plant_location' => $plantLocation,
                'event' => [
                    'id' => "trip_{$trip->id}",
                    'title' => 'Delivery Trip',
                    'start' => $trip->scheduled_date->toDateString(),
                    'allDay' => true,
                    'classNames' => ['split-load-event'],
                    'extendedProps' => [
                        'type' => 'split_load',
                        'trip_id' => $trip->id,
                        'trip_number' => $trip->trip_number,
                        'driver_name' => $trip->driver?->name,
                        'status' => str($trip->status)->headline()->toString(),
                        'plant_location' => $plantLocation,
                        'orders' => $tripOrders->map(fn (Order $order, int $index): array => [
                            'id' => $order->id,
                            'stop_number' => $index + 1,
                            'title' => $order->location?->name ?? $order->order_number,
                            'location_line2' => $order->location
                                ? "{$order->location->city}, {$order->location->state}"
                                : '',
                            'status' => OrderStatus::tryFrom($order->status)?->label()
                                ?? str($order->status)->headline()->toString(),
                            'status_raw' => $order->status,
                            'order_number' => $order->order_number,
                        ])->values()->all(),
                    ],
                ],
            ]);
        }

        foreach ($orders as $order) {
            $statusLabel = OrderStatus::tryFrom($order->status)?->label()
                ?? str($order->status)->headline()->toString();
            $plantLocation = $order->plant_location ?? 'colma_main';

            $items->push([
                'date' => $order->assigned_delivery_date->toDateString(),
                'plant_location' => $plantLocation,
                'event' => [
                    'id' => (string) $order->id,
                    'title' => $order->location?->name ?? $order->order_number,
                    'start' => $order->assigned_delivery_date->toDateString(),
                    'allDay' => true,
                    'extendedProps' => [
                        'type' => 'order',
                        'location_line1' => $order->location?->address_line1,
                        'location_line2' => $order->location
                            ? "{$order->location->city}, {$order->location->state}"
                            : '',
                        'status' => $statusLabel,
                        'status_raw' => $order->status,
                        'order_number' => $order->order_number,
                        'requested_delivery_date' => $order->requested_delivery_date,
                        'delivered_at' => $order->delivered_at,
                        'order_date' => $order->order_date,
                        'plant_location' => $plantLocation,
                    ],
                ],
            ]);
        }

        $events = $items
            ->groupBy('date')
            ->flatMap(function (Collection $dateItems): Collection {
                $lastPlantLocation = null;

                return $dateItems
                    ->sortBy(fn (array $item): string => sprintf(
                        '%02d-%d-%s',
                        $this->plantSortOrder($item['plant_location']),
                        ($item['event']['extendedProps']['type'] ?? null) === 'split_load' ? 0 : 1,
                        $item['event']['id'],
                    ))
                    ->values()
                    ->map(function (array $item, int $index) use (&$lastPlantLocation): array {
                        $event = $item['event'];
                        $event['sort_order'] = $index + 1;
                        $event['extendedProps']['sort_order'] = $index + 1;
                        $event['extendedProps']['is_group_start'] = $lastPlantLocation !== $item['plant_location'];
                        $lastPlantLocation = $item['plant_location'];

                        return $event;
                    });
            })
            ->values()
            ->all();

        return response()->json([
            ...$availability->eventsForRange($start, $end),
            ...$events,
        ]);
    }

    public function assignDate(Request $request, DeliveryCalendarAvailability $availability): JsonResponse
    {
        $request->validate([
            'order_id' => ['required'],
            'assigned_delivery_date' => ['required', 'date'],
        ]);

        if ($availability->isBlocked($request->assigned_delivery_date)) {
            return response()->json([
                'success' => false,
                'message' => $availability->blockingReason($request->assigned_delivery_date)
                    ?? 'This day is blocked for delivery.',
            ]);
        }

        if (str_starts_with((string) $request->order_id, 'trip_')) {
            $trip = Trip::findOrFail((int) str($request->order_id)->after('trip_')->toString());
            $trip->update(['scheduled_date' => $request->assigned_delivery_date]);

            return response()->json(['success' => true]);
        }

        $order = Order::with(['location', 'trip'])->findOrFail($request->order_id);

        if ($order->trip && ! $order->trip->trashed() && $order->trip->orders()->count() > 1) {
            return response()->json([
                'success' => false,
                'message' => 'Move the entire delivery trip instead of an individual stop.',
            ]);
        }

        $order->update(['assigned_delivery_date' => $request->assigned_delivery_date]);

        return response()->json(['success' => true]);
    }

    public function unassignDate(Request $request): JsonResponse
    {
        $request->validate(['order_id' => ['required', 'integer']]);

        $order = Order::with(['location', 'trip'])->findOrFail($request->order_id);

        if ($order->trip && ! $order->trip->trashed() && $order->trip->orders()->count() > 1) {
            return response()->json([
                'success' => false,
                'message' => 'Dissolve the delivery trip before unassigning one of its stops.',
            ]);
        }

        $order->update(['assigned_delivery_date' => null]);

        $statusLabel = OrderStatus::tryFrom($order->status)?->label()
            ?? str($order->status)->headline()->toString();

        return response()->json([
            'success' => true,
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $statusLabel,
                'status_raw' => $order->status,
                'location_name' => $order->location?->name,
                'location_city' => $order->location?->city,
                'location_state' => $order->location?->state,
            ],
        ]);
    }

    private function plantSortOrder(?string $plantLocation): int
    {
        return match ($plantLocation) {
            'colma_main' => 1,
            'colma_locals' => 2,
            'tulare_plant' => 3,
            default => 4,
        };
    }
}
