<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OrderStatisticsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Orders', Order::count()),
            Stat::make('Pending Orders', Order::where('status', 'pending')->count()),
            Stat::make('Completed Orders', Order::where('status', 'delivered')->count()),
            Stat::make('Cancelled Orders', Order::where('status', 'cancelled')->count()),
        ];
    }
}
