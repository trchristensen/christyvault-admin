<?php

namespace App\Filament\Operations\Widgets;

use App\Models\InventoryItem;
use App\Models\KanbanCard;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class InventoryStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Low Stock Items', 
                InventoryItem::query()
                    ->whereColumn('current_stock', '<=', 'minimum_stock')
                    ->count())
                ->description('Items below minimum stock level')
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color('danger'),
            
            Stat::make('Pending Order Cards', 
                KanbanCard::where('status', KanbanCard::STATUS_PENDING_ORDER)->count())
                ->description('Kanban cards waiting for orders')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
            
            Stat::make('Active Items', 
                InventoryItem::query()
                    ->whereRaw('active::boolean = true')
                    ->count())
                ->description('Total active inventory items')
                ->descriptionIcon('heroicon-m-cube')
                ->color('success'),
        ];
    }
} 