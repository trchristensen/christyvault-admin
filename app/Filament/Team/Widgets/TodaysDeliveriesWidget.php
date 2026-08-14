<?php

namespace App\Filament\Team\Widgets;

use App\Enums\PlantLocation;
use App\Filament\Team\Concerns\ManagesDeliveryPhotos;
use App\Filament\Team\Concerns\ManagesDeliveryTripDispatch;
use App\Filament\Team\Concerns\ManagesTripPreTripInspections;
use App\Filament\Team\Pages\Schedule;
use App\Models\Order;
use App\Models\Trip;
use App\Services\DeliveryCalendarAvailability;
use App\Support\DeliveryOrderWeather;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TodaysDeliveriesWidget extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use ManagesDeliveryPhotos;
    use ManagesDeliveryTripDispatch;
    use ManagesTripPreTripInspections;

    protected string $view = 'filament.team.widgets.todays-deliveries-widget';

    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->canViewTeamDeliverySchedule() ?? false;
    }

    protected function getViewData(): array
    {
        $allowedDeliveryTypes = $this->allowedDeliveryTypes();
        [$today, $nextWorkingDay] = $this->dashboardDeliveryDates();

        $query = Order::query()
            ->whereIn('assigned_delivery_date', [
                $today->toDateString(),
                $nextWorkingDay->toDateString(),
            ])
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
                'trip.vehicleConfiguration',
                'trip.preTripInspections.inspectionDefects',
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

        $currentEmployeeId = auth()->user()?->employee?->getKey();
        $orders = $orders
            ->sortBy(fn (Order $order): int => $order->isAssignedToEmployee($currentEmployeeId) ? 0 : 1)
            ->values();
        $weatherByOrder = app(DeliveryOrderWeather::class)->forOrders($orders);

        $canViewUnprintedProductLines = auth()->user()?->can(Order::VIEW_UNPRINTED_PRODUCT_LINES_PERMISSION) ?? false;

        // Product lines are operational loading instructions. Only load them
        // after tag printing unless the viewer has the explicit bypass permission.
        $orders
            ->filter(fn (Order $order): bool => $order->is_printed || $canViewUnprintedProductLines)
            ->load('orderProducts.product');

        $ordersByDate = $orders->groupBy(
            fn (Order $order): string => $order->assigned_delivery_date?->toDateString() ?? '',
        );
        $pacificNow = now('America/Los_Angeles');

        return [
            'deliveryDays' => collect([
                [
                    'key' => 'today',
                    'label' => 'Today',
                    'heading' => "Today's Deliveries",
                    'empty_message' => 'No deliveries scheduled today.',
                    'date' => $today,
                    'is_today' => true,
                ],
                [
                    'key' => 'next_working_day',
                    'label' => $nextWorkingDay->format('l'),
                    'heading' => $nextWorkingDay->format('l').'’s Deliveries',
                    'empty_message' => 'No deliveries scheduled '.$nextWorkingDay->format('l').'.',
                    'date' => $nextWorkingDay,
                    'is_today' => false,
                ],
            ])->map(function (array $day) use ($ordersByDate): array {
                $dayOrders = $ordersByDate->get($day['date']->toDateString(), collect());

                return [
                    ...$day,
                    'grouped_orders' => $this->groupOrdersByPlant($dayOrders),
                    'total' => $dayOrders->count(),
                ];
            })->all(),
            'initialSlide' => $pacificNow->greaterThanOrEqualTo(
                $pacificNow->copy()->setTime(16, 30),
            ) ? 1 : 0,
            'scheduleUrl' => Schedule::getUrl(panel: 'team'),
            'weatherByOrder' => $weatherByOrder,
        ];
    }

    /**
     * @return array{Carbon, Carbon}
     */
    protected function dashboardDeliveryDates(): array
    {
        $today = now('America/Los_Angeles')->startOfDay();

        return [
            $today,
            app(DeliveryCalendarAvailability::class)->nextOpenDateAfter($today),
        ];
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return Collection<string, Collection<int, Order>>
     */
    protected function groupOrdersByPlant(Collection $orders): Collection
    {
        $currentEmployeeId = auth()->user()?->employee?->getKey();
        $effectivePlant = function (Order $order): string {
            $tripOrders = $order->trip && ! $order->trip->trashed()
                ? $order->trip->orderedDeliveryOrders()
                : collect();

            return (string) ($tripOrders->count() > 1
                ? ($tripOrders->sortBy('stop_number')->first()?->plant_location ?? $order->plant_location)
                : $order->plant_location);
        };

        return collect([
            'colma_main' => $orders->filter(fn (Order $order): bool => $effectivePlant($order) === 'colma_main'),
            'colma_locals' => $orders->filter(fn (Order $order): bool => $effectivePlant($order) === 'colma_locals'),
            'tulare_plant' => $orders->filter(fn (Order $order): bool => $effectivePlant($order) === 'tulare_plant'),
        ])
            ->filter(fn (Collection $group): bool => $group->isNotEmpty())
            ->sortBy(fn (Collection $group): int => $group->contains(
                fn (Order $order): bool => $order->isAssignedToEmployee($currentEmployeeId),
            ) ? 0 : 1);
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
        $visibleDates = collect($this->dashboardDeliveryDates())
            ->map(fn (Carbon $date): string => $date->toDateString());

        if (! $visibleDates->contains($trip->scheduled_date?->toDateString())) {
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

    protected function deliveryTripPreTripInspectionIsInScope(Trip $trip): bool
    {
        return $this->deliveryTripDispatchIsInScope($trip);
    }

    protected function refreshTripPreTripInspectionView(): void {}

    protected function deliveryPhotoOrderIsInScope(Order $order): bool
    {
        if (! static::canView()) {
            return false;
        }

        [$today] = $this->dashboardDeliveryDates();

        if ($order->assigned_delivery_date?->toDateString() !== $today->toDateString()) {
            return false;
        }

        $allowedDeliveryTypes = $this->allowedDeliveryTypes();

        return $allowedDeliveryTypes === []
            || in_array((string) $order->plant_location, $allowedDeliveryTypes, true);
    }

    protected function refreshDeliveryPhotoView(): void {}
}
