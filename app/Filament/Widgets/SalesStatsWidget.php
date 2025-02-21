<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesStatsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '15s';

    protected function getStats(): array
    {
        $today = now()->startOfDay();
        $thisMonth = now()->startOfMonth();
        
        return [
            Stat::make('Today\'s Sales', Order::whereDate('created_at', $today)->count())
                ->description('Total orders today')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->chart([7, 3, 4, 5, 6, 3, 5, 3])
                ->color('success'),

            Stat::make('This Month', Order::whereMonth('created_at', $thisMonth->month)
                    ->whereYear('created_at', $thisMonth->year)
                    ->count())
                ->description('Total orders this month')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->chart([17, 16, 14, 15, 14, 13, 12, 21])
                ->color('info'),

            Stat::make('Average Order Value', '$' . number_format(Order::avg('total') ?? 0, 2))
                ->description('Per order average')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->chart([15, 8, 12, 9, 13, 10, 15, 14])
                ->color('warning'),
        ];
    }
} 