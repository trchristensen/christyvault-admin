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

        $total = (clone $query)->count();
        $orders = $query
            ->with(['location', 'driver', 'orderProducts.product'])
            ->orderByRaw("CASE plant_location
                WHEN 'colma_main' THEN 1
                WHEN 'colma_locals' THEN 2
                WHEN 'tulare_plant' THEN 3
                ELSE 4
            END")
            ->orderBy('delivery_time')
            ->orderBy('id')
            ->limit(5)
            ->get();

        return [
            'orders' => $orders,
            'total' => $total,
            'scheduleUrl' => Schedule::getUrl(panel: 'team'),
        ];
    }
}
