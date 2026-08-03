<?php

namespace App\Filament\Maintenance\Resources\MaintenanceFleetPlanResource\Pages;

use App\Filament\Maintenance\Resources\MaintenanceFleetPlanResource;
use App\Services\Maintenance\MaintenanceFleetPlanScheduler;
use Filament\Resources\Pages\CreateRecord;

class CreateMaintenanceFleetPlan extends CreateRecord
{
    protected static string $resource = MaintenanceFleetPlanResource::class;

    protected function afterCreate(): void
    {
        app(MaintenanceFleetPlanScheduler::class)->syncMatchingAssets($this->record);
    }
}
