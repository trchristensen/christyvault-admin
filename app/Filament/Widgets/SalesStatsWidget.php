<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class SalesStatsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '15s';

    protected function getStats(): array
    {
        $today = now()->startOfDay();
        $thisMonth = now()->startOfMonth();

        // Calculate average order value from order_products
        $averageOrderValue = Order::query()
            ->join('order_products', 'orders.id', '=', 'order_products.order_id')
            ->select(DB::raw('COALESCE(AVG(order_products.quantity * order_products.price), 0) as average_value'))
            ->first()
            ->average_value;

        return [
            StatsOverviewWidget\Stat::make('Total orders this month')
                ->value(Order::count())
                ->description('Total orders')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->chart([17, 16, 14, 15, 14, 13, 12, 21])
                ->color('info'),

            StatsOverviewWidget\Stat::make('Average Order Value')
                ->value('$0.00')
                ->description('Per order average')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->chart([15, 8, 12, 9, 13, 10, 15, 14])
                ->color('warning'),
        ];
    }
}
