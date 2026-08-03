<?php

namespace App\Filament\Maintenance\Widgets;

use App\Filament\Maintenance\Resources\MaintenanceAssetResource;
use App\Filament\Maintenance\Resources\MaintenancePlanResource;
use App\Filament\Maintenance\Resources\MaintenanceRequestResource;
use App\Filament\Maintenance\Resources\MaintenanceWorkOrderResource;
use App\Models\MaintenanceAsset;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceWorkOrder;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MaintenanceStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $openStatuses = ['completed', 'canceled'];

        return [
            Stat::make('Equipment down', MaintenanceAsset::where('status', 'out_of_service')->count())
                ->description('Assets currently out of service')->descriptionIcon('heroicon-m-no-symbol')->color('danger')
                ->url(MaintenanceAssetResource::getUrl('index', ['tableFilters' => ['status' => ['value' => 'out_of_service']]])),
            Stat::make('New requests', MaintenanceRequest::where('status', 'new')->count())
                ->description('Waiting for triage')->descriptionIcon('heroicon-m-inbox-arrow-down')->color('warning')
                ->url(MaintenanceRequestResource::getUrl('index')),
            Stat::make('Overdue work', MaintenanceWorkOrder::whereNotIn('status', $openStatuses)->where('due_at', '<', now())->count())
                ->description('Open work orders past due')->descriptionIcon('heroicon-m-clock')->color('danger')
                ->url(MaintenanceWorkOrderResource::getUrl('index')),
            Stat::make('PM due soon', MaintenancePlan::where('active', true)->where('trigger_type', 'calendar')->whereBetween('next_due_date', [today(), today()->addDays(30)])->count())
                ->description('Next 30 days')->descriptionIcon('heroicon-m-arrow-path-rounded-square')->color('info')
                ->url(MaintenancePlanResource::getUrl('index')),
        ];
    }
}
