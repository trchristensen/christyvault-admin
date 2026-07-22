<?php

namespace App\Filament\Team\Widgets;

use App\Enums\PlantLocation;
use App\Filament\Team\Concerns\ManagesDeliveryPhotos;
use App\Filament\Team\Concerns\ManagesDeliveryTripDispatch;
use App\Filament\Team\Pages\Schedule;
use App\Models\Order;
use App\Models\Trip;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;

class TodaysDeliveriesWidget extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use ManagesDeliveryPhotos;
    use ManagesDeliveryTripDispatch;

    protected string $view = 'filament.team.widgets.todays-deliveries-widget';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('view team delivery schedule') ?? false;
    }

    protected function getViewData(): array
    {
        $allowedDeliveryTypes = $this->allowedDeliveryTypes();

        $query = Order::query()
            ->whereDate('assigned_delivery_date', today())
            ->when(
                $allowedDeliveryTypes !== [],
                fn ($query) => $query->whereIn('plant_location', $allowedDeliveryTypes),
            );

        $orders = $query
            ->with([
                'location',
                'driver',
                'activeTripStop',
                'trip.driver',
                'trip.orders:id,trip_id,plant_location,stop_number,is_printed',
                'trip.stops.order:id,plant_location,is_printed',
            ])
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

        $canViewUnprintedProductLines = auth()->user()?->can(Order::VIEW_UNPRINTED_PRODUCT_LINES_PERMISSION) ?? false;

        // Product lines are operational loading instructions. Only load them
        // after tag printing unless the viewer has the explicit bypass permission.
        $orders
            ->filter(fn (Order $order): bool => $order->is_printed || $canViewUnprintedProductLines)
            ->load('orderProducts.product');

        $effectivePlant = function (Order $order): string {
            $tripOrders = $order->trip && ! $order->trip->trashed()
                ? $order->trip->orderedDeliveryOrders()
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

    protected function allowedDeliveryTypes(): array
    {
        return collect(auth()->user()?->team_schedule_delivery_types ?? [])
            ->filter(fn ($type): bool => PlantLocation::tryFrom((string) $type) !== null)
            ->values()
            ->all();
    }

    protected function deliveryTripDispatchIsInScope(Trip $trip): bool
    {
        if ($trip->scheduled_date?->toDateString() !== today()->toDateString()) {
            return false;
        }

        $allowedDeliveryTypes = $this->allowedDeliveryTypes();

        return $allowedDeliveryTypes === []
            || $trip->orderedDeliveryOrders()->every(fn (Order $order): bool => in_array(
                (string) $order->plant_location,
                $allowedDeliveryTypes,
                true,
            ));
    }

    protected function refreshDeliveryTripDispatchView(): void {}

    protected function deliveryPhotoOrderIsInScope(Order $order): bool
    {
        if (! static::canView()) {
            return false;
        }

        if (! $order->assigned_delivery_date || ! $order->assigned_delivery_date->isToday()) {
            return false;
        }

        $allowedDeliveryTypes = $this->allowedDeliveryTypes();

        return $allowedDeliveryTypes === []
            || in_array((string) $order->plant_location, $allowedDeliveryTypes, true);
    }

    protected function refreshDeliveryPhotoView(): void {}
}
