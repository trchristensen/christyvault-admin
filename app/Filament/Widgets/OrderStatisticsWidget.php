<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OrderStatisticsWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = [
        'default' => 1,  // Full width on mobile
        'sm' => 2,       // Full width on small screens (2/2 columns)
        'md' => 3,       // Full width on medium screens (3/3 columns) 
        'lg' => 4,       // Full width on large screens (4/4 columns)
        'xl' => 4,       // 4/6 width on extra large screens
        '2xl' => 6,      // 6/8 width on 2xl screens
    ];

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
