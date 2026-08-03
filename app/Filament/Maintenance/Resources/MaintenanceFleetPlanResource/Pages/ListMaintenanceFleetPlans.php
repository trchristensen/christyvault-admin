<?php

namespace App\Filament\Maintenance\Resources\MaintenanceFleetPlanResource\Pages;

use App\Filament\Maintenance\Resources\MaintenanceFleetPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMaintenanceFleetPlans extends ListRecords
{
    protected static string $resource = MaintenanceFleetPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
