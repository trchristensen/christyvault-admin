<?php

namespace App\Filament\Team\Widgets;

use App\Enums\PlantLocation;
use App\Filament\Team\Pages\Schedule;
use App\Models\Order;
use Filament\Widgets\Widget;

class TodaysDeliveriesWidget extends Widget
{
    protected string $view = 'filament.team.widgets.todays-deliveries-widget';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('view team delivery schedule') ?? false;
    }

    protected function getViewData(): array
    {
        $allowedDeliveryTypes = collect(auth()->user()?->team_schedule_delivery_types ?? [])
            ->filter(fn ($type): bool => PlantLocation::tryFrom((string) $type) !== null)
            ->values()
            ->all();

        $query = Order::query()
            ->whereDate('assigned_delivery_date', today())
            ->when(
                $allowedDeliveryTypes !== [],
                fn ($query) => $query->whereIn('plant_location', $allowedDeliveryTypes),
            );

        $orders = $query
            ->with(['location', 'driver', 'trip.driver', 'trip.orders:id,trip_id,plant_location,stop_number', 'orderProducts.product'])
            ->withCount('deliveryPhotos')
            ->orderByRaw("CASE plant_location
                WHEN 'colma_main' THEN 1
                WHEN 'colma_locals' THEN 2
                WHEN 'tulare_plant' THEN 3
                ELSE 4
            END")
            ->orderByRaw('CASE WHEN trip_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('trip_id')
            ->orderBy('stop_number')
            ->orderBy('delivery_time')
            ->orderBy('id')
            ->get();

        $effectivePlant = function (Order $order): string {
            $tripOrders = $order->trip && ! $order->trip->trashed()
                ? $order->trip->orders
                : collect();

            return (string) ($tripOrders->count() > 1
                ? ($tripOrders->sortBy('stop_number')->first()?->plant_location ?? $order->plant_location)
                : $order->plant_location);
        };

        $groupedOrders = collect([
            'colma_main' => $orders->filter(fn (Order $order): bool => $effectivePlant($order) === 'colma_main'),
            'colma_locals' => $orders->filter(fn (Order $order): bool => $effectivePlant($order) === 'colma_locals'),
            'tulare_plant' => $orders->filter(fn (Order $order): bool => $effectivePlant($order) === 'tulare_plant'),
        ])->filter(fn ($group) => $group->isNotEmpty());

        return [
            'groupedOrders' => $groupedOrders,
            'total' => $orders->count(),
            'scheduleUrl' => Schedule::getUrl(panel: 'team'),
        ];
    }
}
